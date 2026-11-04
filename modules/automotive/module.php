<?php
return [
    'slug' => 'automotive',
    'name' => 'Automotive Workshop',
    'icon' => 'wrench',
    'description' => 'Vehicles, job cards, repairs, parts and service history.',
    'nav' => [
        ['label' => 'Vehicles', 'url' => 'app/records.php?entity=vehicles', 'icon' => 'car', 'entity' => 'vehicles'],
        ['label' => 'Job cards', 'url' => 'app/records.php?entity=job_cards', 'icon' => 'wrench', 'entity' => 'job_cards'],
    ],
    'entities' => [
        'vehicles' => [
            'table' => 'vehicles', 'label' => 'Vehicle', 'plural' => 'Vehicles', 'icon' => 'car',
            'search' => ['plate_number', 'make', 'model', 'vin'], 'order' => 'id DESC',
            'fields' => [
                'plate_number' => ['label' => 'Plate number', 'type' => 'text', 'required' => true, 'col' => 4],
                'make' => ['label' => 'Make', 'type' => 'text', 'col' => 4],
                'model' => ['label' => 'Model', 'type' => 'text', 'col' => 4],
                'year' => ['label' => 'Year', 'type' => 'integer', 'col' => 4],
                'customer_id' => ['label' => 'Owner', 'type' => 'select', 'options_from' => ['table' => 'customers', 'label' => 'name'], 'col' => 4],
                'mileage' => ['label' => 'Mileage (km)', 'type' => 'integer', 'col' => 4],
                'vin' => ['label' => 'VIN', 'type' => 'text', 'col' => 6],
                'notes' => ['label' => 'Notes', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['plate_number', 'make', 'model', 'year', 'customer_id'],
        ],
        'job_cards' => [
            'table' => 'job_cards', 'label' => 'Job card', 'plural' => 'Job cards', 'icon' => 'wrench',
            'search' => ['reference', 'description'], 'order' => 'id DESC',
            'before_save' => function (array $data, array $business, ?int $id): array {
                if (empty($data['reference'])) {
                    $data['reference'] = generate_reference('JOB');
                }
                $data['total'] = round((float) ($data['labor_cost'] ?? 0) + (float) ($data['parts_cost'] ?? 0), 2);
                return $data;
            },
            'fields' => [
                'reference' => ['label' => 'Job number', 'type' => 'text', 'col' => 4],
                'vehicle_id' => ['label' => 'Vehicle', 'type' => 'select', 'options_from' => ['table' => 'vehicles', 'label' => 'plate_number'], 'col' => 4],
                'customer_id' => ['label' => 'Customer', 'type' => 'select', 'options_from' => ['table' => 'customers', 'label' => 'name'], 'col' => 4],
                'assigned_staff_id' => ['label' => 'Assigned technician', 'type' => 'select', 'options_from' => ['table' => 'staff', 'label' => 'name'], 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['open' => 'Open', 'in_progress' => 'In progress', 'awaiting_parts' => 'Awaiting parts', 'completed' => 'Completed', 'cancelled' => 'Cancelled'], 'default' => 'open', 'col' => 4],
                'labor_cost' => ['label' => 'Labour cost', 'type' => 'money', 'col' => 4],
                'parts_cost' => ['label' => 'Parts cost', 'type' => 'money', 'col' => 4],
                'total' => ['label' => 'Total', 'type' => 'money', 'col' => 4],
                'description' => ['label' => 'Work requested', 'type' => 'textarea', 'col' => 12],
            ],
            'list' => ['reference', 'vehicle_id', 'status', 'total', 'opened_at'],
        ],
    ],
    'widgets' => [
        ['label' => 'Vehicles', 'entity' => 'vehicles', 'metric' => 'count', 'icon' => 'car'],
        ['label' => 'Open jobs', 'table' => 'job_cards', 'metric' => 'count', 'where_raw' => "status IN ('open','in_progress','awaiting_parts')", 'icon' => 'wrench'],
        ['label' => 'Awaiting parts', 'table' => 'job_cards', 'metric' => 'count', 'where_raw' => "status = 'awaiting_parts'", 'icon' => 'alert'],
    ],
];
