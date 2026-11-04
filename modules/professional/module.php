<?php
return [
    'slug' => 'professional',
    'name' => 'Professional Services',
    'icon' => 'briefcase',
    'description' => 'Services, clients, proposals, invoices and consultation bookings.',
    'nav' => [
        ['label' => 'Services', 'url' => 'app/records.php?entity=professional_services', 'icon' => 'briefcase', 'entity' => 'professional_services'],
        ['label' => 'Clients', 'url' => 'app/records.php?entity=customers', 'icon' => 'users', 'entity' => 'customers'],
        ['label' => 'Consultations', 'url' => 'app/records.php?entity=consultations', 'icon' => 'calendar', 'entity' => 'consultations'],
    ],
    'entities' => [
        'professional_services' => [
            'table' => 'products', 'label' => 'Service', 'plural' => 'Services', 'icon' => 'briefcase',
            'search' => ['name', 'description'], 'order' => 'name ASC', 'upload_folder' => 'products', 'slug_from' => 'name',
            'before_save' => function (array $data, array $business, ?int $id): array {
                $data['type'] = 'service';
                return $data;
            },
            'fields' => [
                'name' => ['label' => 'Service name', 'type' => 'text', 'required' => true, 'col' => 8],
                'price' => ['label' => 'Fee', 'type' => 'money', 'required' => true, 'col' => 4],
                'duration_minutes' => ['label' => 'Duration (minutes)', 'type' => 'integer', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active', 'col' => 4],
                'image' => ['label' => 'Image', 'type' => 'image', 'folder' => 'products', 'col' => 4],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['name', 'price', 'duration_minutes', 'status'],
        ],
        'consultations' => [
            'table' => 'bookings', 'label' => 'Consultation', 'plural' => 'Consultations', 'icon' => 'calendar',
            'search' => ['reference', 'customer_name', 'customer_phone'], 'order' => 'booking_date DESC, id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('CON');
                }
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'customer_name' => ['label' => 'Client name', 'type' => 'text', 'required' => true, 'col' => 4],
                'customer_phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 4],
                'customer_email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'product_id' => ['label' => 'Service', 'type' => 'select', 'options_from' => ['table' => 'products', 'label' => 'name'], 'col' => 6],
                'booking_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 4],
                'start_time' => ['label' => 'Time', 'type' => 'time', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Requested', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'], 'default' => 'pending', 'col' => 4],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'customer_name', 'booking_date', 'start_time', 'status'],
        ],
    ],
    'widgets' => [
        ['label' => 'Clients', 'table' => 'customers', 'metric' => 'count', 'where_raw' => 'deleted_at IS NULL', 'icon' => 'users'],
        ['label' => 'Services', 'table' => 'products', 'metric' => 'count', 'where_raw' => "type = 'service' AND deleted_at IS NULL", 'icon' => 'briefcase'],
        ['label' => 'Consultations', 'table' => 'bookings', 'metric' => 'count', 'where_raw' => 'booking_date >= CURDATE()', 'icon' => 'calendar'],
    ],
];
