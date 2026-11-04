<?php
/**
 * Generic tenant-scoped entity engine used by module screens.
 * Every query is scoped by business_id; ids are never trusted without scope.
 */

function entity_fields(array $entity): array
{
    return $entity['fields'] ?? [];
}

function entity_field(array $entity, string $key): ?array
{
    return $entity['fields'][$key] ?? null;
}

function entity_defaults(array $entity): array
{
    $defaults = $entity['defaults'] ?? [];
    foreach (entity_fields($entity) as $key => $field) {
        if (array_key_exists('default', $field) && !array_key_exists($key, $defaults)) {
            $defaults[$key] = $field['default'];
        }
    }
    return $defaults;
}

function entity_options(array $business, array $field): array
{
    if (!empty($field['options']) && is_array($field['options'])) {
        return $field['options'];
    }
    if (!empty($field['options_from'])) {
        $source = $field['options_from'];
        $table = preg_replace('/[^a-z0-9_]/', '', $source['table'] ?? '');
        $label = preg_replace('/[^a-z0-9_]/', '', $source['label'] ?? 'name');
        if ($table === '' || $label === '') {
            return [];
        }
        $rows = db_all("SELECT id, `$label` AS label FROM `$table` WHERE business_id = ? ORDER BY `$label` ASC", [$business['id']]);
        $options = [];
        foreach ($rows as $row) {
            $options[(string) $row['id']] = (string) $row['label'];
        }
        return $options;
    }
    return [];
}

function entity_list(array $business, array $entity, array $params = []): array
{
    $table = $entity['table'];
    $where = ['business_id = ?'];
    $bindings = [$business['id']];
    if (!empty($entity['soft_delete'])) {
        $where[] = 'deleted_at IS NULL';
    }

    if (!empty($entity['scope'])) {
        foreach ($entity['scope'] as $column => $value) {
            $where[] = "`$column` = ?";
            $bindings[] = $value;
        }
    }
    $search = trim((string) ($params['q'] ?? ''));
    if ($search !== '' && !empty($entity['search'])) {
        $parts = [];
        foreach ($entity['search'] as $column) {
            $parts[] = "`$column` LIKE ?";
            $bindings[] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    $status = trim((string) ($params['status'] ?? ''));
    if ($status !== '' && isset($entity['fields']['status'])) {
        $where[] = 'status = ?';
        $bindings[] = $status;
    }

    $whereSql = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM `$table` WHERE $whereSql", $bindings);
    $pagination = paginate($total, (int) ($entity['per_page'] ?? 20), (int) ($params['page'] ?? 1));
    $order = $entity['order'] ?? 'id DESC';
    $rows = db_all(
        "SELECT * FROM `$table` WHERE $whereSql ORDER BY $order LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
        $bindings
    );
    return ['rows' => $rows, 'pagination' => $pagination, 'total' => $total];
}

function entity_find(array $business, array $entity, int $id): ?array
{
    $sql = "SELECT * FROM `{$entity['table']}` WHERE id = ? AND business_id = ?";
    if (!empty($entity['soft_delete'])) {
        $sql .= ' AND deleted_at IS NULL';
    }
    return db_row($sql, [$id, $business['id']]);
}

function entity_save(array $business, array $entity, array $input, array $files = [], ?int $id = null): int
{
    $data = [];
    foreach (entity_fields($entity) as $key => $field) {
        $type = $field['type'] ?? 'text';
        if ($type === 'image') {
            $existingKey = 'existing_' . $key;
            if (!empty($files[$key]) && ($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploaded = upload_image($key, $field['folder'] ?? $entity['upload_folder'] ?? 'general');
                if ($uploaded) {
                    $data[$key] = $uploaded;
                }
            } elseif (!empty($input[$existingKey])) {
                $data[$key] = $input[$existingKey];
            }
            continue;
        }
        if ($type === 'checkbox') {
            $data[$key] = !empty($input[$key]) ? 1 : 0;
            continue;
        }
        $value = $input[$key] ?? null;
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === '' || $value === null) {
            if (!empty($field['required'])) {
                throw new InvalidArgumentException(($field['label'] ?? $key) . ' is required.');
            }
            $data[$key] = $field['nullable'] ?? true ? null : '';
            continue;
        }
        switch ($type) {
            case 'number':
            case 'integer':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException(($field['label'] ?? $key) . ' must be a number.');
                }
                $data[$key] = $type === 'integer' ? (int) $value : $value + 0;
                break;
            case 'money':
                if (!is_numeric($value)) {
                    throw new InvalidArgumentException(($field['label'] ?? $key) . ' must be an amount.');
                }
                $data[$key] = round((float) $value, 2);
                break;
            case 'date':
                $data[$key] = date('Y-m-d', strtotime((string) $value));
                break;
            case 'datetime':
                $data[$key] = date('Y-m-d H:i:s', strtotime((string) $value));
                break;
            case 'time':
                $data[$key] = date('H:i:s', strtotime((string) $value));
                break;
            case 'tags':
                $tags = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
                $data[$key] = json_encode(array_values($tags));
                break;
            case 'json':
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);
                $data[$key] = json_encode($decoded ?: []);
                break;
            case 'select':
                $options = entity_options($business, $field);
                if ($options && !array_key_exists((string) $value, $options)) {
                    throw new InvalidArgumentException('Choose a valid ' . strtolower($field['label'] ?? $key) . '.');
                }
                $data[$key] = $value;
                break;
            default:
                $data[$key] = $value;
        }
    }

    if (!empty($entity['slug_from']) && in_array('slug', array_keys($entity_fields($entity)), true)) {
        if (empty($data['slug'])) {
            $data['slug'] = unique_slug($entity['table'], 'slug', (string) ($data[$entity['slug_from']] ?? 'item'), $id);
        } else {
            $data['slug'] = unique_slug($entity['table'], 'slug', (string) $data['slug'], $id);
        }
    }

    if ($id === null) {
        foreach (entity_defaults($entity) as $key => $value) {
            if (!array_key_exists($key, $data) && array_key_exists($key, entity_fields($entity))) {
                $data[$key] = $value;
            }
        }
    }

    if (!empty($entity['before_save']) && is_callable($entity['before_save'])) {
        $data = ($entity['before_save'])($data, $business, $id);
    }

    $data['business_id'] = $business['id'];

    if ($id !== null) {
        $existing = entity_find($business, $entity, $id);
        if (!$existing) {
            throw new RuntimeException('Record not found.');
        }
        unset($data['business_id']);
        db_update($entity['table'], $data, 'id = :id AND business_id = :business_id', ['id' => $id, 'business_id' => $business['id']]);
        $savedId = $id;
        $action = 'updated';
    } else {
        $savedId = db_insert($entity['table'], $data);
        $action = 'created';
    }

    if (!empty($entity['after_save']) && is_callable($entity['after_save'])) {
        ($entity['after_save'])($savedId, $data, $business, $action);
    }
    return $savedId;
}

function entity_delete(array $business, array $entity, int $id): bool
{
    $record = entity_find($business, $entity, $id);
    if (!$record) {
        return false;
    }
    if (!empty($entity['soft_delete']) || !empty($record['deleted_at'])) {
        return db_exec(
            "UPDATE `{$entity['table']}` SET deleted_at = NOW() WHERE id = ? AND business_id = ?",
            [$id, $business['id']]
        ) > 0;
    }
    return db_exec("DELETE FROM `{$entity['table']}` WHERE id = ? AND business_id = ?", [$id, $business['id']]) > 0;
}

function entity_field_html(array $business, string $key, array $field, mixed $value): string
{
    $type = $field['type'] ?? 'text';
    $label = e($field['label'] ?? ucwords(str_replace('_', ' ', $key)));
    $required = !empty($field['required']) ? ' required' : '';
    $help = !empty($field['help']) ? '<small class="field-help">' . e($field['help']) . '</small>' : '';
    $col = (int) ($field['col'] ?? 6);
    $name = e($key);
    $value = $value ?? ($field['default'] ?? '');
    $html = '<div class="field field-col-' . $col . '"><label for="f_' . $name . '">' . $label . (!empty($field['required']) ? ' <span class="req">*</span>' : '') . '</label>';

    switch ($type) {
        case 'textarea':
        case 'richtext':
            $html .= '<textarea id="f_' . $name . '" name="' . $name . '" rows="' . (int) ($field['rows'] ?? 4) . '"' . $required . '>' . e($value) . '</textarea>';
            break;
        case 'select':
            $options = entity_options($business, $field);
            $html .= '<select id="f_' . $name . '" name="' . $name . '"' . $required . '>';
            if (empty($field['required'])) {
                $html .= '<option value="">— Select —</option>';
            }
            foreach ($options as $optionValue => $optionLabel) {
                $html .= '<option value="' . e($optionValue) . '" ' . selected($value, $optionValue) . '>' . e($optionLabel) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'checkbox':
            $html = '<div class="field field-col-' . $col . ' field-check"><label class="switch-label"><input type="checkbox" id="f_' . $name . '" name="' . $name . '" value="1" ' . checked($value) . '><span>' . $label . '</span></label>' . $help . '</div>';
            return $html;
        case 'image':
            $html .= '<div class="image-field">';
            if ($value) {
                $html .= '<img class="image-preview" src="' . e(media_url($value)) . '" alt="">';
            }
            $html .= '<input type="file" id="f_' . $name . '" name="' . $name . '" accept="image/*">';
            $html .= '<input type="hidden" name="existing_' . $name . '" value="' . e($value) . '">';
            $html .= '</div>';
            break;
        case 'money':
            $html .= '<div class="input-prefix"><span>' . e($business['currency'] ?? 'NGN') . '</span><input type="number" step="0.01" min="0" id="f_' . $name . '" name="' . $name . '" value="' . e($value) . '"' . $required . '></div>';
            break;
        case 'number':
        case 'integer':
            $html .= '<input type="number" step="' . ($type === 'integer' ? '1' : 'any') . '" id="f_' . $name . '" name="' . $name . '" value="' . e($value) . '"' . $required . '>';
            break;
        case 'date':
            $html .= '<input type="date" id="f_' . $name . '" name="' . $name . '" value="' . e($value ? date('Y-m-d', strtotime((string) $value)) : '') . '"' . $required . '>';
            break;
        case 'datetime':
            $html .= '<input type="datetime-local" id="f_' . $name . '" name="' . $name . '" value="' . e($value ? date('Y-m-d\TH:i', strtotime((string) $value)) : '') . '"' . $required . '>';
            break;
        case 'time':
            $html .= '<input type="time" id="f_' . $name . '" name="' . $name . '" value="' . e($value ? date('H:i', strtotime((string) $value)) : '') . '"' . $required . '>';
            break;
        case 'tags':
            $tags = is_array($value) ? implode(', ', $value) : (string) $value;
            $html .= '<input type="text" id="f_' . $name . '" name="' . $name . '" value="' . e($tags) . '" placeholder="Comma separated"' . $required . '>';
            break;
        case 'email':
            $html .= '<input type="email" id="f_' . $name . '" name="' . $name . '" value="' . e($value) . '"' . $required . '>';
            break;
        case 'phone':
            $html .= '<input type="tel" id="f_' . $name . '" name="' . $name . '" value="' . e($value) . '"' . $required . '>';
            break;
        case 'url':
            $html .= '<input type="url" id="f_' . $name . '" name="' . $name . '" value="' . e($value) . '"' . $required . '>';
            break;
        case 'hidden':
            $html = '<input type="hidden" name="' . $name . '" value="' . e($value) . '">';
            return $html;
        default:
            $html .= '<input type="text" id="f_' . $name . '" name="' . $name . '" value="' . e($value) . '"' . $required . '>';
    }
    return $html . $help . '</div>';
}

function entity_value_display(array $business, array $field, mixed $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $type = $field['type'] ?? 'text';
    if ($type === 'money') {
        return money((float) $value, $business['currency'] ?? 'NGN');
    }
    if ($type === 'date') {
        return nice_date((string) $value);
    }
    if ($type === 'datetime') {
        return nice_datetime((string) $value);
    }
    if ($type === 'image') {
        return '<img class="table-thumb" src="' . e(media_url((string) $value)) . '" alt="">';
    }
    if ($type === 'select') {
        $options = entity_options($business, $field);
        return e($options[(string) $value] ?? (string) $value);
    }
    if ($type === 'checkbox') {
        return $value ? 'Yes' : 'No';
    }
    if (in_array($type, ['status', 'badge'], true)) {
        return status_badge((string) $value);
    }
    return e(str_limit((string) $value, 70));
}
