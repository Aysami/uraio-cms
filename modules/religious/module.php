<?php
return [
    'slug' => 'religious',
    'name' => 'Religious Organization',
    'icon' => 'heart',
    'description' => 'Events, announcements, programmes, donations and gallery.',
    'nav' => [
        ['label' => 'Events', 'url' => 'app/records.php?entity=events', 'icon' => 'calendar', 'entity' => 'events'],
        ['label' => 'Announcements', 'url' => 'app/records.php?entity=announcements', 'icon' => 'megaphone', 'entity' => 'announcements'],
        ['label' => 'Donations', 'url' => 'app/records.php?entity=donations', 'icon' => 'heart', 'entity' => 'donations'],
    ],
    'entities' => [
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
        'announcements' => [
            'table' => 'announcements', 'label' => 'Announcement', 'plural' => 'Announcements', 'icon' => 'megaphone',
            'search' => ['title', 'body'], 'order' => 'id DESC',
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text', 'required' => true, 'col' => 8],
                'audience' => ['label' => 'Audience', 'type' => 'select', 'options' => ['public' => 'Public', 'members' => 'Members', 'staff' => 'Staff'], 'default' => 'public', 'col' => 4],
                'published_at' => ['label' => 'Publish date', 'type' => 'datetime', 'col' => 6],
                'body' => ['label' => 'Announcement', 'type' => 'textarea', 'required' => true, 'col' => 12],
            ],
            'list' => ['title', 'audience', 'published_at'],
        ],
        'donations' => [
            'table' => 'donations', 'label' => 'Donation', 'plural' => 'Donations', 'icon' => 'heart',
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
    ],
    'widgets' => [
        ['label' => 'Upcoming events', 'table' => 'events', 'metric' => 'count', 'where_raw' => "starts_at >= NOW() AND status = 'upcoming'", 'icon' => 'calendar'],
        ['label' => 'Announcements', 'entity' => 'announcements', 'metric' => 'count', 'icon' => 'megaphone'],
        ['label' => 'Donations received', 'entity' => 'donations', 'metric' => 'sum', 'column' => 'amount', 'where' => ['status' => 'successful'], 'icon' => 'heart', 'format' => 'money'],
    ],
];
