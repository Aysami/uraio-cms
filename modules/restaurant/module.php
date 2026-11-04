<?php
return [
    'slug' => 'restaurant',
    'name' => 'Restaurant & Food',
    'icon' => 'utensils',
    'description' => 'Menu, reservations, kitchen orders and restaurant analytics.',
    'nav' => [
        ['label' => 'Menu', 'url' => 'app/records.php?entity=menu_items', 'icon' => 'book', 'entity' => 'menu_items'],
        ['label' => 'Reservations', 'url' => 'app/records.php?entity=reservations', 'icon' => 'calendar', 'entity' => 'reservations'],
        ['label' => 'Kitchen orders', 'url' => 'app/records.php?entity=orders&status=pending', 'icon' => 'utensils', 'entity' => 'orders'],
    ],
    'entities' => [
        'menu_items' => [
            'table' => 'menu_items', 'label' => 'Menu item', 'plural' => 'Menu', 'icon' => 'utensils',
            'search' => ['name', 'description', 'dietary'], 'order' => 'id DESC', 'upload_folder' => 'products',
            'fields' => [
                'name' => ['label' => 'Item name', 'type' => 'text', 'required' => true, 'col' => 8],
                'price' => ['label' => 'Price', 'type' => 'money', 'required' => true, 'col' => 4],
                'category_id' => ['label' => 'Menu category', 'type' => 'select', 'options_from' => ['table' => 'product_categories', 'label' => 'name'], 'col' => 6],
                'dietary' => ['label' => 'Dietary notes', 'type' => 'text', 'col' => 6, 'help' => 'e.g. Vegetarian, contains nuts'],
                'prep_time' => ['label' => 'Prep time (minutes)', 'type' => 'integer', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Available', 'inactive' => 'Unavailable'], 'default' => 'active', 'col' => 4],
                'featured' => ['label' => 'Chef special', 'type' => 'checkbox', 'col' => 4],
                'image' => ['label' => 'Photo', 'type' => 'image', 'folder' => 'products', 'col' => 6],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['name', 'price', 'dietary', 'status'],
        ],
        'reservations' => [
            'table' => 'bookings', 'label' => 'Reservation', 'plural' => 'Reservations', 'icon' => 'calendar',
            'search' => ['reference', 'customer_name', 'customer_phone'], 'order' => 'booking_date DESC, id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('RES');
                }
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'customer_name' => ['label' => 'Guest name', 'type' => 'text', 'required' => true, 'col' => 4],
                'customer_phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 4],
                'customer_email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'booking_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 3],
                'start_time' => ['label' => 'Time', 'type' => 'time', 'col' => 3],
                'party_size' => ['label' => 'Guests', 'type' => 'integer', 'col' => 3],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Seated', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'], 'default' => 'pending', 'col' => 3],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'customer_name', 'booking_date', 'start_time', 'party_size', 'status'],
        ],
    ],
    'widgets' => [
        ['label' => 'Menu items', 'entity' => 'menu_items', 'metric' => 'count', 'icon' => 'book'],
        ['label' => 'Reservations today', 'table' => 'bookings', 'metric' => 'count', 'where_raw' => 'booking_date = CURDATE()', 'icon' => 'calendar'],
        ['label' => 'Pending orders', 'entity' => 'orders', 'metric' => 'count', 'where' => ['status' => 'pending'], 'icon' => 'utensils'],
    ],
];
