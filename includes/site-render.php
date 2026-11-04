<?php
/**
 * Public website section renderers. Everything is tenant-scoped and escaped.
 */

function site_content(array $section): array
{
    return json_decode_assoc($section['content'] ?? null);
}

function site_value(array $content, string $key, string $default = ''): string
{
    $value = $content[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function site_page_url(array $business, string $slug, bool $preview = false): string
{
    if ($preview) {
        return app_url('site.php?preview=1&page=' . urlencode($slug === 'home' ? 'home' : $slug));
    }
    return business_site_url($business, $slug === 'home' ? '' : $slug);
}

function site_smart_link(string $link, array $business, bool $preview = false): string
{
    $link = trim($link);
    if ($link === '') {
        return '#contact';
    }
    if (preg_match('#^(https?:|mailto:|tel:)#i', $link) || str_starts_with($link, '#') || str_starts_with($link, '/')) {
        return $link;
    }
    return site_page_url($business, ltrim($link, '/'), $preview);
}

function site_heading(array $content, string $eyebrowKey = 'eyebrow'): string
{
    $eyebrow = site_value($content, $eyebrowKey);
    $heading = site_value($content, 'heading');
    $subheading = site_value($content, 'subheading');
    if ($heading === '' && $subheading === '') {
        return '';
    }
    return '<div class="section-heading">'
        . ($eyebrow !== '' ? '<p class="eyebrow">' . e($eyebrow) . '</p>' : '')
        . ($heading !== '' ? '<h2>' . e($heading) . '</h2>' : '')
        . ($subheading !== '' ? '<p class="section-sub">' . nl2br(e($subheading)) . '</p>' : '')
        . '</div>';
}

function site_empty_note(string $message, bool $preview): string
{
    return $preview ? '<p class="site-empty">' . e($message) . '</p>' : '';
}

function site_image_url(?string $path): string
{
    return $path ? media_url($path) : '';
}

function site_whatsapp_url(array $business, string $message = ''): string
{
    $number = preg_replace('/\D+/', '', (string) ($business['whatsapp'] ?? ''));
    if ($number === '') {
        return '';
    }
    if (str_starts_with($number, '0')) {
        $number = '234' . substr($number, 1);
    }
    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function site_render_hero(array $content, array $business, array $website, bool $preview): string
{
    $style = site_value($content, 'style', 'split');
    $image = site_image_url(site_value($content, 'image'));
    $heading = site_value($content, 'heading', (string) $business['name']);
    $subheading = site_value($content, 'subheading');
    $eyebrow = site_value($content, 'eyebrow');
    $buttonText = site_value($content, 'button_text');
    $buttonLink = site_smart_link(site_value($content, 'button_link', 'contact'), $business, $preview);
    $button = $buttonText !== '' ? '<a class="site-btn site-btn-primary" href="' . e($buttonLink) . '">' . e($buttonText) . '</a>' : '';
    $classes = 'site-hero site-hero-' . e($style);
    if ($style === 'image' && $image !== '') {
        $classes .= ' has-image';
    }
    $html = '<section class="' . $classes . '"' . ($style === 'image' && $image !== '' ? ' style="background-image:linear-gradient(110deg,rgba(5,15,20,.78),rgba(5,15,20,.32)),url(' . e($image) . ')"' : '') . '>';
    $html .= '<div class="site-container hero-inner">';
    $html .= '<div class="hero-copy">';
    if ($eyebrow !== '') {
        $html .= '<p class="eyebrow">' . e($eyebrow) . '</p>';
    }
    $html .= '<h1>' . nl2br(e($heading)) . '</h1>';
    if ($subheading !== '') {
        $html .= '<p class="hero-sub">' . nl2br(e($subheading)) . '</p>';
    }
    $html .= '<div class="hero-actions">' . $button;
    if (site_whatsapp_url($business) !== '') {
        $html .= '<a class="site-btn site-btn-ghost" href="' . e(site_whatsapp_url($business, 'Hello ' . $business['name'] . ', I would like to know more.')) . '" target="_blank" rel="noopener">Chat on WhatsApp</a>';
    }
    $html .= '</div></div>';
    if ($style === 'split') {
        $html .= '<div class="hero-media">';
        if ($image !== '') {
            $html .= '<img src="' . e($image) . '" alt="' . e((string) $business['name']) . '">';
        } else {
            $html .= '<div class="hero-placeholder"><span>' . e(initials((string) $business['name'])) . '</span><strong>' . e((string) $business['name']) . '</strong><span>' . e((string) ($business['category_name'] ?? 'Business')) . '</span></div>';
        }
        $html .= '</div>';
    }
    $html .= '</div></section>';
    return $html;
}

function site_render_page_header(array $content, array $business): string
{
    $image = site_image_url(site_value($content, 'image'));
    $style = $image !== '' ? ' style="background-image:linear-gradient(110deg,rgba(5,15,20,.8),rgba(5,15,20,.45)),url(' . e($image) . ')"' : '';
    return '<section class="site-page-header"' . $style . '><div class="site-container">'
        . (site_value($content, 'eyebrow') !== '' ? '<p class="eyebrow">' . e(site_value($content, 'eyebrow')) . '</p>' : '')
        . '<h1>' . e(site_value($content, 'heading', (string) $business['name'])) . '</h1>'
        . (site_value($content, 'subheading') !== '' ? '<p>' . nl2br(e(site_value($content, 'subheading'))) . '</p>' : '')
        . '</div></section>';
}

function site_render_about(array $content, array $business): string
{
    $image = site_image_url(site_value($content, 'image'));
    $points = is_array($content['points'] ?? null) ? $content['points'] : [];
    $html = '<section class="site-section"><div class="site-container about-grid">';
    $html .= '<div class="about-copy">' . site_heading($content);
    $html .= '<div class="prose">' . nl2br(e(site_value($content, 'body'))) . '</div>';
    if ($points) {
        $html .= '<ul class="tick-list">';
        foreach ($points as $point) {
            $text = is_array($point) ? (string) ($point['text'] ?? '') : (string) $point;
            if ($text !== '') {
                $html .= '<li>' . e($text) . '</li>';
            }
        }
        $html .= '</ul>';
    }
    $html .= '</div>';
    $html .= '<div class="about-media">';
    if ($image !== '') {
        $html .= '<img src="' . e($image) . '" alt="' . e((string) $business['name']) . '" loading="lazy">';
    } else {
        $html .= '<div class="media-placeholder">' . e(initials((string) $business['name'])) . '</div>';
    }
    $html .= '</div></div></section>';
    return $html;
}

function site_render_features(array $content, bool $preview): string
{
    $items = is_array($content['items'] ?? null) ? $content['items'] : [];
    if (!$items) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add feature cards in the website editor.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section site-section-tint"><div class="site-container">' . site_heading($content) . '<div class="feature-grid">';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $html .= '<article class="feature-card"><span class="feature-icon">' . e((string) ($item['icon'] ?? '✓')) . '</span><h3>' . e((string) ($item['title'] ?? '')) . '</h3><p>' . nl2br(e((string) ($item['description'] ?? ''))) . '</p></article>';
    }
    return $html . '</div></div></section>';
}

function site_render_services(array $content, array $business, bool $preview): string
{
    $rows = db_all(
        "SELECT * FROM products WHERE business_id = ? AND deleted_at IS NULL AND status = 'active' AND type IN ('product','service') ORDER BY featured DESC, name ASC LIMIT 9",
        [$business['id']]
    );
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add products or services from your dashboard to populate this section.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="card-grid">';
    foreach ($rows as $row) {
        $html .= site_product_card($row, $business);
    }
    return $html . '</div></div></section>';
}

function site_product_card(array $row, array $business): string
{
    $image = site_image_url($row['image'] ?? '');
    $price = (float) ($row['price'] ?? 0);
    $currency = (string) ($business['currency'] ?? 'NGN');
    $wa = site_whatsapp_url($business, 'Hello, I am interested in ' . ($row['name'] ?? 'this item') . '.');
    $html = '<article class="product-card">';
    if ($image !== '') {
        $html .= '<a class="product-media" href="' . e($wa ?: '#contact') . '"' . ($wa ? ' target="_blank" rel="noopener"' : '') . '><img src="' . e($image) . '" alt="' . e((string) ($row['name'] ?? '')) . '" loading="lazy"></a>';
    } else {
        $html .= '<div class="product-media product-placeholder"><span>' . e(initials((string) ($row['name'] ?? 'Item'))) . '</span></div>';
    }
    $html .= '<div class="product-body"><h3>' . e((string) ($row['name'] ?? '')) . '</h3>';
    if (!empty($row['description'])) {
        $html .= '<p>' . e(str_limit((string) $row['description'], 120)) . '</p>';
    }
    $html .= '<div class="product-foot"><strong>' . e($price > 0 ? money($price, $currency) : 'Ask for price') . '</strong>';
    if ($wa !== '') {
        $html .= '<a class="text-link" href="' . e($wa) . '" target="_blank" rel="noopener">Enquire</a>';
    }
    return $html . '</div></div></article>';
}

function site_render_menu(array $content, array $business, bool $preview): string
{
    $items = db_all(
        "SELECT m.*, c.name AS category_name FROM menu_items m
         LEFT JOIN product_categories c ON c.id = m.category_id
         WHERE m.business_id = ? AND m.status = 'active' ORDER BY c.sort_order ASC, c.name ASC, m.featured DESC, m.name ASC",
        [$business['id']]
    );
    if (!$items) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add menu items to show your menu here.', $preview) . '</div></section>';
    }
    $groups = [];
    foreach ($items as $item) {
        $groups[$item['category_name'] ?: 'Menu'][] = $item;
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content);
    foreach ($groups as $group => $rows) {
        $html .= '<div class="menu-group"><h3>' . e((string) $group) . '</h3><div class="menu-grid">';
        foreach ($rows as $row) {
            $html .= '<div class="menu-item"><div class="menu-item-head"><h4>' . e((string) $row['name']) . '</h4><span class="menu-dots"></span><strong>' . e(money((float) $row['price'], (string) ($business['currency'] ?? 'NGN'))) . '</strong></div>';
            if (!empty($row['description'])) {
                $html .= '<p>' . e((string) $row['description']) . '</p>';
            }
            if (!empty($row['dietary'])) {
                $html .= '<span class="menu-tag">' . e((string) $row['dietary']) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';
    }
    return $html . '</div></section>';
}

function site_render_products(array $content, array $business, bool $preview): string
{
    $limit = max(1, min(48, (int) site_value($content, 'limit', '12')));
    $rows = db_all(
        "SELECT * FROM products WHERE business_id = ? AND deleted_at IS NULL AND status = 'active' ORDER BY featured DESC, created_at DESC LIMIT $limit",
        [$business['id']]
    );
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add products from your dashboard to populate your shop.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="card-grid">';
    foreach ($rows as $row) {
        $html .= site_product_card($row, $business);
    }
    return $html . '</div></div></section>';
}

function site_render_rooms(array $content, array $business, bool $preview): string
{
    $rows = db_all("SELECT * FROM rooms WHERE business_id = ? AND status = 'available' ORDER BY price ASC", [$business['id']]);
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add rooms from your dashboard to show availability.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section site-section-tint"><div class="site-container">' . site_heading($content) . '<div class="card-grid">';
    foreach ($rows as $row) {
        $image = site_image_url($row['image'] ?? '');
        $html .= '<article class="product-card"><div class="product-media">' . ($image !== '' ? '<img src="' . e($image) . '" alt="' . e((string) $row['name']) . '" loading="lazy">' : '<div class="product-placeholder"><span>' . e(initials((string) $row['name'])) . '</span></div>') . '</div><div class="product-body"><h3>' . e((string) $row['name']) . '</h3><p>' . e(str_limit((string) ($row['room_type'] ?? $row['description'] ?? ''), 120)) . '</p><div class="product-foot"><strong>' . e(money((float) $row['price'], (string) ($business['currency'] ?? 'NGN'))) . ' / night</strong><a class="text-link" href="#booking">Book</a></div><span class="small-note">Sleeps ' . (int) $row['capacity'] . (!empty($row['beds']) ? ' · ' . e((string) $row['beds']) : '') . '</span></div></article>';
    }
    return $html . '</div></div></section>';
}

function site_render_properties(array $content, array $business, bool $preview): string
{
    $rows = db_all("SELECT * FROM properties WHERE business_id = ? AND status = 'available' ORDER BY featured DESC, created_at DESC LIMIT 9", [$business['id']]);
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add property listings to show them here.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="card-grid">';
    foreach ($rows as $row) {
        $images = json_decode_assoc($row['images'] ?? null);
        $image = site_image_url((string) ($images[0] ?? ''));
        $html .= '<article class="product-card"><div class="product-media">' . ($image !== '' ? '<img src="' . e($image) . '" alt="' . e((string) $row['title']) . '" loading="lazy">' : '<div class="product-placeholder"><span>' . e(initials((string) $row['title'])) . '</span></div>') . '</div><div class="product-body"><span class="tag">For ' . e((string) $row['listing_type']) . '</span><h3>' . e((string) $row['title']) . '</h3><p>' . e(trim(($row['address'] ?? '') . ' ' . ($row['city'] ?? ''))) . '</p><div class="product-foot"><strong>' . e(money((float) $row['price'], (string) ($business['currency'] ?? 'NGN'))) . '</strong><a class="text-link" href="#contact">Enquire</a></div><span class="small-note">' . (int) $row['bedrooms'] . ' beds · ' . (int) $row['bathrooms'] . ' baths' . (!empty($row['area']) ? ' · ' . e((string) $row['area']) : '') . '</span></div></article>';
    }
    return $html . '</div></div></section>';
}

function site_render_courses(array $content, array $business, bool $preview): string
{
    $rows = db_all("SELECT * FROM courses WHERE business_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 9", [$business['id']]);
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add courses to show your programmes here.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="card-grid">';
    foreach ($rows as $row) {
        $html .= '<article class="product-card"><div class="product-body"><span class="tag">Course</span><h3>' . e((string) $row['title']) . '</h3><p>' . e(str_limit((string) ($row['description'] ?? ''), 150)) . '</p><div class="product-foot"><strong>' . e(money((float) $row['fee'], (string) ($business['currency'] ?? 'NGN'))) . '</strong><a class="text-link" href="#contact">Enrol</a></div>' . (!empty($row['duration']) ? '<span class="small-note">' . e((string) $row['duration']) . (!empty($row['schedule']) ? ' · ' . e((string) $row['schedule']) : '') . '</span>' : '') . '</div></article>';
    }
    return $html . '</div></div></section>';
}

function site_render_events(array $content, array $business, bool $preview): string
{
    $rows = db_all("SELECT * FROM events WHERE business_id = ? AND status IN ('upcoming','ongoing') ORDER BY starts_at ASC LIMIT 8", [$business['id']]);
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add upcoming events to show them here.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section site-section-tint"><div class="site-container">' . site_heading($content) . '<div class="event-list">';
    foreach ($rows as $row) {
        $html .= '<article class="event-row"><div class="event-date"><strong>' . e(date('d', strtotime($row['starts_at']))) . '</strong><span>' . e(date('M', strtotime($row['starts_at']))) . '</span></div><div><h3>' . e((string) $row['title']) . '</h3><p>' . e(str_limit((string) ($row['description'] ?? ''), 180)) . '</p><span class="small-note">' . e(date('g:i A', strtotime($row['starts_at']))) . (!empty($row['location']) ? ' · ' . e((string) $row['location']) : '') . '</span></div></article>';
    }
    return $html . '</div></div></section>';
}

function site_render_donations(array $content, array $business, bool $preview): string
{
    $heading = site_value($content, 'heading', 'Support our work');
    $subheading = site_value($content, 'subheading');
    $button = site_value($content, 'button_text', 'Donate now');
    $link = site_whatsapp_url($business, 'Hello, I would like to support your work.') ?: site_page_url($business, 'contact', $preview);
    return '<section class="site-section"><div class="site-container"><div class="donation-panel"><div><h2>' . e($heading) . '</h2>' . ($subheading !== '' ? '<p>' . nl2br(e($subheading)) . '</p>' : '') . '</div><a class="site-btn site-btn-primary" href="' . e($link) . '">' . e($button) . '</a></div></div></section>';
}

function site_render_announcements(array $content, array $business, bool $preview): string
{
    $limit = max(1, min(12, (int) site_value($content, 'limit', '3')));
    $rows = db_all("SELECT * FROM announcements WHERE business_id = ? AND audience = 'public' AND (published_at IS NULL OR published_at <= NOW()) ORDER BY COALESCE(published_at, created_at) DESC LIMIT $limit", [$business['id']]);
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Publish an announcement to show it here.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="announcement-list">';
    foreach ($rows as $row) {
        $html .= '<article class="announcement"><span class="small-note">' . e(nice_date($row['published_at'] ?? $row['created_at'])) . '</span><h3>' . e((string) $row['title']) . '</h3><p>' . nl2br(e((string) $row['body'])) . '</p></article>';
    }
    return $html . '</div></div></section>';
}

function site_render_gallery(array $content, bool $preview): string
{
    $images = is_array($content['images'] ?? null) ? $content['images'] : [];
    $valid = [];
    foreach ($images as $image) {
        if (is_array($image) && !empty($image['image'])) {
            $valid[] = $image;
        }
    }
    if (!$valid) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add gallery images in the website editor.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="gallery-grid">';
    foreach ($valid as $image) {
        $html .= '<figure><img src="' . e(media_url((string) $image['image'])) . '" alt="' . e((string) ($image['caption'] ?? 'Gallery image')) . '" loading="lazy">';
        if (!empty($image['caption'])) {
            $html .= '<figcaption>' . e((string) $image['caption']) . '</figcaption>';
        }
        $html .= '</figure>';
    }
    return $html . '</div></div></section>';
}

function site_render_testimonials(array $content, array $business, bool $preview): string
{
    $source = site_value($content, 'source', 'reviews');
    $items = [];
    if ($source === 'custom') {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
    } else {
        $reviews = db_all("SELECT * FROM reviews WHERE business_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 9", [$business['id']]);
        foreach ($reviews as $review) {
            $items[] = ['name' => $review['author_name'], 'role' => '', 'quote' => trim(($review['title'] ? $review['title'] . ' — ' : '') . (string) $review['body']), 'rating' => (int) $review['rating']];
        }
    }
    if (!$items) {
        return '<section class="site-section site-section-tint"><div class="site-container">' . site_heading($content) . site_empty_note('Approved reviews will appear here. You can also add custom testimonials.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section site-section-tint"><div class="site-container">' . site_heading($content) . '<div class="testimonial-grid">';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $rating = (int) ($item['rating'] ?? 5);
        $html .= '<blockquote class="testimonial"><div class="stars" aria-label="' . $rating . ' out of 5 stars">' . str_repeat('★', max(1, min(5, $rating))) . '</div><p>“' . nl2br(e((string) ($item['quote'] ?? ''))) . '”</p><footer><strong>' . e((string) ($item['name'] ?? 'Customer')) . '</strong>' . (!empty($item['role']) ? '<span>' . e((string) $item['role']) . '</span>' : '') . '</footer></blockquote>';
    }
    return $html . '</div></div></section>';
}

function site_render_hours(array $content, array $business): string
{
    $hours = json_decode_assoc($business['opening_hours'] ?? null);
    $html = '<section class="site-section" id="hours"><div class="site-container narrow">' . site_heading($content);
    if ($hours) {
        $html .= '<dl class="hours-list">';
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            $value = $hours[$day] ?? $hours[strtolower($day)] ?? null;
            if (is_array($value)) {
                $display = !empty($value['closed']) ? 'Closed' : trim((string) ($value['open'] ?? '') . ' – ' . (string) ($value['close'] ?? ''));
            } else {
                $display = trim((string) $value);
            }
            $html .= '<div><dt>' . e($day) . '</dt><dd>' . e($display !== '' ? $display : 'Contact us') . '</dd></div>';
        }
        $html .= '</dl>';
    } else {
        $html .= '<p class="site-empty">Please contact us for current opening hours.</p>';
    }
    if (site_value($content, 'note') !== '') {
        $html .= '<p class="small-note">' . nl2br(e(site_value($content, 'note'))) . '</p>';
    }
    return $html . '</div></section>';
}

function site_contact_details(array $business): string
{
    $rows = [];
    if (!empty($business['address']) || !empty($business['city'])) {
        $rows[] = ['map-pin', trim(($business['address'] ?? '') . ', ' . ($business['city'] ?? '') . ' ' . ($business['state'] ?? ''), ', ')];
    }
    if (!empty($business['phone'])) {
        $rows[] = ['phone', (string) $business['phone'], 'tel:' . preg_replace('/[^\d+]/', '', (string) $business['phone'])];
    }
    if (!empty($business['email'])) {
        $rows[] = ['mail', (string) $business['email'], 'mailto:' . $business['email']];
    }
    $html = '<div class="contact-details">';
    foreach ($rows as $row) {
        $html .= '<div class="contact-line"><span>' . icon($row[0]) . '</span><div>';
        if (!empty($row[2])) {
            $html .= '<a href="' . e($row[2]) . '">' . e($row[1]) . '</a>';
        } else {
            $html .= '<span>' . e($row[1]) . '</span>';
        }
        $html .= '</div></div>';
    }
    if (site_whatsapp_url($business) !== '') {
        $html .= '<a class="site-btn site-btn-whatsapp" href="' . e(site_whatsapp_url($business, 'Hello ' . $business['name'] . '!')) . '" target="_blank" rel="noopener">Chat on WhatsApp</a>';
    }
    return $html . '</div>';
}

function site_contact_form(array $business, bool $preview, array $context): string
{
    $errors = $context['form_type'] === 'contact' ? ($context['form_errors'] ?? []) : [];
    $old = $context['form_type'] === 'contact' ? ($context['form_old'] ?? []) : [];
    $html = '<form class="site-form" method="post" action="' . e($context['form_action']) . '" novalidate>';
    $html .= csrf_field();
    $html .= '<input type="hidden" name="site_form" value="contact">';
    $html .= '<div class="form-trap" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    if (!empty($context['form_success']) && $context['form_type'] === 'contact') {
        $html .= '<div class="site-alert site-alert-success">Thank you — your message has been sent. We will get back to you shortly.</div>';
    }
    if ($errors) {
        $html .= '<div class="site-alert site-alert-error">Please check the highlighted fields and try again.</div>';
    }
    $html .= '<div class="form-row"><label>Your name<input type="text" name="name" value="' . e((string) ($old['name'] ?? '')) . '" required>' . (isset($errors['name']) ? '<span class="field-error">' . e($errors['name']) . '</span>' : '') . '</label>';
    $html .= '<label>Email<input type="email" name="email" value="' . e((string) ($old['email'] ?? '')) . '" required>' . (isset($errors['email']) ? '<span class="field-error">' . e($errors['email']) . '</span>' : '') . '</label></div>';
    $html .= '<div class="form-row"><label>Phone<input type="tel" name="phone" value="' . e((string) ($old['phone'] ?? '')) . '"></label><label>Subject<input type="text" name="subject" value="' . e((string) ($old['subject'] ?? '')) . '"></label></div>';
    $html .= '<label>Message<textarea name="message" rows="5" required>' . e((string) ($old['message'] ?? '')) . '</textarea>' . (isset($errors['message']) ? '<span class="field-error">' . e($errors['message']) . '</span>' : '') . '</label>';
    $html .= '<button class="site-btn site-btn-primary" type="submit">Send message</button></form>';
    return $html;
}

function site_render_contact(array $content, array $business, bool $preview, array $context): string
{
    $html = '<section class="site-section" id="contact"><div class="site-container contact-grid"><div>';
    $html .= site_heading($content);
    if (site_value($content, 'body') !== '') {
        $html .= '<div class="prose">' . nl2br(e(site_value($content, 'body'))) . '</div>';
    }
    $html .= site_contact_details($business) . '</div><div>';
    if (site_value($content, 'show_form', '1') === '1') {
        $html .= site_contact_form($business, $preview, $context);
    }
    return $html . '</div></div></section>';
}

function site_render_map(array $content, array $business): string
{
    $address = site_value($content, 'address') ?: trim(($business['address'] ?? '') . ', ' . ($business['city'] ?? '') . ' ' . ($business['state'] ?? ''), ', ');
    $embed = site_value($content, 'embed_url');
    $html = '<section class="site-section site-section-tint" id="map"><div class="site-container">' . site_heading($content);
    if ($embed !== '' && preg_match('#^https://(www\.)?(google\.[a-z.]+/maps|maps\.google\.[a-z.]+)#i', $embed)) {
        $html .= '<div class="map-frame"><iframe src="' . e($embed) . '" title="Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
    } elseif ($address !== '') {
        $html .= '<div class="map-card"><div><strong>' . e($address) . '</strong><p class="small-note">Open the location in Google Maps for directions.</p></div><a class="site-btn site-btn-primary" href="https://www.google.com/maps/search/?api=1&amp;query=' . rawurlencode($address) . '" target="_blank" rel="noopener">Get directions</a></div>';
    } else {
        $html .= '<p class="site-empty">Add your business address in Settings to show a map.</p>';
    }
    return $html . '</div></section>';
}

function site_render_cta(array $content, array $business, bool $preview): string
{
    $link = site_smart_link(site_value($content, 'button_link', 'contact'), $business, $preview);
    return '<section class="site-cta"><div class="site-container cta-inner"><div><h2>' . e(site_value($content, 'heading', 'Ready to get started?')) . '</h2>' . (site_value($content, 'body') !== '' ? '<p>' . nl2br(e(site_value($content, 'body'))) . '</p>' : '') . '</div><a class="site-btn site-btn-light" href="' . e($link) . '">' . e(site_value($content, 'button_text', 'Contact us')) . '</a></div></section>';
}

function site_render_faq(array $content, bool $preview): string
{
    $items = is_array($content['items'] ?? null) ? $content['items'] : [];
    if (!$items) {
        return '<section class="site-section"><div class="site-container narrow">' . site_heading($content) . site_empty_note('Add questions and answers in the website editor.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container narrow">' . site_heading($content) . '<div class="faq-list">';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $html .= '<details><summary>' . e((string) ($item['question'] ?? '')) . '</summary><p>' . nl2br(e((string) ($item['answer'] ?? ''))) . '</p></details>';
    }
    return $html . '</div></div></section>';
}

function site_render_booking(array $content, array $business, bool $preview, array $context): string
{
    $errors = $context['form_type'] === 'booking' ? ($context['form_errors'] ?? []) : [];
    $old = $context['form_type'] === 'booking' ? ($context['form_old'] ?? []) : [];
    $partyLabel = site_value($content, 'party_label', 'Number of guests');
    $html = '<section class="site-section site-section-tint" id="booking"><div class="site-container narrow">' . site_heading($content);
    if (!empty($context['form_success']) && $context['form_type'] === 'booking') {
        $html .= '<div class="site-alert site-alert-success">Your booking request has been received. We will confirm it shortly.</div>';
    }
    if ($errors) {
        $html .= '<div class="site-alert site-alert-error">Please check the highlighted fields and try again.</div>';
    }
    $html .= '<form class="site-form" method="post" action="' . e($context['form_action']) . '" novalidate>';
    $html .= csrf_field();
    $html .= '<input type="hidden" name="site_form" value="booking">';
    $html .= '<div class="form-trap" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    $html .= '<div class="form-row"><label>Your name<input type="text" name="name" value="' . e((string) ($old['name'] ?? '')) . '" required>' . (isset($errors['name']) ? '<span class="field-error">' . e($errors['name']) . '</span>' : '') . '</label>';
    $html .= '<label>Email<input type="email" name="email" value="' . e((string) ($old['email'] ?? '')) . '" required>' . (isset($errors['email']) ? '<span class="field-error">' . e($errors['email']) . '</span>' : '') . '</label></div>';
    $html .= '<div class="form-row"><label>Phone<input type="tel" name="phone" value="' . e((string) ($old['phone'] ?? '')) . '"></label>';
    $html .= '<label>' . e($partyLabel) . '<input type="number" min="1" max="500" name="party_size" value="' . e((string) ($old['party_size'] ?? '2')) . '"></label></div>';
    $html .= '<div class="form-row"><label>Date<input type="date" name="booking_date" min="' . e(date('Y-m-d')) . '" value="' . e((string) ($old['booking_date'] ?? '')) . '" required>' . (isset($errors['booking_date']) ? '<span class="field-error">' . e($errors['booking_date']) . '</span>' : '') . '</label>';
    $html .= '<label>Time<input type="time" name="start_time" value="' . e((string) ($old['start_time'] ?? '')) . '"></label></div>';
    $html .= '<label>Notes<textarea name="notes" rows="3" placeholder="Anything we should know?">' . e((string) ($old['notes'] ?? '')) . '</textarea></label>';
    $html .= '<button class="site-btn site-btn-primary" type="submit">' . e(site_value($content, 'button_text', 'Request booking')) . '</button></form>';
    return $html . '</div></section>';
}

function site_render_team(array $content, array $business, bool $preview): string
{
    $rows = db_all("SELECT * FROM staff WHERE business_id = ? AND status = 'active' ORDER BY name ASC LIMIT 12", [$business['id']]);
    if (!$rows) {
        return '<section class="site-section"><div class="site-container">' . site_heading($content) . site_empty_note('Add team members from the Team page.', $preview) . '</div></section>';
    }
    $html = '<section class="site-section"><div class="site-container">' . site_heading($content) . '<div class="team-grid">';
    foreach ($rows as $row) {
        $html .= '<article class="team-card">';
        if (!empty($row['photo'])) {
            $html .= '<img src="' . e(media_url((string) $row['photo'])) . '" alt="' . e((string) $row['name']) . '" loading="lazy">';
        } else {
            $html .= '<div class="team-placeholder">' . e(initials((string) $row['name'])) . '</div>';
        }
        $html .= '<h3>' . e((string) $row['name']) . '</h3>' . (!empty($row['role_title']) ? '<p class="small-note">' . e((string) $row['role_title']) . '</p>' : '');
        if (!empty($row['bio'])) {
            $html .= '<p>' . e(str_limit((string) $row['bio'], 140)) . '</p>';
        }
        $html .= '</article>';
    }
    return $html . '</div></div></section>';
}

function site_render_stats(array $content, array $business): string
{
    $offers = (int) db_val("SELECT COUNT(*) FROM products WHERE business_id = ? AND deleted_at IS NULL AND status = 'active'", [$business['id']]);
    $reviews = (int) db_val("SELECT COUNT(*) FROM reviews WHERE business_id = ? AND status = 'approved'", [$business['id']]);
    $team = (int) db_val("SELECT COUNT(*) FROM staff WHERE business_id = ? AND status = 'active'", [$business['id']]);
    $html = '<section class="site-section site-section-tint"><div class="site-container">' . site_heading($content) . '<div class="stat-strip">';
    $html .= '<div><strong>' . number_format($offers) . '</strong><span>Products &amp; services</span></div>';
    $html .= '<div><strong>' . number_format($reviews) . '</strong><span>Approved reviews</span></div>';
    $html .= '<div><strong>' . number_format($team) . '</strong><span>Team members</span></div>';
    return $html . '</div></div></section>';
}

function site_render_section(array $section, array $business, array $website, array $context): string
{
    $content = site_content($section);
    $preview = !empty($context['preview']);
    $type = (string) $section['type'];
    return match ($type) {
        'hero' => site_render_hero($content, $business, $website, $preview),
        'page_header' => site_render_page_header($content, $business),
        'about' => site_render_about($content, $business),
        'features' => site_render_features($content, $preview),
        'services' => site_render_services($content, $business, $preview),
        'menu' => site_render_menu($content, $business, $preview),
        'products' => site_render_products($content, $business, $preview),
        'rooms' => site_render_rooms($content, $business, $preview),
        'properties' => site_render_properties($content, $business, $preview),
        'courses' => site_render_courses($content, $business, $preview),
        'events' => site_render_events($content, $business, $preview),
        'donations' => site_render_donations($content, $business, $preview),
        'announcements' => site_render_announcements($content, $business, $preview),
        'gallery' => site_render_gallery($content, $preview),
        'testimonials' => site_render_testimonials($content, $business, $preview),
        'hours' => site_render_hours($content, $business),
        'contact' => site_render_contact($content, $business, $preview, $context),
        'map' => site_render_map($content, $business),
        'cta' => site_render_cta($content, $business, $preview),
        'faq' => site_render_faq($content, $preview),
        'booking' => site_render_booking($content, $business, $preview, $context),
        'team' => site_render_team($content, $business, $preview),
        'stats' => site_render_stats($content, $business),
        default => '',
    };
}
