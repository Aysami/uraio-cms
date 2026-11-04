<?php
return [
    'slug' => 'universal',
    'name' => 'Universal Business',
    'icon' => 'grid',
    'description' => 'Customers, products, orders, bookings, invoices, staff, reviews and messages for every business.',
    'nav' => [
        ['label' => 'Products & services', 'url' => 'app/records.php?entity=products', 'icon' => 'shopping-bag', 'entity' => 'products'],
        ['label' => 'Orders', 'url' => 'app/records.php?entity=orders', 'icon' => 'shopping-cart', 'entity' => 'orders'],
        ['label' => 'Bookings', 'url' => 'app/records.php?entity=bookings', 'icon' => 'calendar', 'entity' => 'bookings'],
    ],
    'entities' => [
        'customers' => [
            'table' => 'customers', 'label' => 'Customer', 'plural' => 'Customers', 'icon' => 'users', 'soft_delete' => true,
            'search' => ['name', 'email', 'phone'], 'order' => 'id DESC',
            'fields' => [
                'name' => ['label' => 'Full name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'whatsapp' => ['label' => 'WhatsApp', 'type' => 'phone', 'col' => 6],
                'address' => ['label' => 'Address', 'type' => 'text', 'col' => 12],
                'tags' => ['label' => 'Tags', 'type' => 'tags', 'col' => 6, 'help' => 'Comma separated, e.g. VIP, wholesale'],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked'], 'default' => 'active', 'col' => 6],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['name', 'email', 'phone', 'status', 'created_at'],
        ],
        'products' => [
            'table' => 'products', 'label' => 'Product or service', 'plural' => 'Products & services', 'icon' => 'shopping-bag',
            'soft_delete' => true, 'search' => ['name', 'sku', 'description'], 'order' => 'id DESC', 'upload_folder' => 'products',
            'slug_from' => 'name',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 8],
                'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['product' => 'Product', 'service' => 'Service', 'menu' => 'Menu item'], 'default' => 'product', 'col' => 4],
                'category_id' => ['label' => 'Category', 'type' => 'select', 'options_from' => ['table' => 'product_categories', 'label' => 'name'], 'col' => 6],
                'sku' => ['label' => 'SKU', 'type' => 'text', 'col' => 6],
                'price' => ['label' => 'Price', 'type' => 'money', 'required' => true, 'col' => 4],
                'compare_price' => ['label' => 'Compare price', 'type' => 'money', 'col' => 4],
                'stock' => ['label' => 'Stock', 'type' => 'integer', 'col' => 4],
                'image' => ['label' => 'Image', 'type' => 'image', 'folder' => 'products', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft'], 'default' => 'active', 'col' => 6],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
                'featured' => ['label' => 'Featured on website', 'type' => 'checkbox', 'col' => 12],
            ],
            'list' => ['name', 'type', 'price', 'stock', 'status'],
        ],
        'orders' => [
            'table' => 'orders', 'label' => 'Order', 'plural' => 'Orders', 'icon' => 'shopping-cart',
            'search' => ['number', 'notes'], 'order' => 'id DESC', 'per_page' => 25,
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['number'])) {
                    $data['number'] = generate_reference('ORD');
                }
                $data['total'] = round((float) ($data['subtotal'] ?? 0) + (float) ($data['tax'] ?? 0) - (float) ($data['discount'] ?? 0), 2);
                return $data;
            },
            'fields' => [
                'number' => ['label' => 'Order number', 'type' => 'text', 'col' => 4],
                'customer_id' => ['label' => 'Customer', 'type' => 'select', 'options_from' => ['table' => 'customers', 'label' => 'name'], 'col' => 4],
                'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['order' => 'Order', 'booking' => 'Booking', 'reservation' => 'Reservation', 'request' => 'Request'], 'default' => 'order', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'], 'default' => 'pending', 'col' => 4],
                'payment_status' => ['label' => 'Payment', 'type' => 'select', 'options' => ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'partial' => 'Partial', 'refunded' => 'Refunded'], 'default' => 'unpaid', 'col' => 4],
                'channel' => ['label' => 'Channel', 'type' => 'select', 'options' => ['dashboard' => 'Dashboard', 'website' => 'Website', 'whatsapp' => 'WhatsApp', 'walk_in' => 'Walk-in', 'phone' => 'Phone'], 'default' => 'dashboard', 'col' => 4],
                'subtotal' => ['label' => 'Subtotal', 'type' => 'money', 'col' => 4],
                'tax' => ['label' => 'Tax', 'type' => 'money', 'col' => 4],
                'discount' => ['label' => 'Discount', 'type' => 'money', 'col' => 4],
                'total' => ['label' => 'Total', 'type' => 'money', 'col' => 4],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['number', 'type', 'status', 'payment_status', 'total', 'created_at'],
        ],
        'bookings' => [
            'table' => 'bookings', 'label' => 'Booking', 'plural' => 'Bookings', 'icon' => 'calendar',
            'search' => ['reference', 'customer_name', 'customer_phone', 'customer_email'], 'order' => 'booking_date DESC, id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('BKG');
                }
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'customer_name' => ['label' => 'Customer name', 'type' => 'text', 'required' => true, 'col' => 4],
                'customer_phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 4],
                'customer_email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'product_id' => ['label' => 'Service / room', 'type' => 'select', 'options_from' => ['table' => 'products', 'label' => 'name'], 'col' => 6],
                'booking_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 4],
                'start_time' => ['label' => 'Start time', 'type' => 'time', 'col' => 4],
                'end_time' => ['label' => 'End time', 'type' => 'time', 'col' => 4],
                'party_size' => ['label' => 'Party size', 'type' => 'integer', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'], 'default' => 'pending', 'col' => 4],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'customer_name', 'booking_date', 'start_time', 'status'],
        ],
        'invoices' => [
            'table' => 'invoices', 'label' => 'Invoice', 'plural' => 'Invoices', 'icon' => 'file-text',
            'search' => ['number', 'notes'], 'order' => 'id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['number'])) {
                    $data['number'] = generate_reference('INV');
                }
                if (empty($data['issue_date'])) {
                    $data['issue_date'] = date('Y-m-d');
                }
                $data['total'] = round((float) ($data['subtotal'] ?? 0) + (float) ($data['tax'] ?? 0), 2);
                return $data;
            },
            'fields' => [
                'number' => ['label' => 'Invoice number', 'type' => 'text', 'col' => 4],
                'customer_id' => ['label' => 'Customer', 'type' => 'select', 'options_from' => ['table' => 'customers', 'label' => 'name'], 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'], 'default' => 'draft', 'col' => 4],
                'issue_date' => ['label' => 'Issue date', 'type' => 'date', 'col' => 4],
                'due_date' => ['label' => 'Due date', 'type' => 'date', 'col' => 4],
                'subtotal' => ['label' => 'Subtotal', 'type' => 'money', 'col' => 4],
                'tax' => ['label' => 'Tax', 'type' => 'money', 'col' => 4],
                'total' => ['label' => 'Total', 'type' => 'money', 'col' => 4],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['number', 'issue_date', 'due_date', 'status', 'total'],
        ],
        'staff' => [
            'table' => 'staff', 'label' => 'Staff member', 'plural' => 'Staff', 'icon' => 'briefcase',
            'search' => ['name', 'email', 'role_title'], 'order' => 'name ASC', 'upload_folder' => 'general',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'role_title' => ['label' => 'Role', 'type' => 'text', 'col' => 6],
                'photo' => ['label' => 'Photo', 'type' => 'image', 'folder' => 'general', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active', 'col' => 6],
                'bio' => ['label' => 'Bio', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['name', 'role_title', 'phone', 'status'],
        ],
        'reviews' => [
            'table' => 'reviews', 'label' => 'Review', 'plural' => 'Reviews', 'icon' => 'star',
            'search' => ['author_name', 'title', 'body'], 'order' => 'id DESC',
            'fields' => [
                'author_name' => ['label' => 'Author', 'type' => 'text', 'required' => true, 'col' => 6],
                'rating' => ['label' => 'Rating (1-5)', 'type' => 'integer', 'default' => 5, 'col' => 3],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], 'default' => 'pending', 'col' => 3],
                'title' => ['label' => 'Title', 'type' => 'text', 'col' => 12],
                'body' => ['label' => 'Review', 'type' => 'textarea', 'col' => 12],
                'source' => ['label' => 'Source', 'type' => 'select', 'options' => ['website' => 'Website', 'google' => 'Google', 'walk_in' => 'Walk-in', 'other' => 'Other'], 'default' => 'website', 'col' => 6],
            ],
            'list' => ['author_name', 'rating', 'status', 'created_at'],
        ],
        'messages' => [
            'table' => 'messages', 'label' => 'Message', 'plural' => 'Messages', 'icon' => 'mail',
            'search' => ['name', 'email', 'subject', 'body'], 'order' => 'id DESC',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'], 'default' => 'new', 'col' => 6],
                'subject' => ['label' => 'Subject', 'type' => 'text', 'col' => 12],
                'body' => ['label' => 'Message', 'type' => 'textarea', 'required' => true, 'col' => 12],
            ],
            'list' => ['name', 'subject', 'status', 'created_at'],
        ],
    ],
    'widgets' => [
        ['label' => 'Customers', 'entity' => 'customers', 'metric' => 'count', 'icon' => 'users'],
        ['label' => 'Orders', 'entity' => 'orders', 'metric' => 'count', 'icon' => 'shopping-cart'],
        ['label' => 'Pending orders', 'entity' => 'orders', 'metric' => 'count', 'where' => ['status' => 'pending'], 'icon' => 'clock'],
        ['label' => 'Paid revenue', 'entity' => 'orders', 'metric' => 'sum', 'column' => 'total', 'where' => ['payment_status' => 'paid'], 'icon' => 'dollar', 'format' => 'money'],
    ],
];
