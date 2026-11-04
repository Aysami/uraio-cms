<?php
/**
 * Uraio Smart Discovery adapter.
 *
 * The adapter is read-only against the discovery platform. It never writes to
 * the production discovery database. Credentials are configured in the admin
 * panel and are not shipped in source control.
 */

function uraio_remote_connection(): ?PDO
{
    $host = (string) setting('uraio_db_host', '');
    $name = (string) setting('uraio_db_name', '');
    $user = (string) setting('uraio_db_user', '');
    $pass = (string) setting('uraio_db_pass', '');
    $port = (int) setting('uraio_db_port', 3306);
    if ($host === '' || $name === '' || $user === '') {
        return null;
    }
    try {
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
    } catch (Throwable $e) {
        error_log('Uraio discovery adapter unavailable: ' . $e->getMessage());
        return null;
    }
}

function uraio_adapter_available(): bool
{
    return uraio_remote_connection() !== null;
}

function uraio_search_listings(string $term, int $limit = 20): array
{
    $pdo = uraio_remote_connection();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, slug, category_id, description, address, city, state, country, phone, email, website, image_url
             FROM businesses WHERE name LIKE ? OR city LIKE ? ORDER BY name ASC LIMIT ' . (int) $limit
        );
        $stmt->execute(['%' . $term . '%', '%' . $term . '%']);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function uraio_find_listing(string $email, string $phone, string $name = ''): ?array
{
    $pdo = uraio_remote_connection();
    if (!$pdo) {
        return null;
    }
    try {
        $sql = 'SELECT * FROM businesses WHERE 1=1';
        $params = [];
        if ($email !== '') {
            $sql .= ' AND email = ?';
            $params[] = $email;
        } elseif ($phone !== '') {
            $sql .= ' AND phone = ?';
            $params[] = $phone;
        } else {
            $sql .= ' AND name LIKE ?';
            $params[] = '%' . $name . '%';
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function uraio_claim_listing(array $business, array $listing): array
{
    $email = strtolower((string) ($business['email'] ?? ''));
    $listingEmail = strtolower((string) ($listing['email'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($business['phone'] ?? ''));
    $listingPhone = preg_replace('/\D+/', '', (string) ($listing['phone'] ?? ''));
    if (($email === '' || $email !== $listingEmail) && ($phone === '' || $phone !== $listingPhone)) {
        return ['ok' => false, 'error' => 'The listing contact details do not match your business profile. Update your business email or phone to match, or contact support.'];
    }
    $existing = db_row('SELECT * FROM integrations WHERE provider = ? AND external_id = ?', ['uraio_discovery', (string) $listing['id']]);
    if ($existing && (int) $existing['business_id'] !== (int) $business['id']) {
        return ['ok' => false, 'error' => 'This listing has already been claimed by another business.'];
    }
    if ($existing) {
        db_update('integrations', ['status' => 'verified', 'last_synced_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $existing['id']]);
    } else {
        db_insert('integrations', [
            'business_id' => $business['id'],
            'provider' => 'uraio_discovery',
            'external_id' => (string) $listing['id'],
            'status' => 'verified',
            'credentials' => json_encode(['url' => 'https://' . site_domain() . '/business/' . ($listing['slug'] ?? '')]),
            'last_synced_at' => date('Y-m-d H:i:s'),
        ]);
    }
    // Fill blank fields only; never overwrite data the owner has edited.
    $fill = [];
    foreach (['description', 'address', 'city', 'state', 'country', 'phone', 'email'] as $field) {
        if (empty($business[$field]) && !empty($listing[$field])) {
            $fill[$field] = $listing[$field];
        }
    }
    if ($fill) {
        db_update('businesses', $fill, 'id = :id', ['id' => $business['id']]);
    }
    activity_log('uraio.listing_claimed', 'integration', $listing['id'], [], (int) $business['id']);
    return ['ok' => true, 'message' => 'Your Uraio discovery listing is now connected.'];
}

function uraio_sync_business(array $business): array
{
    $integration = db_row("SELECT * FROM integrations WHERE business_id = ? AND provider = 'uraio_discovery' AND status = 'verified' LIMIT 1", [$business['id']]);
    if (!$integration) {
        return ['ok' => false, 'error' => 'No verified Uraio listing is connected to this business.'];
    }
    $pdo = uraio_remote_connection();
    if (!$pdo) {
        return ['ok' => false, 'error' => 'The Uraio discovery adapter is not configured.'];
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM businesses WHERE id = ? LIMIT 1');
        $stmt->execute([$integration['external_id']]);
        $listing = $stmt->fetch();
        if (!$listing) {
            return ['ok' => false, 'error' => 'The connected listing no longer exists.'];
        }
        db_update('integrations', ['last_synced_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $integration['id']]);
        return ['ok' => true, 'listing' => $listing, 'synced_at' => date('Y-m-d H:i:s')];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Could not sync the listing.'];
    }
}

function uraio_business_listing(array $business): ?array
{
    $integration = db_row("SELECT * FROM integrations WHERE business_id = ? AND provider = 'uraio_discovery' AND status = 'verified' LIMIT 1", [$business['id']]);
    if (!$integration) {
        return null;
    }
    $pdo = uraio_remote_connection();
    if (!$pdo) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT id, name, slug, city, state, phone, email, website FROM businesses WHERE id = ? LIMIT 1');
        $stmt->execute([$integration['external_id']]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}
