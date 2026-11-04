<?php
return [
    'slug' => 'healthcare',
    'name' => 'Healthcare Practice',
    'icon' => 'heart',
    'description' => 'Patients, appointments and doctor profiles with strict access controls.',
    'nav' => [
        ['label' => 'Patients', 'url' => 'app/records.php?entity=patients', 'icon' => 'users', 'entity' => 'patients'],
        ['label' => 'Appointments', 'url' => 'app/records.php?entity=appointments', 'icon' => 'calendar', 'entity' => 'appointments'],
        ['label' => 'Doctors', 'url' => 'app/records.php?entity=doctors', 'icon' => 'briefcase', 'entity' => 'doctors'],
    ],
    'entities' => [
        'patients' => [
            'table' => 'customers', 'label' => 'Patient', 'plural' => 'Patients', 'icon' => 'users', 'soft_delete' => true,
            'search' => ['name', 'email', 'phone'], 'order' => 'id DESC',
            'fields' => [
                'name' => ['label' => 'Patient name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'whatsapp' => ['label' => 'WhatsApp', 'type' => 'phone', 'col' => 6],
                'address' => ['label' => 'Address', 'type' => 'text', 'col' => 12],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active', 'col' => 6],
                'notes' => ['label' => 'Contact notes', 'type' => 'textarea', 'col' => 12, 'help' => 'Do not store diagnosis or treatment decisions here.'],
            ],
            'list' => ['name', 'phone', 'email', 'status'],
        ],
        'appointments' => [
            'table' => 'bookings', 'label' => 'Appointment', 'plural' => 'Appointments', 'icon' => 'calendar',
            'search' => ['reference', 'customer_name', 'customer_phone'], 'order' => 'booking_date DESC, id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('APT');
                }
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'customer_name' => ['label' => 'Patient name', 'type' => 'text', 'required' => true, 'col' => 4],
                'customer_phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 4],
                'customer_email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'staff_id' => ['label' => 'Doctor', 'type' => 'select', 'options_from' => ['table' => 'staff', 'label' => 'name'], 'col' => 6],
                'booking_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 3],
                'start_time' => ['label' => 'Time', 'type' => 'time', 'col' => 3],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Requested', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'], 'default' => 'pending', 'col' => 3],
                'notes' => ['label' => 'Administrative notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'customer_name', 'booking_date', 'start_time', 'status'],
        ],
        'doctors' => [
            'table' => 'staff', 'label' => 'Doctor', 'plural' => 'Doctors', 'icon' => 'briefcase',
            'search' => ['name', 'role_title'], 'order' => 'name ASC',
            'fields' => [
                'name' => ['label' => 'Full name', 'type' => 'text', 'required' => true, 'col' => 6],
                'role_title' => ['label' => 'Specialty', 'type' => 'text', 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'photo' => ['label' => 'Photo', 'type' => 'image', 'folder' => 'general', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active', 'col' => 6],
                'bio' => ['label' => 'Profile', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['name', 'role_title', 'phone', 'status'],
        ],
    ],
    'widgets' => [
        ['label' => 'Patients', 'table' => 'customers', 'metric' => 'count', 'where_raw' => 'deleted_at IS NULL', 'icon' => 'users'],
        ['label' => 'Appointments today', 'table' => 'bookings', 'metric' => 'count', 'where_raw' => 'booking_date = CURDATE()', 'icon' => 'calendar'],
        ['label' => 'Pending requests', 'table' => 'bookings', 'metric' => 'count', 'where_raw' => "status = 'pending'", 'icon' => 'clock'],
    ],
];
