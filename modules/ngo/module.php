<?php
return [
    'slug' => 'ngo',
    'name' => 'NGO & Non-profit',
    'icon' => 'heart',
    'description' => 'Projects, donations, campaigns, volunteers and events.',
    'nav' => [
        ['label' => 'Projects', 'url' => 'app/records.php?entity=projects', 'icon' => 'heart', 'entity' => 'projects'],
        ['label' => 'Donations', 'url' => 'app/records.php?entity=donations', 'icon' => 'dollar', 'entity' => 'donations'],
        ['label' => 'Volunteers', 'url' => 'app/records.php?entity=volunteers', 'icon' => 'users', 'entity' => 'volunteers'],
        ['label' => 'Events', 'url' => 'app/records.php?entity=events', 'icon' => 'calendar', 'entity' => 'events'],
    ],
    'entities' => [
        'projects' => [
            'table' => 'projects', 'label' => 'Project', 'plural' => 'Projects', 'icon' => 'heart',
            'search' => ['title', 'description'], 'order' => 'id DESC', 'upload_folder' => 'general',
            'fields' => [
                'title' => ['label' => 'Project title', 'type' => 'text', 'required' => true, 'col' => 8],
                'goal_amount' => ['label' => 'Funding goal', 'type' => 'money', 'col' => 4],
                'raised_amount' => ['label' => 'Amount raised', 'type' => 'money', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'completed' => 'Completed', 'paused' => 'Paused'], 'default' => 'active', 'col' => 4],
                'image' => ['label' => 'Image', 'type' => 'image', 'folder' => 'general', 'col' => 4],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['title', 'goal_amount', 'raised_amount', 'status'],
        ],
        'donations' => [
            'table' => 'donations', 'label' => 'Donation', 'plural' => 'Donations', 'icon' => 'dollar',
            'search' => ['donor_name', 'email', 'reference', 'purpose'], 'order' => 'id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('DON');
                }
                return $data;
            },
            'fields' => [
                'donor_name' => ['label' => 'Donor name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'amount' => ['label' => 'Amount', 'type' => 'money', 'required' => true, 'col' => 6],
                'purpose' => ['label' => 'Purpose', 'type' => 'text', 'col' => 8],
                'reference' => ['label' => 'Reference', 'type' => 'text', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'successful' => 'Successful', 'failed' => 'Failed'], 'default' => 'successful', 'col' => 6],
            ],
            'list' => ['donor_name', 'amount', 'purpose', 'status', 'created_at'],
        ],
        'volunteers' => [
            'table' => 'volunteers', 'label' => 'Volunteer', 'plural' => 'Volunteers', 'icon' => 'users',
            'search' => ['name', 'email', 'skills'], 'order' => 'id DESC',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'skills' => ['label' => 'Skills', 'type' => 'text', 'col' => 6],
                'availability' => ['label' => 'Availability', 'type' => 'text', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['new' => 'New', 'active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'new', 'col' => 6],
            ],
            'list' => ['name', 'skills', 'availability', 'status'],
        ],
        'events' => [
            'table' => 'events', 'label' => 'Event', 'plural' => 'Events', 'icon' => 'calendar',
            'search' => ['title', 'description', 'location'], 'order' => 'starts_at DESC', 'upload_folder' => 'general',
            'fields' => [
                'title' => ['label' => 'Event title', 'type' => 'text', 'required' => true, 'col' => 8],
                'starts_at' => ['label' => 'Starts', 'type' => 'datetime', 'required' => true, 'col' => 4],
                'ends_at' => ['label' => 'Ends', 'type' => 'datetime', 'col' => 4],
                'location' => ['label' => 'Location', 'type' => 'text', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['upcoming' => 'Upcoming', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'], 'default' => 'upcoming', 'col' => 4],
                'image' => ['label' => 'Image', 'type' => 'image', 'folder' => 'general', 'col' => 4],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['title', 'starts_at', 'location', 'status'],
        ],
    ],
    'widgets' => [
        ['label' => 'Active projects', 'table' => 'projects', 'metric' => 'count', 'where_raw' => "status = 'active'", 'icon' => 'heart'],
        ['label' => 'Donations', 'entity' => 'donations', 'metric' => 'sum', 'column' => 'amount', 'where' => ['status' => 'successful'], 'icon' => 'dollar', 'format' => 'money'],
        ['label' => 'Volunteers', 'entity' => 'volunteers', 'metric' => 'count', 'icon' => 'users'],
    ],
];
