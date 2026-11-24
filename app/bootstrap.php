<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();

function e(mixed $input): string {
    return htmlspecialchars((string)($input ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function db(): PDO {
    static $db = null;
    if ($db instanceof PDO) return $db;
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'campus_portal';
    $user = getenv('DB_USER') ?: 'campus_user';
    $password = getenv('DB_PASSWORD') ?: '';
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $db;
}
function all(string $sql, array $params = []): array {
    $query = db()->prepare($sql); $query->execute($params); return $query->fetchAll();
}
function one(string $sql, array $params = []): ?array {
    $query = db()->prepare($sql); $query->execute($params); return $query->fetch() ?: null;
}
function execute(string $sql, array $params = []): int {
    $query = db()->prepare($sql); $query->execute($params); return $query->rowCount();
}
function csrf(): string {
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['csrf'];
}
function check_csrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit('Invalid request token. Go back and refresh the page.');
    }
}
function flash(string $message, string $type = 'ok'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function redirect(string $view = 'dashboard'): never {
    header('Location: /?view=' . rawurlencode($view)); exit;
}
function positive_id($value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new InvalidArgumentException('Please select a valid record.');
    return (int)$id;
}
function field(string $name, int $max = 120): string {
    $value = trim((string)($_POST[$name] ?? ''));
    if ($value === '' || mb_safe_len($value) > $max) throw new InvalidArgumentException("Please enter a valid $name.");
    return $value;
}
function mb_safe_len(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value); }
