<?php
declare(strict_types=1);

session_name('whs_invitation');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Invitation setup is incomplete. See the deployment instructions.');
}

$config = require $configPath;
if (!is_array($config) || empty($config['admin_password_hash']) || !is_string($config['database_path'] ?? null)) {
    http_response_code(503);
    exit('Invitation setup is incomplete. See the deployment instructions.');
}

try {
    $databaseDirectory = dirname($config['database_path']);
    if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0700, true) && !is_dir($databaseDirectory)) {
        throw new RuntimeException('Unable to create the private database directory.');
    }

    $database = new PDO('sqlite:' . $config['database_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec(
        'CREATE TABLE IF NOT EXISTS attendees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            professional_title TEXT NOT NULL,
            phone_number TEXT NOT NULL,
            email TEXT NOT NULL,
            duplicate_key TEXT NOT NULL UNIQUE,
            confirmed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $attendeeColumns = array_column($database->query('PRAGMA table_info(attendees)')->fetchAll(), 'name');
    if (!in_array('notes', $attendeeColumns, true)) {
        $database->exec("ALTER TABLE attendees ADD COLUMN notes TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('phone_number', $attendeeColumns, true)) {
        $database->exec("ALTER TABLE attendees ADD COLUMN phone_number TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('email', $attendeeColumns, true)) {
        $database->exec("ALTER TABLE attendees ADD COLUMN email TEXT NOT NULL DEFAULT ''");
    }
    @chmod($config['database_path'], 0600);
} catch (Throwable $error) {
    error_log('WHS invitation database initialization failed: ' . $error->getMessage());
    http_response_code(503);
    exit('The confirmation service is temporarily unavailable. Please try again later.');
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    $provided = $_POST['csrf_token'] ?? '';
    return is_string($provided)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $provided);
}

function clean_field(mixed $value, int $maxLength): ?string
{
    if (!is_string($value) || !preg_match('//u', $value)) {
        return null;
    }

    $value = preg_replace('/[\p{Z}\s]+/u', ' ', trim($value));
    if (!is_string($value) || $value === '' || preg_match('/[\p{C}<>]/u', $value)) {
        return null;
    }

    preg_match_all('/./us', $value, $characters);
    if (count($characters[0]) > $maxLength) {
        return null;
    }

    return $value;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $stored = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($stored) ? $stored : null;
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}