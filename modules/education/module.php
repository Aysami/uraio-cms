<?php
return [
    'slug' => 'education',
    'name' => 'Education',
    'icon' => 'graduation',
    'description' => 'Courses, students, enrolments and announcements.',
    'nav' => [
        ['label' => 'Courses', 'url' => 'app/records.php?entity=courses', 'icon' => 'graduation', 'entity' => 'courses'],
        ['label' => 'Enrolments', 'url' => 'app/records.php?entity=enrollments', 'icon' => 'users', 'entity' => 'enrollments'],
        ['label' => 'Announcements', 'url' => 'app/records.php?entity=announcements', 'icon' => 'megaphone', 'entity' => 'announcements'],
    ],
    'entities' => [
        'courses' => [
            'table' => 'courses', 'label' => 'Course', 'plural' => 'Courses', 'icon' => 'graduation',
            'search' => ['title', 'code', 'description'], 'order' => 'id DESC', 'upload_folder' => 'general',
            'fields' => [
                'title' => ['label' => 'Course title', 'type' => 'text', 'required' => true, 'col' => 8],
                'code' => ['label' => 'Course code', 'type' => 'text', 'col' => 4],
                'fee' => ['label' => 'Fee', 'type' => 'money', 'col' => 4],
                'duration' => ['label' => 'Duration', 'type' => 'text', 'col' => 4, 'help' => 'e.g. 12 weeks'],
                'capacity' => ['label' => 'Capacity', 'type' => 'integer', 'col' => 4],
                'schedule' => ['label' => 'Schedule', 'type' => 'text', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'full' => 'Full'], 'default' => 'active', 'col' => 3],
                'image' => ['label' => 'Image', 'type' => 'image', 'folder' => 'general', 'col' => 3],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['title', 'code', 'fee', 'duration', 'status'],
        ],
        'enrollments' => [
            'table' => 'enrollments', 'label' => 'Enrolment', 'plural' => 'Enrolments', 'icon' => 'users',
            'search' => ['student_name', 'email', 'phone'], 'order' => 'id DESC',
            'fields' => [
                'student_name' => ['label' => 'Student name', 'type' => 'text', 'required' => true, 'col' => 6],
                'course_id' => ['label' => 'Course', 'type' => 'select', 'options_from' => ['table' => 'courses', 'label' => 'title'], 'col' => 6],
                'email' => ['label' => 'Email', 'type' => 'email', 'col' => 6],
                'phone' => ['label' => 'Phone', 'type' => 'phone', 'col' => 6],
                'amount_paid' => ['label' => 'Amount paid', 'type' => 'money', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'enrolled' => 'Enrolled', 'completed' => 'Completed', 'withdrawn' => 'Withdrawn'], 'default' => 'pending', 'col' => 4],
            ],
            'list' => ['student_name', 'course_id', 'amount_paid', 'status'],
        ],
        'announcements' => [
            'table' => 'announcements', 'label' => 'Announcement', 'plural' => 'Announcements', 'icon' => 'megaphone',
            'search' => ['title', 'body'], 'order' => 'id DESC',
            'fields' => [
                'title' => ['label' => 'Title', 'type' => 'text', 'required' => true, 'col' => 8],
                'audience' => ['label' => 'Audience', 'type' => 'select', 'options' => ['public' => 'Public', 'students' => 'Students', 'staff' => 'Staff'], 'default' => 'public', 'col' => 4],
                'published_at' => ['label' => 'Publish date', 'type' => 'datetime', 'col' => 6],
                'body' => ['label' => 'Announcement', 'type' => 'textarea', 'required' => true, 'col' => 12],
            ],
            'list' => ['title', 'audience', 'published_at'],
        ],
    ],
    'widgets' => [
        ['label' => 'Courses', 'entity' => 'courses', 'metric' => 'count', 'icon' => 'graduation'],
        ['label' => 'Enrolments', 'entity' => 'enrollments', 'metric' => 'count', 'icon' => 'users'],
        ['label' => 'Pending enrolments', 'entity' => 'enrollments', 'metric' => 'count', 'where' => ['status' => 'pending'], 'icon' => 'clock'],
    ],
];
