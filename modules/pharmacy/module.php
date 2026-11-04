<?php
return [
    'slug' => 'pharmacy',
    'name' => 'Pharmacy',
    'icon' => 'pill',
    'description' => 'Medicine inventory, batches, expiry tracking and low-stock alerts.',
    'nav' => [
        ['label' => 'Medicines', 'url' => 'app/records.php?entity=medicines', 'icon' => 'pill', 'entity' => 'medicines'],
        ['label' => 'Inventory', 'url' => 'app/records.php?entity=inventory', 'icon' => 'database', 'entity' => 'inventory'],
    ],
    'entities' => [
        'medicines' => [
            'table' => 'medicines', 'label' => 'Medicine', 'plural' => 'Medicines', 'icon' => 'pill',
            'search' => ['name', 'generic_name', 'brand', 'supplier'], 'order' => 'name ASC',
            'fields' => [
                'name' => ['label' => 'Medicine name', 'type' => 'text', 'required' => true, 'col' => 6],
                'generic_name' => ['label' => 'Generic name', 'type' => 'text', 'col' => 6],
                'brand' => ['label' => 'Brand', 'type' => 'text', 'col' => 4],
                'category' => ['label' => 'Category', 'type' => 'text', 'col' => 4],
                'unit' => ['label' => 'Unit', 'type' => 'text', 'col' => 4, 'help' => 'e.g. pack of 10, bottle'],
                'price' => ['label' => 'Selling price', 'type' => 'money', 'required' => true, 'col' => 4],
                'stock' => ['label' => 'Stock quantity', 'type' => 'integer', 'default' => 0, 'col' => 4],
                'low_stock_threshold' => ['label' => 'Low stock alert at', 'type' => 'integer', 'default' => 5, 'col' => 4],
                'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'col' => 4],
                'batch' => ['label' => 'Batch number', 'type' => 'text', 'col' => 4],
                'supplier' => ['label' => 'Supplier', 'type' => 'text', 'col' => 4],
                'prescription_required' => ['label' => 'Prescription required', 'type' => 'checkbox', 'col' => 6],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active', 'col' => 6],
            ],
            'list' => ['name', 'stock', 'price', 'expiry_date', 'status'],
        ],
        'inventory' => [
            'table' => 'inventory', 'label' => 'Inventory record', 'plural' => 'Inventory', 'icon' => 'database',
            'search' => ['batch', 'supplier'], 'order' => 'id DESC',
            'fields' => [
                'product_id' => ['label' => 'Product', 'type' => 'select', 'options_from' => ['table' => 'products', 'label' => 'name'], 'required' => true, 'col' => 6],
                'quantity' => ['label' => 'Quantity', 'type' => 'integer', 'default' => 0, 'col' => 6],
                'low_stock_threshold' => ['label' => 'Low stock threshold', 'type' => 'integer', 'default' => 5, 'col' => 4],
                'expiry_date' => ['label' => 'Expiry date', 'type' => 'date', 'col' => 4],
                'batch' => ['label' => 'Batch', 'type' => 'text', 'col' => 4],
                'supplier' => ['label' => 'Supplier', 'type' => 'text', 'col' => 6],
            ],
            'list' => ['product_id', 'quantity', 'expiry_date', 'batch'],
        ],
    ],
    'widgets' => [
        ['label' => 'Medicines', 'entity' => 'medicines', 'metric' => 'count', 'icon' => 'pill'],
        ['label' => 'Low stock', 'table' => 'medicines', 'metric' => 'count', 'where_raw' => 'stock <= low_stock_threshold', 'icon' => 'alert'],
        ['label' => 'Expiring in 60 days', 'table' => 'medicines', 'metric' => 'count', 'where_raw' => 'expiry_date IS NOT NULL AND expiry_date <= (CURDATE() + INTERVAL 60 DAY)', 'icon' => 'clock'],
    ],
];
