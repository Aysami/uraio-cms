<?php
/**
 * Website generation engine: templates, pages, sections and publishing.
 */

function template_layouts(): array
{
    return [
        'aurora' => [
            'name' => 'Aurora',
            'group' => 'universal',
            'description' => 'A clean, confident layout for service businesses and modern brands.',
            'pages' => [
                ['title' => 'Home', 'slug' => 'home', 'is_home' => 1, 'sections' => ['hero', 'features', 'about', 'gallery', 'testimonials', 'hours', 'cta', 'contact']],
                ['title' => 'About', 'slug' => 'about', 'sections' => ['page_header', 'about', 'team', 'cta']],
                ['title' => 'Services', 'slug' => 'services', 'sections' => ['page_header', 'services', 'testimonials', 'cta']],
                ['title' => 'Gallery', 'slug' => 'gallery', 'sections' => ['page_header', 'gallery']],
                ['title' => 'Contact', 'slug' => 'contact', 'sections' => ['page_header', 'contact', 'map', 'hours']],
                ['title' => 'Bookings', 'slug' => 'bookings', 'sections' => ['page_header', 'booking']],
            ],
        ],
        'saffron' => [
            'name' => 'Saffron',
            'group' => 'restaurant',
            'description' => 'Warm, appetite-led design for restaurants, cafes and food brands.',
            'pages' => [
                ['title' => 'Home', 'slug' => 'home', 'is_home' => 1, 'sections' => ['hero', 'about', 'menu', 'gallery', 'testimonials', 'hours', 'contact']],
                ['title' => 'Menu', 'slug' => 'menu', 'sections' => ['page_header', 'menu', 'cta']],
                ['title' => 'About', 'slug' => 'about', 'sections' => ['page_header', 'about', 'team']],
                ['title' => 'Gallery', 'slug' => 'gallery', 'sections' => ['page_header', 'gallery']],
                ['title' => 'Reservations', 'slug' => 'reservations', 'sections' => ['page_header', 'booking']],
                ['title' => 'Contact', 'slug' => 'contact', 'sections' => ['page_header', 'contact', 'map', 'hours']],
            ],
        ],
        'azure' => [
            'name' => 'Azure',
            'group' => 'hotel',
            'description' => 'Spacious hospitality layout with room showcases and reservations.',
            'pages' => [
                ['title' => 'Home', 'slug' => 'home', 'is_home' => 1, 'sections' => ['hero', 'features', 'rooms', 'about', 'gallery', 'testimonials', 'contact']],
                ['title' => 'Rooms', 'slug' => 'rooms', 'sections' => ['page_header', 'rooms', 'cta']],
                ['title' => 'Facilities', 'slug' => 'facilities', 'sections' => ['page_header', 'features', 'gallery']],
                ['title' => 'Gallery', 'slug' => 'gallery', 'sections' => ['page_header', 'gallery']],
                ['title' => 'Reservations', 'slug' => 'reservations', 'sections' => ['page_header', 'booking']],
                ['title' => 'Contact', 'slug' => 'contact', 'sections' => ['page_header', 'contact', 'map']],
            ],
        ],
        'market' => [
            'name' => 'Market',
            'group' => 'ecommerce',
            'description' => 'Conversion-focused storefront with product collections and checkout.',
            'pages' => [
                ['title' => 'Home', 'slug' => 'home', 'is_home' => 1, 'sections' => ['hero', 'products', 'features', 'about', 'testimonials', 'contact']],
                ['title' => 'Shop', 'slug' => 'shop', 'sections' => ['page_header', 'products', 'cta']],
                ['title' => 'About', 'slug' => 'about', 'sections' => ['page_header', 'about', 'team']],
                ['title' => 'Contact', 'slug' => 'contact', 'sections' => ['page_header', 'contact', 'map']],
            ],
        ],
        'slate' => [
            'name' => 'Slate',
            'group' => 'professional',
            'description' => 'Editorial, high-trust design for consultants, firms and agencies.',
            'pages' => [
                ['title' => 'Home', 'slug' => 'home', 'is_home' => 1, 'sections' => ['hero', 'features', 'about', 'stats', 'testimonials', 'contact']],
                ['title' => 'Services', 'slug' => 'services', 'sections' => ['page_header', 'services', 'features', 'cta']],
                ['title' => 'About', 'slug' => 'about', 'sections' => ['page_header', 'about', 'team', 'stats']],
                ['title' => 'Contact', 'slug' => 'contact', 'sections' => ['page_header', 'contact', 'map']],
                ['title' => 'Book a Consultation', 'slug' => 'book', 'sections' => ['page_header', 'booking']],
            ],
        ],
    ];
}

function section_schemas(): array
{
    $text = fn (string $label, array $extra = []) => array_merge(['label' => $label, 'type' => 'text'], $extra);
    $textarea = fn (string $label, array $extra = []) => array_merge(['label' => $label, 'type' => 'textarea'], $extra);
    $image = fn (string $label) => ['label' => $label, 'type' => 'image'];
    return [
        'hero' => ['label' => 'Hero', 'icon' => 'sparkles', 'fields' => [
            'eyebrow' => $text('Eyebrow'),
            'heading' => $text('Headline'),
            'subheading' => $textarea('Subheadline', ['rows' => 3]),
            'button_text' => $text('Button label'),
            'button_link' => $text('Button link'),
            'image' => $image('Hero image'),
            'style' => ['label' => 'Layout', 'type' => 'select', 'options' => ['split' => 'Text + image', 'centered' => 'Centered', 'image' => 'Image background']],
        ]],
        'page_header' => ['label' => 'Page header', 'icon' => 'layout', 'fields' => [
            'eyebrow' => $text('Eyebrow'),
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
            'image' => $image('Background image'),
        ]],
        'about' => ['label' => 'About', 'icon' => 'info', 'fields' => [
            'eyebrow' => $text('Eyebrow'),
            'heading' => $text('Heading'),
            'body' => $textarea('Body', ['rows' => 6]),
            'image' => $image('Image'),
            'points' => ['label' => 'Highlights', 'type' => 'repeater', 'fields' => ['text' => $text('Highlight')]],
        ]],
        'features' => ['label' => 'Features', 'icon' => 'check', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
            'items' => ['label' => 'Features', 'type' => 'repeater', 'fields' => [
                'icon' => $text('Icon (emoji)'), 'title' => $text('Title'), 'description' => $textarea('Description', ['rows' => 2]),
            ]],
        ]],
        'services' => ['label' => 'Services', 'icon' => 'briefcase', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
            'source' => ['label' => 'Source', 'type' => 'select', 'options' => ['products' => 'Products & services', 'menu' => 'Menu items']],
        ]],
        'menu' => ['label' => 'Menu', 'icon' => 'book', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Introduction', ['rows' => 3]),
        ]],
        'products' => ['label' => 'Products', 'icon' => 'shopping-bag', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
            'limit' => ['label' => 'Maximum products', 'type' => 'number'],
        ]],
        'rooms' => ['label' => 'Rooms', 'icon' => 'bed', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Introduction', ['rows' => 3]),
        ]],
        'properties' => ['label' => 'Property listings', 'icon' => 'home', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Introduction', ['rows' => 2]),
        ]],
        'courses' => ['label' => 'Courses', 'icon' => 'graduation', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Introduction', ['rows' => 2]),
        ]],
        'events' => ['label' => 'Events', 'icon' => 'calendar', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Introduction', ['rows' => 2]),
        ]],
        'donations' => ['label' => 'Donations', 'icon' => 'heart', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Introduction', ['rows' => 3]),
            'button_text' => $text('Donate button label'),
        ]],
        'announcements' => ['label' => 'Announcements', 'icon' => 'megaphone', 'fields' => [
            'heading' => $text('Heading'),
            'limit' => ['label' => 'Maximum announcements', 'type' => 'number'],
        ]],
        'gallery' => ['label' => 'Gallery', 'icon' => 'image', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
            'images' => ['label' => 'Images', 'type' => 'repeater', 'fields' => ['image' => $image('Image'), 'caption' => $text('Caption')]],
        ]],
        'testimonials' => ['label' => 'Testimonials', 'icon' => 'star', 'fields' => [
            'heading' => $text('Heading'),
            'source' => ['label' => 'Source', 'type' => 'select', 'options' => ['reviews' => 'Approved reviews', 'custom' => 'Custom testimonials']],
            'items' => ['label' => 'Custom testimonials', 'type' => 'repeater', 'fields' => [
                'name' => $text('Name'), 'role' => $text('Role'), 'quote' => $textarea('Quote', ['rows' => 2]),
            ]],
        ]],
        'hours' => ['label' => 'Opening hours', 'icon' => 'clock', 'fields' => [
            'heading' => $text('Heading'),
            'note' => $textarea('Note', ['rows' => 2]),
        ]],
        'contact' => ['label' => 'Contact', 'icon' => 'mail', 'fields' => [
            'heading' => $text('Heading'),
            'body' => $textarea('Body', ['rows' => 3]),
            'show_form' => ['label' => 'Show contact form', 'type' => 'select', 'options' => ['1' => 'Yes', '0' => 'No']],
            'button_text' => $text('WhatsApp button label'),
        ]],
        'map' => ['label' => 'Map', 'icon' => 'map', 'fields' => [
            'heading' => $text('Heading'),
            'address' => $text('Address override'),
            'embed_url' => ['label' => 'Google Maps embed URL', 'type' => 'url'],
        ]],
        'cta' => ['label' => 'Call to action', 'icon' => 'megaphone', 'fields' => [
            'heading' => $text('Heading'),
            'body' => $textarea('Body', ['rows' => 2]),
            'button_text' => $text('Button label'),
            'button_link' => $text('Button link'),
        ]],
        'faq' => ['label' => 'FAQ', 'icon' => 'help', 'fields' => [
            'heading' => $text('Heading'),
            'items' => ['label' => 'Questions', 'type' => 'repeater', 'fields' => ['question' => $text('Question'), 'answer' => $textarea('Answer', ['rows' => 2])]],
        ]],
        'booking' => ['label' => 'Booking form', 'icon' => 'calendar', 'fields' => [
            'heading' => $text('Heading'),
            'body' => $textarea('Introduction', ['rows' => 3]),
            'button_text' => $text('Submit button label'),
            'party_label' => $text('Party size label'),
        ]],
        'team' => ['label' => 'Team', 'icon' => 'users', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
        ]],
        'stats' => ['label' => 'Business stats', 'icon' => 'bar-chart', 'fields' => [
            'heading' => $text('Heading'),
            'subheading' => $textarea('Subheading', ['rows' => 2]),
        ]],
    ];
}

function section_defaults(string $type, array $business, string $templateSlug = 'aurora'): array
{
    $name = $business['name'] ?? 'Your business';
    $category = $business['category_name'] ?? 'business';
    $city = $business['city'] ?? '';
    $defaults = [
        'hero' => [
            'eyebrow' => $category,
            'heading' => 'Welcome to ' . $name,
            'subheading' => $business['description'] ?: ('Professional ' . strtolower($category) . ' services' . ($city ? ' in ' . $city : '') . '.'),
            'button_text' => 'Get in touch',
            'button_link' => 'contact',
            'image' => $business['cover'] ?? '',
            'style' => 'split',
        ],
        'page_header' => ['eyebrow' => $category, 'heading' => $name, 'subheading' => '', 'image' => $business['cover'] ?? ''],
        'about' => [
            'eyebrow' => 'About us',
            'heading' => 'Built around our customers',
            'body' => $business['description'] ?: ($name . ' is a ' . strtolower($category) . ' business' . ($city ? ' based in ' . $city : '') . '. We are committed to quality, service and a great customer experience.'),
            'image' => $business['logo'] ?? $business['cover'] ?? '',
            'points' => [['text' => 'Friendly, knowledgeable team'], ['text' => 'Clear pricing and honest service'], ['text' => 'Convenient location and opening hours']],
        ],
        'features' => [
            'heading' => 'Why choose us',
            'subheading' => 'The details that make working with us easy.',
            'items' => [
                ['icon' => '✓', 'title' => 'Reliable service', 'description' => 'We show up, communicate clearly and deliver what we promise.'],
                ['icon' => '★', 'title' => 'Quality first', 'description' => 'Every interaction is handled with care and attention to detail.'],
                ['icon' => '↗', 'title' => 'Easy to reach', 'description' => 'Contact us by phone, WhatsApp, email or through this website.'],
            ],
        ],
        'services' => ['heading' => 'What we offer', 'subheading' => 'Explore our products and services.', 'source' => 'products'],
        'menu' => ['heading' => 'Our menu', 'subheading' => 'Freshly prepared and served with care.'],
        'products' => ['heading' => 'Shop our collection', 'subheading' => 'Quality products, ready when you are.', 'limit' => 12],
        'rooms' => ['heading' => 'Rooms & suites', 'subheading' => 'Comfortable spaces designed for a restful stay.'],
        'properties' => ['heading' => 'Available properties', 'subheading' => 'Find your next home or investment.'],
        'courses' => ['heading' => 'Courses & programmes', 'subheading' => 'Learn with experienced instructors.'],
        'events' => ['heading' => 'Upcoming events', 'subheading' => 'Join us at our next gathering.'],
        'donations' => ['heading' => 'Support our work', 'subheading' => 'Your giving makes a measurable difference.', 'button_text' => 'Donate now'],
        'announcements' => ['heading' => 'Latest announcements', 'limit' => 3],
        'gallery' => ['heading' => 'A look around', 'subheading' => '', 'images' => []],
        'testimonials' => ['heading' => 'What our customers say', 'source' => 'reviews', 'items' => []],
        'hours' => ['heading' => 'Opening hours', 'note' => 'Public holidays may differ. Please call ahead.'],
        'contact' => ['heading' => 'Get in touch', 'body' => 'We would love to hear from you. Send a message and we will respond as soon as possible.', 'show_form' => '1', 'button_text' => 'Chat on WhatsApp'],
        'map' => ['heading' => 'Find us', 'address' => trim(($business['address'] ?? '') . ' ' . $city), 'embed_url' => ''],
        'cta' => ['heading' => 'Ready to get started?', 'body' => 'Speak with our team today.', 'button_text' => 'Contact us', 'button_link' => 'contact'],
        'faq' => ['heading' => 'Frequently asked questions', 'items' => []],
        'booking' => ['heading' => 'Make a booking', 'body' => 'Choose a date and time that works for you. We will confirm your booking shortly.', 'button_text' => 'Request booking', 'party_label' => 'Number of guests'],
        'team' => ['heading' => 'Meet the team', 'subheading' => 'The people behind ' . $name . '.'],
        'stats' => ['heading' => 'Our business at a glance', 'subheading' => 'Real numbers from our work.'],
    ];
    return $defaults[$type] ?? [];
}

function default_template_for_business(array $business): ?array
{
    if (!empty($business['category_id'])) {
        $template = category_template((int) $business['category_id']);
        if ($template) {
            return $template;
        }
    }
    return db_row("SELECT * FROM website_templates WHERE status = 'active' ORDER BY is_default DESC, sort_order ASC LIMIT 1");
}

function create_website_for_business(array $business, int|string|null $templateId = null, bool $reset = false): int
{
    $existing = db_row('SELECT * FROM business_websites WHERE business_id = ?', [$business['id']]);
    if ($existing && !$reset) {
        return (int) $existing['id'];
    }
    $template = null;
    if ($templateId !== null) {
        $template = is_numeric($templateId)
            ? db_row('SELECT * FROM website_templates WHERE id = ?', [(int) $templateId])
            : db_row('SELECT * FROM website_templates WHERE slug = ?', [(string) $templateId]);
    }
    $template ??= default_template_for_business($business);
    $templateSlug = $template['slug'] ?? 'aurora';
    $layouts = template_layouts();
    $layout = $layouts[$templateSlug] ?? $layouts['aurora'];
    $categoryName = $business['category_name'] ?? 'Business';
    $business = array_merge(['category_name' => $categoryName], $business);

    $pdo = db();
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        if ($existing) {
            db_exec('DELETE FROM website_pages WHERE business_id = ?', [$business['id']]);
            db_update('business_websites', [
                'template_id' => $template['id'] ?? null,
                'status' => 'draft',
                'published_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            $websiteId = (int) $existing['id'];
        } else {
            $websiteId = db_insert('business_websites', [
                'business_id' => $business['id'],
                'template_id' => $template['id'] ?? null,
                'title' => $business['name'] ?? 'My website',
                'tagline' => $business['description'] ? str_limit($business['description'], 120) : '',
                'seo_title' => ($business['name'] ?? '') . ' · ' . $categoryName,
                'seo_description' => str_limit((string) ($business['description'] ?? ''), 300),
                'status' => 'draft',
            ]);
        }

        foreach ($layout['pages'] as $pageIndex => $page) {
            $pageId = db_insert('website_pages', [
                'business_id' => $business['id'],
                'website_id' => $websiteId,
                'title' => $page['title'],
                'slug' => $page['slug'],
                'type' => $page['slug'] === 'home' ? 'home' : $page['slug'],
                'is_home' => (int) (!empty($page['is_home'])),
                'sort_order' => $pageIndex,
                'status' => 'published',
            ]);
            foreach ($page['sections'] as $sectionIndex => $type) {
                $content = section_defaults($type, $business, $templateSlug);
                db_insert('website_sections', [
                    'page_id' => $pageId,
                    'type' => $type,
                    'title' => $content['heading'] ?? ($content['title'] ?? null),
                    'subtitle' => $content['subheading'] ?? null,
                    'content' => json_encode($content),
                    'sort_order' => $sectionIndex,
                    'is_visible' => 1,
                ]);
            }
        }
        if ($ownTransaction) {
            $pdo->commit();
        }
        return $websiteId;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function business_website(int $businessId): ?array
{
    return db_row(
        'SELECT w.*, t.slug AS template_slug, t.name AS template_name
         FROM business_websites w LEFT JOIN website_templates t ON t.id = w.template_id
         WHERE w.business_id = ?',
        [$businessId]
    );
}

function website_pages(int $businessId): array
{
    return db_all('SELECT * FROM website_pages WHERE business_id = ? ORDER BY sort_order ASC, id ASC', [$businessId]);
}

function website_home_page(int $businessId): ?array
{
    return db_row('SELECT * FROM website_pages WHERE business_id = ? AND is_home = 1 LIMIT 1', [$businessId])
        ?? db_row('SELECT * FROM website_pages WHERE business_id = ? ORDER BY sort_order ASC LIMIT 1', [$businessId]);
}

function website_page_by_slug(int $businessId, string $slug): ?array
{
    return db_row('SELECT * FROM website_pages WHERE business_id = ? AND slug = ? AND status = ?', [$businessId, $slug, 'published']);
}

function website_sections(int $pageId, bool $visibleOnly = false): array
{
    $sql = 'SELECT * FROM website_sections WHERE page_id = ?';
    if ($visibleOnly) {
        $sql .= ' AND is_visible = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db_all($sql, [$pageId]);
}

function website_section_by_id(int $businessId, int $sectionId): ?array
{
    return db_row(
        'SELECT s.*, p.business_id FROM website_sections s
         JOIN website_pages p ON p.id = s.page_id
         WHERE s.id = ? AND p.business_id = ?',
        [$sectionId, $businessId]
    );
}

function save_website_section(array $business, int $pageId, array $input, ?int $sectionId = null): int
{
    $page = db_row('SELECT * FROM website_pages WHERE id = ? AND business_id = ?', [$pageId, $business['id']]);
    if (!$page) {
        throw new RuntimeException('Page not found.');
    }
    $type = (string) ($input['type'] ?? '');
    $schemas = section_schemas();
    if (!isset($schemas[$type])) {
        throw new InvalidArgumentException('Choose a valid section type.');
    }
    $content = [];
    foreach ($schemas[$type]['fields'] as $key => $field) {
        $raw = $input['content'][$key] ?? null;
        if (($field['type'] ?? 'text') === 'repeater') {
            $rows = [];
            $rawRows = is_array($raw) ? $raw : [];
            $rowIndex = 0;
            foreach ($rawRows as $row) {
                $clean = [];
                foreach ($field['fields'] as $subKey => $subField) {
                    $value = trim((string) ($row[$subKey] ?? ''));
                    if ($subField['type'] === 'image') {
                        $existing = trim((string) ($row['existing_' . $subKey] ?? ''));
                        $uploaded = upload_image_file($_FILES['section_files'][$key][$rowIndex][$subKey] ?? null, 'general');
                        $value = $uploaded ?: ($value ?: $existing);
                    }
                    if ($value !== '') {
                        $clean[$subKey] = $value;
                    }
                }
                if ($clean) {
                    $rows[] = $clean;
                }
                $rowIndex++;
            }
            $content[$key] = $rows;
            continue;
        }
        if (($field['type'] ?? 'text') === 'image') {
            $existing = trim((string) ($input['content']['existing_' . $key] ?? ''));
            $uploaded = upload_image_file($_FILES['section_files'][$key] ?? null, 'general');
            $content[$key] = $uploaded ?: ($raw ?: $existing);
            continue;
        }
        $content[$key] = is_array($raw) ? $raw : trim((string) $raw);
    }
    $data = [
        'type' => $type,
        'title' => $content['heading'] ?? ($content['title'] ?? null),
        'subtitle' => $content['subheading'] ?? null,
        'content' => json_encode($content),
        'is_visible' => isset($input['is_visible']) ? 1 : 0,
    ];
    if ($sectionId !== null) {
        $existing = website_section_by_id((int) $business['id'], $sectionId);
        if (!$existing) {
            throw new RuntimeException('Section not found.');
        }
        db_update('website_sections', $data, 'id = :id', ['id' => $sectionId]);
        return $sectionId;
    }
    $next = (int) db_val('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM website_sections WHERE page_id = ?', [$pageId]);
    $data['page_id'] = $pageId;
    $data['sort_order'] = $next;
    return db_insert('website_sections', $data);
}

function delete_website_section(array $business, int $sectionId): bool
{
    $section = website_section_by_id((int) $business['id'], $sectionId);
    if (!$section) {
        return false;
    }
    return db_exec('DELETE FROM website_sections WHERE id = ?', [$sectionId]) > 0;
}

function move_website_section(array $business, int $sectionId, string $direction): bool
{
    $section = website_section_by_id((int) $business['id'], $sectionId);
    if (!$section) {
        return false;
    }
    $operator = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';
    $neighbour = db_row(
        "SELECT * FROM website_sections WHERE page_id = ? AND sort_order $operator ? ORDER BY sort_order $order LIMIT 1",
        [$section['page_id'], $section['sort_order']]
    );
    if (!$neighbour) {
        return false;
    }
    db_update('website_sections', ['sort_order' => $neighbour['sort_order']], 'id = :id', ['id' => $section['id']]);
    db_update('website_sections', ['sort_order' => $section['sort_order']], 'id = :id', ['id' => $neighbour['id']]);
    return true;
}

function publish_website(array $business): bool
{
    $website = business_website((int) $business['id']);
    if (!$website) {
        return false;
    }
    db_update('business_websites', ['status' => 'published', 'published_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $website['id']]);
    db_exec("UPDATE website_pages SET status = 'published' WHERE business_id = ?", [$business['id']]);
    db_update('businesses', ['published_at' => date('Y-m-d H:i:s'), 'onboarding_step' => 'done'], 'id = :id', ['id' => $business['id']]);
    activity_log('website.published', 'business_website', $website['id'], [], (int) $business['id']);
    return true;
}

function unpublish_website(array $business): bool
{
    $website = business_website((int) $business['id']);
    if (!$website) {
        return false;
    }
    db_update('business_websites', ['status' => 'unpublished'], 'id = :id', ['id' => $website['id']]);
    activity_log('website.unpublished', 'business_website', $website['id'], [], (int) $business['id']);
    return true;
}

function website_stats(int $businessId): array
{
    return [
        'visits_30d' => (int) db_val('SELECT COUNT(*) FROM website_visits WHERE business_id = ? AND visited_at > (NOW() - INTERVAL 30 DAY)', [$businessId]),
        'visits_today' => (int) db_val('SELECT COUNT(*) FROM website_visits WHERE business_id = ? AND DATE(visited_at) = CURDATE()', [$businessId]),
        'pages' => (int) db_val('SELECT COUNT(*) FROM website_pages WHERE business_id = ?', [$businessId]),
        'sections' => (int) db_val('SELECT COUNT(*) FROM website_sections s JOIN website_pages p ON p.id = s.page_id WHERE p.business_id = ?', [$businessId]),
    ];
}
