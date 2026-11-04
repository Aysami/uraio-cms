<?php
/**
 * Category engine: hierarchical business categories, module/template mapping
 * and the category selector data source.
 */

function all_categories(bool $activeOnly = true): array
{
    $sql = 'SELECT c.*, p.name AS parent_name,
                   (SELECT COUNT(*) FROM category_modules cm WHERE cm.category_id = c.id) AS module_count
            FROM categories c LEFT JOIN categories p ON p.id = c.parent_id';
    if ($activeOnly) {
        $sql .= " WHERE c.status = 'active'";
    }
    $sql .= ' ORDER BY c.sort_order ASC, c.name ASC';
    return db_all($sql);
}

function categories_grouped(): array
{
    $rows = all_categories();
    $grouped = [];
    foreach ($rows as $row) {
        if ($row['parent_id']) {
            $grouped[(int) $row['parent_id']][] = $row;
        }
    }
    return $grouped;
}

function category_tree(): array
{
    $rows = all_categories();
    $byParent = [];
    foreach ($rows as $row) {
        $byParent[(int) ($row['parent_id'] ?? 0)][] = $row;
    }
    $build = function (int $parent) use (&$build, $byParent): array {
        $items = [];
        foreach ($byParent[$parent] ?? [] as $row) {
            $row['children'] = $build((int) $row['id']);
            $items[] = $row;
        }
        return $items;
    };
    return $build(0);
}

function category_by_id(int $id): ?array
{
    return db_row('SELECT * FROM categories WHERE id = ?', [$id]);
}

function category_by_slug(string $slug): ?array
{
    return db_row('SELECT * FROM categories WHERE slug = ?', [$slug]);
}

function popular_categories(int $limit = 12): array
{
    return db_all("SELECT * FROM categories WHERE status = 'active' AND is_popular = 1 ORDER BY sort_order ASC, name ASC LIMIT " . (int) $limit);
}

function search_categories(string $term, int $limit = 40): array
{
    $like = '%' . $term . '%';
    return db_all(
        "SELECT * FROM categories WHERE status = 'active' AND (name LIKE ? OR description LIKE ? OR slug LIKE ?)
         ORDER BY is_popular DESC, name ASC LIMIT " . (int) $limit,
        [$like, $like, $like]
    );
}

function category_modules(int $categoryId): array
{
    return db_all(
        'SELECT m.*, cm.is_default FROM category_modules cm
         JOIN modules m ON m.id = cm.module_id
         WHERE cm.category_id = ? AND m.status = \'active\'
         ORDER BY m.is_core DESC, m.name ASC',
        [$categoryId]
    );
}

function category_template(int $categoryId): ?array
{
    $category = category_by_id($categoryId);
    if ($category && $category['default_template_id']) {
        $template = db_row('SELECT * FROM website_templates WHERE id = ? AND status = \'active\'', [$category['default_template_id']]);
        if ($template) {
            return $template;
        }
    }
    return db_row("SELECT * FROM website_templates WHERE status = 'active' ORDER BY is_default DESC, sort_order ASC LIMIT 1");
}

function category_breadcrumb(array $category): array
{
    $crumbs = [];
    $current = $category;
    $guard = 0;
    while ($current && $guard++ < 10) {
        array_unshift($crumbs, $current);
        $current = $current['parent_id'] ? category_by_id((int) $current['parent_id']) : null;
    }
    return $crumbs;
}

function import_categories_from_json(string $file): array
{
    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException('Category file could not be read.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Category file is not valid JSON.');
    }
    return import_category_rows($data);
}

/**
 * Import rows shaped as:
 * [ { "group": "Food & Hospitality", "icon": "🍽️", "modules": [...],
 *     "template": "restaurant", "items": [ {"name": "...", "slug": "...", "description": "..."} ] } ]
 * Idempotent: existing slugs are updated, never duplicated.
 */
function import_category_rows(array $groups): array
{
    $created = 0;
    $updated = 0;
    $modulesBySlug = [];
    foreach (db_all('SELECT id, slug FROM modules') as $module) {
        $modulesBySlug[$module['slug']] = (int) $module['id'];
    }
    $templatesBySlug = [];
    foreach (db_all('SELECT id, slug FROM website_templates') as $template) {
        $templatesBySlug[$template['slug']] = (int) $template['id'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sort = 0;
        foreach ($groups as $group) {
            $groupName = trim((string) ($group['group'] ?? 'Other'));
            $groupSlug = slugify($groupName);
            $parent = db_row('SELECT * FROM categories WHERE slug = ?', [$groupSlug]);
            $parentData = [
                'name' => $groupName,
                'description' => $group['description'] ?? ('Businesses in ' . $groupName),
                'icon' => $group['icon'] ?? '🏷️',
                'sort_order' => $sort++,
                'status' => 'active',
                'source' => 'uraio',
                'default_template_id' => $templatesBySlug[$group['template'] ?? 'universal'] ?? null,
            ];
            if ($parent) {
                db_update('categories', $parentData, 'id = :id', ['id' => $parent['id']]);
                $parentId = (int) $parent['id'];
                $updated++;
            } else {
                $parentData['slug'] = $groupSlug;
                $parentId = db_insert('categories', $parentData);
                $created++;
            }
            // Parent gets the universal module plus group modules.
            $groupModules = array_unique(array_merge(['universal'], $group['modules'] ?? []));
            foreach ($groupModules as $moduleSlug) {
                if (isset($modulesBySlug[$moduleSlug])) {
                    db_exec('INSERT IGNORE INTO category_modules (category_id, module_id, is_default) VALUES (?, ?, 1)', [$parentId, $modulesBySlug[$moduleSlug]]);
                }
            }
            $itemSort = 0;
            foreach ($group['items'] ?? [] as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $slug = slugify($item['slug'] ?? $name);
                $row = db_row('SELECT * FROM categories WHERE slug = ?', [$slug]);
                $itemModules = array_unique(array_merge(['universal'], $item['modules'] ?? $group['modules'] ?? []));
                $data = [
                    'parent_id' => $parentId,
                    'name' => $name,
                    'description' => $item['description'] ?? ('Find and manage ' . strtolower($name) . ' businesses.'),
                    'icon' => $item['icon'] ?? ($group['icon'] ?? '🏷️'),
                    'is_popular' => (int) ($item['popular'] ?? 0),
                    'sort_order' => $itemSort++,
                    'status' => 'active',
                    'source' => 'uraio',
                    'default_template_id' => $templatesBySlug[$item['template'] ?? $group['template'] ?? 'universal'] ?? null,
                ];
                if ($row) {
                    db_update('categories', $data, 'id = :id', ['id' => $row['id']]);
                    $categoryId = (int) $row['id'];
                    $updated++;
                } else {
                    $data['slug'] = $slug;
                    $categoryId = db_insert('categories', $data);
                    $created++;
                }
                foreach ($itemModules as $moduleSlug) {
                    if (isset($modulesBySlug[$moduleSlug])) {
                        db_exec('INSERT IGNORE INTO category_modules (category_id, module_id, is_default) VALUES (?, ?, 1)', [$categoryId, $modulesBySlug[$moduleSlug]]);
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['created' => $created, 'updated' => $updated];
}

function import_categories_from_uraio_database(?string $host = null, ?string $dbName = null, ?string $user = null, ?string $pass = null, ?int $port = null): array
{
    // Controlled adapter: reads only id/name/slug/icon from the discovery database.
    // Never writes to the discovery database.
    $host ??= setting('uraio_db_host', '127.0.0.1');
    $dbName ??= setting('uraio_db_name', 'uraio');
    $user ??= setting('uraio_db_user', '');
    $pass ??= setting('uraio_db_pass', '');
    $port ??= (int) setting('uraio_db_port', 3306);
    if ($user === '') {
        throw new RuntimeException('Uraio discovery database credentials are not configured.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    $remote = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $rows = $remote->query('SELECT id, name, slug, icon FROM categories ORDER BY name ASC')->fetchAll();
    $created = 0;
    $updated = 0;
    $template = db_val("SELECT id FROM website_templates WHERE slug = 'universal'");
    foreach ($rows as $row) {
        $slug = slugify($row['slug'] ?: $row['name']);
        $existing = db_row('SELECT id FROM categories WHERE slug = ?', [$slug]);
        $data = [
            'name' => $row['name'],
            'icon' => $row['icon'] ?: '🏷️',
            'description' => 'Imported from Uraio Smart Discovery.',
            'status' => 'active',
            'source' => 'uraio_discovery',
            'source_id' => (string) $row['id'],
            'default_template_id' => $template ?: null,
        ];
        if ($existing) {
            db_update('categories', $data, 'id = :id', ['id' => $existing['id']]);
            $updated++;
        } else {
            $data['slug'] = $slug;
            db_insert('categories', $data);
            $created++;
        }
    }
    return ['created' => $created, 'updated' => $updated, 'source' => 'uraio_discovery'];
}
