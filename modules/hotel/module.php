<?php
return [
    'slug' => 'hotel',
    'name' => 'Hotel & Hospitality',
    'icon' => 'bed',
    'description' => 'Rooms, availability, reservations and guest management.',
    'nav' => [
        ['label' => 'Rooms', 'url' => 'app/records.php?entity=rooms', 'icon' => 'bed', 'entity' => 'rooms'],
        ['label' => 'Reservations', 'url' => 'app/records.php?entity=room_reservations', 'icon' => 'calendar', 'entity' => 'room_reservations'],
    ],
    'entities' => [
        'rooms' => [
            'table' => 'rooms', 'label' => 'Room', 'plural' => 'Rooms', 'icon' => 'bed',
            'search' => ['name', 'room_type', 'description'], 'order' => 'id DESC', 'upload_folder' => 'rooms',
            'fields' => [
                'name' => ['label' => 'Room name / number', 'type' => 'text', 'required' => true, 'col' => 6],
                'room_type' => ['label' => 'Room type', 'type' => 'text', 'col' => 6, 'help' => 'e.g. Deluxe, Suite, Standard'],
                'price' => ['label' => 'Price per night', 'type' => 'money', 'required' => true, 'col' => 4],
                'capacity' => ['label' => 'Capacity', 'type' => 'integer', 'default' => 2, 'col' => 4],
                'beds' => ['label' => 'Beds', 'type' => 'text', 'col' => 4, 'help' => 'e.g. 1 king, 2 singles'],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance'], 'default' => 'available', 'col' => 6],
                'image' => ['label' => 'Photo', 'type' => 'image', 'folder' => 'rooms', 'col' => 6],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['name', 'room_type', 'price', 'capacity', 'status'],
        ],
        'room_reservations' => [
            'table' => 'bookings', 'label' => 'Room reservation', 'plural' => 'Reservations', 'icon' => 'calendar',
            'search' => ['reference', 'customer_name', 'customer_phone'], 'order' => 'booking_date DESC, id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('HTL');
                }
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'customer_name' => ['label' => 'Guest name', 'type' => 'text', 'required' => true, 'col' => 4],
                'customer_phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 4],
                'customer_email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'product_id' => ['label' => 'Room', 'type' => 'select', 'options_from' => ['table' => 'rooms', 'label' => 'name'], 'col' => 6],
                'booking_date' => ['label' => 'Check-in date', 'type' => 'date', 'required' => true, 'col' => 4],
                'end_time' => ['label' => 'Check-out date', 'type' => 'date', 'col' => 4],
                'party_size' => ['label' => 'Guests', 'type' => 'integer', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'checked_in' => 'Checked in', 'completed' => 'Checked out', 'cancelled' => 'Cancelled', 'no_show' => 'No show'], 'default' => 'pending', 'col' => 6],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'customer_name', 'booking_date', 'party_size', 'status'],
        ],
    ],
    'widgets' => [
        ['label' => 'Rooms', 'entity' => 'rooms', 'metric' => 'count', 'icon' => 'bed'],
        ['label' => 'Available rooms', 'table' => 'rooms', 'metric' => 'count', 'where_raw' => "status = 'available'", 'icon' => 'check'],
        ['label' => 'Arrivals today', 'table' => 'bookings', 'metric' => 'count', 'where_raw' => 'booking_date = CURDATE()', 'icon' => 'calendar'],
    ],
];
