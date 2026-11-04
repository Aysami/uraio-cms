<?php
return [
    'slug' => 'ecommerce',
    'name' => 'E-commerce',
    'icon' => 'shopping-bag',
    'description' => 'Products, inventory, coupons, orders and online checkout.',
    'nav' => [
        ['label' => 'Shop products', 'url' => 'app/records.php?entity=products', 'icon' => 'shopping-bag', 'entity' => 'products'],
        ['label' => 'Coupons', 'url' => 'app/records.php?entity=coupons', 'icon' => 'tag', 'entity' => 'coupons'],
    ],
    'entities' => [
        'coupons' => [
            'table' => 'coupons', 'label' => 'Coupon', 'plural' => 'Coupons', 'icon' => 'tag',
            'search' => ['code'], 'order' => 'id DESC',
            'fields' => [
                'code' => ['label' => 'Coupon code', 'type' => 'text', 'required' => true, 'col' => 4],
                'type' => ['label' => 'Type', 'type' => 'select', 'options' => ['percentage' => 'Percentage', 'fixed' => 'Fixed amount'], 'default' => 'percentage', 'col' => 4],
                'value' => ['label' => 'Value', 'type' => 'money', 'required' => true, 'col' => 4],
                'min_order' => ['label' => 'Minimum order', 'type' => 'money', 'col' => 4],
                'usage_limit' => ['label' => 'Usage limit', 'type' => 'integer', 'col' => 4],
                'expires_at' => ['label' => 'Expires', 'type' => 'datetime', 'col' => 4],
                'status' => ['label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'default' => 'active', 'col' => 4],
            ],
            'list' => ['code', 'type', 'value', 'used_count', 'status'],
        ],
    ],
    'widgets' => [
        ['label' => 'Products', 'table' => 'products', 'metric' => 'count', 'where_raw' => "deleted_at IS NULL", 'icon' => 'shopping-bag'],
        ['label' => 'Orders', 'entity' => 'orders', 'metric' => 'count', 'icon' => 'shopping-cart'],
        ['label' => 'Low stock', 'table' => 'products', 'metric' => 'count', 'where_raw' => 'track_stock = 1 AND stock IS NOT NULL AND stock <= 5', 'icon' => 'alert'],
    ],
];
