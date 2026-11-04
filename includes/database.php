<?php
/**
 * PDO connection factory. One connection per request.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = config('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $db['host'] ?? '127.0.0.1',
        $db['port'] ?? '3306',
        $db['name'] ?? 'ubos'
    );
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    return $pdo;
}

function db_available(): bool
{
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_val(string $sql, array $params = [], mixed $default = null): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_insert(string $table, array $data): int
{
    $columns = array_keys($data);
    $placeholders = array_map(fn ($c) => ':' . $c, $columns);
    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        str_replace('`', '', $table),
        implode('`,`', $columns),
        implode(',', $placeholders)
    );
    $stmt = db()->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    $stmt->execute();
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    foreach (array_keys($data) as $column) {
        $sets[] = '`' . $column . '` = :' . $column;
    }
    $sql = sprintf('UPDATE `%s` SET %s WHERE %s', str_replace('`', '', $table), implode(', ', $sets), $where);
    $stmt = db()->prepare($sql);
    foreach ($data as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    foreach ($whereParams as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    return $stmt->rowCount();
}
