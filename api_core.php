<?php
declare(strict_types=1);

function env_load(string $file): void {
    if (!is_file($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if ($line === '' || strpos(ltrim($line), '#') === 0) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"");
        if ($k !== '' && getenv($k) === false) { putenv($k . '=' . $v); $_ENV[$k] = $v; }
    }
}

function cfg(string $key, $default = null) {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', cfg('DB_HOST', '127.0.0.1'), cfg('DB_PORT', '3306'), cfg('DB_NAME', 'booking'));
    $pdo = new PDO($dsn, (string) cfg('DB_USER', ''), (string) cfg('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return $pdo;
}

function request_id(): string { return bin2hex(random_bytes(8)); }
function method(): string { return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')); }
function ip_addr(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'); }
function query(string $k, $default=null) { return $_GET[$k] ?? $default; }
function json_input(): array { $j = json_decode((string)(file_get_contents('php://input') ?: '{}'), true); return is_array($j) ? $j : []; }
function cors(): void { header('Access-Control-Allow-Origin: ' . cfg('CORS_ALLOW_ORIGIN', '*')); header('Access-Control-Allow-Methods: GET,POST,PUT,OPTIONS'); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Key'); if (method() === 'OPTIONS') { http_response_code(204); exit; } }

function ok($data, string $message = 'OK', int $status = 200): void { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success' => true, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE); exit; }
function fail(string $code, string $message, int $status = 400, array $errors = []): void { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success' => false, 'errorCode' => $code, 'message' => $message, 'errors' => $errors, 'requestId' => request_id()], JSON_UNESCAPED_UNICODE); exit; }

function rate_limit(string $scope): void {
    $max = (int) cfg('RATE_LIMIT_MAX', 100);
    $window = (int) cfg('RATE_LIMIT_WINDOW_SECONDS', 60);
    $db = db();
    $db->exec('CREATE TABLE IF NOT EXISTS booking_rate_limits (scope_key VARCHAR(190) PRIMARY KEY, hits INT NOT NULL, window_start INT NOT NULL)');
    $k = $scope . ':' . ip_addr();
    $now = time();
    $st = $db->prepare('SELECT hits, window_start FROM booking_rate_limits WHERE scope_key = ?');
    $st->execute([$k]); $row = $st->fetch();
    if (!$row) { $ins = $db->prepare('INSERT INTO booking_rate_limits(scope_key,hits,window_start) VALUES(?,?,?)'); $ins->execute([$k,1,$now]); return; }
    $hits=(int)$row['hits']; $start=(int)$row['window_start'];
    if (($now - $start) >= $window) { $up=$db->prepare('UPDATE booking_rate_limits SET hits=1, window_start=? WHERE scope_key=?'); $up->execute([$now,$k]); return; }
    if ($hits >= $max) fail('RATE_LIMITED','Demasiadas solicitudes.',429);
    $up=$db->prepare('UPDATE booking_rate_limits SET hits=hits+1 WHERE scope_key=?'); $up->execute([$k]);
}

function admin_auth(): void { $sent = (string)($_SERVER['HTTP_X_ADMIN_KEY'] ?? ''); if ($sent === '' || !hash_equals((string) cfg('ADMIN_KEY', ''), $sent)) fail('UNAUTHORIZED','No autorizado.',401); }

function issue_token(int $appointmentId, string $licenseUuid): string {
    $exp = time() + (int) cfg('TOKEN_TTL_SECONDS', 3600);
    $payload = $appointmentId . '|' . $licenseUuid . '|' . $exp;
    $sig = hash_hmac('sha256', $payload, (string) cfg('TOKEN_SECRET', 'secret'));
    return rtrim(strtr(base64_encode(json_encode(['appointmentId'=>$appointmentId,'licenseUuid'=>$licenseUuid,'exp'=>$exp,'sig'=>$sig])), '+/', '-_'), '=');
}
function parse_token(string $token): array {
    $raw = base64_decode(strtr($token, '-_', '+/') . str_repeat('=', (4 - strlen($token) % 4) % 4), true);
    $d = json_decode((string) $raw, true);
    if (!is_array($d)) fail('TOKEN_INVALID','Token inválido.',401);
    $payload = ((int)$d['appointmentId']) . '|' . (string)$d['licenseUuid'] . '|' . ((int)$d['exp']);
    $sig = hash_hmac('sha256', $payload, (string) cfg('TOKEN_SECRET', 'secret'));
    if (!hash_equals($sig, (string)($d['sig'] ?? '')) || time() > (int)$d['exp']) fail('TOKEN_INVALID','Token inválido o expirado.',401);
    return $d;
}

function normalize_status(string $status): string {
    $s = strtolower(trim($status));
    $allowed = ['pending','confirmed','cancelled','completed','no_show'];
    if (!in_array($s, $allowed, true)) fail('VALIDATION_ERROR','status inválido.',400,['field'=>'status']);
    return $s;
}

function license_or_fail(string $uuid): array {
    if ($uuid === '') fail('VALIDATION_ERROR','licenseUuid es requerido.',400,['field'=>'licenseUuid']);
    $st = db()->prepare('SELECT license_id, license_uuid, booking_enabled, license_name, logo_url FROM booking_licenses WHERE license_uuid = ? LIMIT 1');
    $st->execute([$uuid]); $l = $st->fetch();
    if (!$l) fail('LICENSE_NOT_FOUND','Licencia no encontrada.',404);
    if (!(int)$l['booking_enabled']) fail('LICENSE_INACTIVE','La licencia no está habilitada para reservas.',422);
    return $l;
}
