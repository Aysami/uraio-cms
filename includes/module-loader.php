<?php
/**
 * Module registry. Modules are PHP definition files under /modules/{slug}/module.php.
 * A definition declares navigation, entities, widgets and public site sections.
 */

function module_registry(): array
{
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }
    $registry = [];
    $dirs = glob(BASE_PATH . '/modules/*', GLOB_ONLYDIR) ?: [];
    foreach ($dirs as $dir) {
        $file = $dir . '/module.php';
        if (!is_file($file)) {
            continue;
        }
        $definition = require $file;
        if (is_array($definition) && !empty($definition['slug'])) {
            $registry[$definition['slug']] = $definition;
        }
    }
    return $registry;
}

function module_definition(string $slug): ?array
{
    return module_registry()[$slug] ?? null;
}

function enabled_module_slugs(array $business): array
{
    $slugs = ['universal'];
    $rows = db_all(
        "SELECT m.slug FROM business_modules bm
         JOIN modules m ON m.id = bm.module_id
         WHERE bm.business_id = ? AND bm.enabled = 1 AND m.status = 'active'",
        [$business['id']]
    );
    foreach ($rows as $row) {
        $slugs[] = $row['slug'];
    }
    // A category's default modules are activated during onboarding, but this
    // fallback keeps existing businesses usable after a category change.
    if (count($slugs) === 1 && !empty($business['category_id'])) {
        foreach (category_modules((int) $business['category_id']) as $module) {
            $slugs[] = $module['slug'];
        }
    }
    return array_values(array_unique($slugs));
}

function enabled_modules(array $business): array
{
    $definitions = [];
    foreach (enabled_module_slugs($business) as $slug) {
        $definition = module_definition($slug);
        if ($definition) {
            $definitions[$slug] = $definition;
        }
    }
    return $definitions;
}

function activate_category_modules(int $businessId, int $categoryId): void
{
    $modules = category_modules($categoryId);
    if (!$modules) {
        $modules = db_all("SELECT * FROM modules WHERE slug = 'universal'");
    }
    foreach ($modules as $module) {
        db_exec(
            'INSERT INTO business_modules (business_id, module_id, enabled) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE enabled = 1',
            [$businessId, $module['id']]
        );
    }
    // Universal is always available.
    $universal = db_val("SELECT id FROM modules WHERE slug = 'universal'");
    if ($universal) {
        db_exec('INSERT IGNORE INTO business_modules (business_id, module_id, enabled) VALUES (?, ?, 1)', [$businessId, $universal]);
    }
}

function business_has_module(array $business, string $slug): bool
{
    return in_array($slug, enabled_module_slugs($business), true);
}

function module_nav(array $business): array
{
    $nav = [
        ['label' => 'Overview', 'url' => 'app/dashboard.php', 'icon' => 'grid', 'key' => 'overview'],
        ['label' => 'Customers', 'url' => 'app/records.php?entity=customers', 'icon' => 'users', 'key' => 'customers'],
    ];
    foreach (enabled_modules($business) as $slug => $module) {
        foreach ($module['nav'] ?? [] as $item) {
            $item['key'] ??= $slug . '.' . ($item['entity'] ?? $item['label']);
            $item['module'] = $slug;
            $nav[] = $item;
        }
    }
    $nav[] = ['label' => 'Invoices', 'url' => 'app/records.php?entity=invoices', 'icon' => 'file-text', 'key' => 'invoices'];
    $nav[] = ['label' => 'Reviews', 'url' => 'app/records.php?entity=reviews', 'icon' => 'star', 'key' => 'reviews'];
    $nav[] = ['label' => 'Messages', 'url' => 'app/records.php?entity=messages', 'icon' => 'mail', 'key' => 'messages'];
    $nav[] = ['label' => 'Staff', 'url' => 'app/records.php?entity=staff', 'icon' => 'briefcase', 'key' => 'staff'];
    return $nav;
}

function module_widgets(array $business): array
{
    $widgets = [];
    foreach (enabled_modules($business) as $slug => $module) {
        foreach ($module['widgets'] ?? [] as $widget) {
            $widget['module'] = $slug;
            $widgets[] = $widget;
        }
    }
    return $widgets;
}

function entity_definitions(array $business): array
{
    $entities = [];
    foreach (enabled_modules($business) as $slug => $module) {
        foreach ($module['entities'] ?? [] as $key => $entity) {
            $entity['key'] = $key;
            $entity['module'] = $slug;
            $entities[$key] = $entity;
        }
    }
    return $entities;
}

function entity_definition(array $business, string $key): ?array
{
    $entities = entity_definitions($business);
    return $entities[$key] ?? null;
}

function entity_count(array $business, string $entityKey, array $extraWhere = []): int
{
    $entity = entity_definition($business, $entityKey);
    if (!$entity) {
        return 0;
    }
    $sql = 'SELECT COUNT(*) FROM `' . $entity['table'] . '` WHERE business_id = ?';
    $params = [$business['id']];
    foreach ($extraWhere as $column => $value) {
        $sql .= " AND `$column` = ?";
        $params[] = $value;
    }
    return (int) db_val($sql, $params);
}

function module_status_list(): array
{
    return db_all('SELECT * FROM modules ORDER BY is_core DESC, name ASC');
}

/**
 * Evaluate a module dashboard widget against tenant-scoped data.
 * Widget definitions are trusted module code; no user input is interpolated.
 */
function widget_value(array $business, array $widget): float
{
    $table = (string) ($widget['table'] ?? '');
    if ($table === '' && !empty($widget['entity'])) {
        $entity = entity_definition($business, (string) $widget['entity']);
        $table = (string) ($entity['table'] ?? '');
    }
    if ($table === '' || !preg_match('/^[a-z0-9_]+$/', $table)) {
        return 0;
    }
    $metric = ($widget['metric'] ?? 'count') === 'sum' ? 'sum' : 'count';
    $column = preg_replace('/[^a-z0-9_]/', '', (string) ($widget['column'] ?? 'id'));
    $select = $metric === 'sum' ? "COALESCE(SUM(`$column`), 0)" : 'COUNT(*)';
    $where = ['business_id = ?'];
    $params = [$business['id']];
    foreach (($widget['where'] ?? []) as $key => $value) {
        $key = preg_replace('/[^a-z0-9_]/', '', (string) $key);
        if ($key !== '') {
            $where[] = "`$key` = ?";
            $params[] = $value;
        }
    }
    if (!empty($widget['where_raw'])) {
        $where[] = '(' . $widget['where_raw'] . ')';
    }
    try {
        return (float) db_val("SELECT $select FROM `$table` WHERE " . implode(' AND ', $where), $params, 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function format_widget_value(array $widget, float $value, string $currency = 'NGN'): string
{
    if (($widget['format'] ?? '') === 'money') {
        return money($value, $currency);
    }
    return number_format($value);
}
