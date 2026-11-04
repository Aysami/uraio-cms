<?php
return [
    'slug' => 'real-estate',
    'name' => 'Real Estate',
    'icon' => 'home',
    'description' => 'Property listings, enquiries, viewings and agent profiles.',
    'nav' => [
        ['label' => 'Properties', 'url' => 'app/records.php?entity=properties', 'icon' => 'home', 'entity' => 'properties'],
        ['label' => 'Viewings', 'url' => 'app/records.php?entity=viewings', 'icon' => 'calendar', 'entity' => 'viewings'],
        ['label' => 'Enquiries', 'url' => 'app/records.php?entity=enquiries', 'icon' => 'mail', 'entity' => 'enquiries'],
    ],
    'entities' => [
        'properties' => [
            'table' => 'properties', 'label' => 'Property', 'plural' => 'Properties', 'icon' => 'home',
            'search' => ['title', 'address', 'city', 'state'], 'order' => 'id DESC', 'slug_from' => 'title',
            'fields' => [
                'title' => ['label' => 'Listing title', 'type' => 'text', 'required' => true, 'col' => 8],
                'listing_type' => ['label' => 'Listing type', 'type' => 'select', 'options' => ['sale' => 'For sale', 'rent' => 'For rent', 'shortlet' => 'Short let', 'lease' => 'Lease'], 'default' => 'sale', 'col' => 4],
                'property_type' => ['label' => 'Property type', 'type' => 'text', 'col' => 4, 'help' => 'e.g. Duplex, apartment, land'],
                'price' => ['label' => 'Price', 'type' => 'money', 'required' => true, 'col' => 4],
                'bedrooms' => ['label' => 'Bedrooms', 'type' => 'integer', 'col' => 2],
                'bathrooms' => ['label' => 'Bathrooms', 'type' => 'integer', 'col' => 2],
                'area' => ['label' => 'Area', 'type' => 'text', 'col' => 4],
                'address' => ['label' => 'Address', 'type' => 'text', 'col' => 6],
                'city' => ['label' => 'City', 'type' => 'text', 'col' => 3],
                'state' => ['label' => 'State', 'type' => 'text', 'col' => 3],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['available' => 'Available', 'sold' => 'Sold', 'rented' => 'Rented', 'draft' => 'Draft'], 'default' => 'available', 'col' => 4],
                'agent_id' => ['label' => 'Agent', 'type' => 'select', 'options_from' => ['table' => 'staff', 'label' => 'name'], 'col' => 4],
                'featured' => ['label' => 'Featured', 'type' => 'checkbox', 'col' => 4],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['title', 'listing_type', 'price', 'city', 'status'],
        ],
        'viewings' => [
            'table' => 'bookings', 'label' => 'Viewing', 'plural' => 'Viewings', 'icon' => 'calendar',
            'search' => ['reference', 'customer_name', 'customer_phone'], 'order' => 'booking_date DESC, id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('VWG');
                }
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'customer_name' => ['label' => 'Client name', 'type' => 'text', 'required' => true, 'col' => 4],
                'customer_phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 4],
                'customer_email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'product_id' => ['label' => 'Property', 'type' => 'select', 'options_from' => ['table' => 'properties', 'label' => 'title'], 'col' => 6],
                'booking_date' => ['label' => 'Date', 'type' => 'date', 'required' => true, 'col' => 4],
                'start_time' => ['label' => 'Time', 'type' => 'time', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Requested', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No show'], 'default' => 'pending', 'col' => 4],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'customer_name', 'booking_date', 'start_time', 'status'],
        ],
        'enquiries' => [
            'table' => 'messages', 'label' => 'Enquiry', 'plural' => 'Enquiries', 'icon' => 'mail',
            'search' => ['name', 'email', 'subject', 'body'], 'order' => 'id DESC',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'], 'default' => 'new', 'col' => 6],
                'subject' => ['label' => 'Subject', 'type' => 'text', 'col' => 12],
                'body' => ['label' => 'Enquiry', 'type' => 'textarea', 'required' => true, 'col' => 12],
            ],
            'list' => ['name', 'subject', 'status', 'created_at'],
        ],
    ],
    'widgets' => [
        ['label' => 'Listings', 'entity' => 'properties', 'metric' => 'count', 'icon' => 'home'],
        ['label' => 'Available', 'entity' => 'properties', 'metric' => 'count', 'where' => ['status' => 'available'], 'icon' => 'check'],
        ['label' => 'Viewing requests', 'table' => 'bookings', 'metric' => 'count', 'where_raw' => "status = 'pending'", 'icon' => 'calendar'],
    ],
];
