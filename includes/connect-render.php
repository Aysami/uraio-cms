<?php
/**
 * Uraio Connect widget renderer. Public, self-contained HTML fragments for iframes.
 * No business credentials or internal data are ever exposed.
 */

function connect_money(float|int|string $amount, string $currency = 'NGN'): string
{
    return money($amount, $currency ?: 'NGN');
}

function connect_menu_html(array $business): string
{
    $items = db_all(
        "SELECT * FROM menu_items WHERE business_id = ? AND status = 'active' ORDER BY featured DESC, name ASC LIMIT 60",
        [$business['id']]
    );
    if (!$items) {
        return '<div class="empty">Menu coming soon.</div>';
    }
    $groups = [];
    $catRows = db_all(
        'SELECT id, name FROM product_categories WHERE id IN (SELECT DISTINCT category_id FROM menu_items WHERE business_id = ?)',
        [$business['id']]
    );
    $catNames = [];
    foreach ($catRows as $row) {
        $catNames[(int) $row['id']] = $row['name'];
    }
    foreach ($items as $item) {
        $key = $item['category_id'] && isset($catNames[(int) $item['category_id']]) ? $catNames[(int) $item['category_id']] : 'Menu';
        $groups[$key][] = $item;
    }
    $out = '';
    foreach ($groups as $groupName => $groupItems) {
        if (!is_numeric($groupName) && $groupName !== 'Menu') {
            $out .= '<h3 class="wgt-group">' . e($groupName) . '</h3>';
        }
        $out .= '<div class="wgt-items">';
        foreach ($groupItems as $item) {
            $price = (float) $item['price'];
            $out .= '<div class="wgt-item">';
            if (!empty($item['image'])) {
                $out .= '<img class="wgt-thumb" src="' . e(media_url($item['image'])) . '" alt="" loading="lazy">';
            }
            $out .= '<div class="wgt-item-body">';
            $out .= '<div class="wgt-item-head"><span class="wgt-item-name">' . e($item['name']) . '</span>';
            $out .= '<span class="wgt-item-price">' . e(connect_money($price, (string) ($business['currency'] ?? 'NGN'))) . '</span></div>';
            if (!empty($item['dietary'])) {
                $out .= '<div class="wgt-item-meta">' . e($item['dietary']) . '</div>';
            }
            if (!empty($item['description'])) {
                $out .= '<p class="wgt-item-desc">' . e(str_limit((string) $item['description'], 140)) . '</p>';
            }
            $out .= '</div></div>';
        }
        $out .= '</div>';
    }
    return $out;
}

function connect_products_html(array $business): string
{
    $items = db_all(
        "SELECT * FROM products WHERE business_id = ? AND status = 'active' AND deleted_at IS NULL ORDER BY featured DESC, name ASC LIMIT 12",
        [$business['id']]
    );
    if (!$items) {
        return '<div class="empty">Products coming soon.</div>';
    }
    $out = '<div class="wgt-products">';
    foreach ($items as $item) {
        $price = (float) $item['price'];
        $out .= '<div class="wgt-product">';
        if (!empty($item['image'])) {
            $out .= '<img class="wgt-thumb" src="' . e(media_url($item['image'])) . '" alt="" loading="lazy">';
        }
        $out .= '<div class="wgt-item-body"><div class="wgt-item-head"><span class="wgt-item-name">' . e($item['name']) . '</span>';
        $out .= '<span class="wgt-item-price">' . e(connect_money($price, (string) ($business['currency'] ?? 'NGN'))) . '</span></div></div></div>';
    }
    return $out . '</div>';
}

function connect_hours_html(array $business): string
{
    $hours = json_decode_assoc($business['opening_hours'] ?? null);
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    if (!$hours) {
        return '<div class="empty">Opening hours not published yet.</div>';
    }
    $out = '<ul class="wgt-hours">';
    foreach ($days as $day) {
        $value = $hours[$day] ?? null;
        $closed = !empty($value['closed']);
        $open = $closed ? '' : trim((string) ($value['open'] ?? ''));
        $close = $closed ? '' : trim((string) ($value['close'] ?? ''));
        $label = $closed ? 'Closed' : ($open !== '' && $close !== '' ? $open . ' – ' . $close : 'Open');
        $out .= '<li><span>' . e($day) . '</span><span class="wgt-hours-value' . ($closed ? ' is-closed' : '') . '">' . e($label) . '</span></li>';
    }
    $out .= '</ul>';
    if (!empty($business['address'])) {
        $out .= '<div class="wgt-address">' . icon('map-pin', 'icon icon-sm') . ' ' . e($business['address']) . (!empty($business['city']) ? ', ' . e($business['city']) : '') . '</div>';
    }
    return $out;
}

function connect_reviews_html(array $business): string
{
    $reviews = db_all(
        "SELECT * FROM reviews WHERE business_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 6",
        [$business['id']]
    );
    if (!$reviews) {
        return '<div class="empty">No reviews yet.</div>';
    }
    $out = '<div class="wgt-reviews">';
    foreach ($reviews as $review) {
        $rating = max(1, min(5, (int) $review['rating']));
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $out .= '<figure class="wgt-review"><div class="wgt-stars">' . e($stars) . '</div>';
        if (!empty($review['title'])) {
            $out .= '<figcaption><strong>' . e($review['title']) . '</strong>' . ($review['body'] ? ' — ' . e(str_limit((string) $review['body'], 160)) : '') . '</figcaption>';
        } elseif (!empty($review['body'])) {
            $out .= '<figcaption>' . e(str_limit((string) $review['body'], 200)) . '</figcaption>';
        }
        $out .= '<div class="wgt-review-author">' . e($review['author_name']) . '</div></figure>';
    }
    return $out . '</div>';
}

function connect_contact_form_html(array $business, ?string $sent): string
{
    if ($sent === 'contact') {
        return '<div class="wgt-success" role="status"><h3>Message sent</h3><p>' . e($business['name']) . ' will get back to you soon.</p></div>';
    }
    $token = urlencode((string) $business['embed_token']);
    return '<form class="wgt-form" method="post" action="' . e(app_url('connect/submit.php?t=' . $token . '&w=contact')) . '">'
        . '<input type="hidden" name="site_source" value="embed">'
        . '<label class="hp-field" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
        . '<label>Name<input type="text" name="name" required maxlength="190" placeholder="Your name"></label>'
        . '<label>Email<input type="email" name="email" maxlength="190" placeholder="you@example.com"></label>'
        . '<label>Phone<input type="tel" name="phone" maxlength="40" placeholder="+234…"></label>'
        . '<label>Message<textarea name="message" required maxlength="2000" rows="4" placeholder="How can ' . e($business['name']) . ' help?"></textarea></label>'
        . '<button type="submit">Send message</button>'
        . '</form>';
}

function connect_booking_form_html(array $business, ?string $sent): string
{
    if ($sent === 'booking') {
        return '<div class="wgt-success" role="status"><h3>Booking request received</h3><p>' . e($business['name']) . ' will confirm your request shortly.</p></div>';
    }
    $token = urlencode((string) $business['embed_token']);
    $today = date('Y-m-d');
    return '<form class="wgt-form" method="post" action="' . e(app_url('connect/submit.php?t=' . $token . '&w=booking')) . '">'
        . '<input type="hidden" name="site_source" value="embed">'
        . '<label class="hp-field" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
        . '<label>Name<input type="text" name="name" required maxlength="190" placeholder="Your name"></label>'
        . '<label>Email<input type="email" name="email" maxlength="190" placeholder="you@example.com"></label>'
        . '<label>Phone<input type="tel" name="phone" maxlength="40" placeholder="+234…"></label>'
        . '<label>Preferred date<input type="date" name="booking_date" required min="' . e($today) . '"></label>'
        . '<label>Party size<input type="number" name="party_size" min="1" max="100" value="2"></label>'
        . '<label>Notes<textarea name="notes" maxlength="1000" rows="3" placeholder="Anything we should know?"></textarea></label>'
        . '<button type="submit">Request booking</button>'
        . '</form>';
}

function connect_whatsapp_html(array $business): string
{
    if (empty($business['whatsapp'])) {
        return '<div class="empty">WhatsApp not set for this business.</div>';
    }
    $number = preg_replace('/[^0-9+]/', '', (string) $business['whatsapp']);
    $number = ltrim((string) $number, '+');
    $prefilled = rawurlencode('Hello, I found you via your website.');
    return '<a class="wgt-whatsapp" href="https://wa.me/' . e($number) . '?text=' . e($prefilled) . '" target="_blank" rel="noopener">'
        . icon('phone', 'icon icon-sm') . ' Chat on WhatsApp</a>';
}

function connect_widget_html(array $business, string $widget, ?string $sent = null): string
{
    switch ($widget) {
        case 'menu':    return connect_menu_html($business);
        case 'products': return connect_products_html($business);
        case 'hours':   return connect_hours_html($business);
        case 'contact': return connect_contact_form_html($business, $sent);
        case 'booking': return connect_booking_form_html($business, $sent);
        case 'reviews': return connect_reviews_html($business);
        case 'whatsapp': return connect_whatsapp_html($business);
        default:
            return '<div class="empty">Unknown widget.</div>';
    }
}

function connect_widget_title(string $widget): string
{
    return [
        'menu' => 'Menu',
        'products' => 'Products',
        'hours' => 'Opening hours',
        'contact' => 'Contact',
        'booking' => 'Book now',
        'reviews' => 'Reviews',
        'whatsapp' => 'WhatsApp',
    ][$widget] ?? 'Widget';
}
