<?php

/**
 * Email API — Switch puro de correo
 *
 * Recibe la config SMTP + el correo ya renderizado (desde Laravel u otro sistema)
 * y lo reenvía por SMTP sin modificar el contenido.
 *
 * POST /api/send-email
 * Header: Authorization: Bearer <token>
 *
 * Body JSON:
 * {
 *   "smtp": {
 *     "host":         "smtp.hostinger.com",
 *     "port":         465,
 *     "username":     "info@ejemplo.com",
 *     "password":     "secret",
 *     "encryption":   "ssl",
 *     "from_address": "info@ejemplo.com",
 *     "from_name":    "Mi App"           // opcional
 *   },
 *   "to":        "destino@ejemplo.com",
 *   "to_name":   "Nombre",              // opcional
 *   "subject":   "Asunto",
 *   "body":      "<html>...</html>",    // HTML ya renderizado (ej: desde Laravel Blade)
 *   "is_html":   true,                  // opcional, default true
 *   "alt_body":  "Versión texto plano", // opcional, fallback sin HTML
 *   "cc":        [],                    // opcional
 *   "bcc":       [],                    // opcional
 *   "reply_to":  ""                     // opcional
 * }
 */

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/MailService.php';

// ── 1. Cargar .env (solo para MAIL_API_TOKEN) ────────────────────────────────
loadEnv(__DIR__ . '/.env');

// ── 2. Headers ────────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── 3. Método ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ── 4. Ruta ───────────────────────────────────────────────────────────────────
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

if ($uri !== '/api/send-email') {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'message' => 'Use POST /api/send-email']);
    exit;
}

// ── 5. Token ──────────────────────────────────────────────────────────────────
verifyBearerToken();

// ── 6. JSON ───────────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'JSON inválido.']);
    exit;
}

// ── 7. Validación ─────────────────────────────────────────────────────────────
$smtp = $body['smtp'] ?? [];
$errors = [];

if (empty($smtp['host']))
    $errors[] = 'smtp.host requerido.';
if (empty($smtp['port']))
    $errors[] = 'smtp.port requerido.';
if (empty($smtp['username']))
    $errors[] = 'smtp.username requerido.';
if (empty($smtp['password']))
    $errors[] = 'smtp.password requerido.';
if (empty($smtp['encryption']))
    $errors[] = 'smtp.encryption requerido (ssl|tls).';
if (empty($smtp['from_address']))
    $errors[] = 'smtp.from_address requerido.';

if (empty($body['to']))
    $errors[] = '"to" requerido.';
elseif (!filter_var($body['to'], FILTER_VALIDATE_EMAIL))
    $errors[] = '"to" no es un email válido.';
if (empty($body['subject']))
    $errors[] = '"subject" requerido.';
if (empty($body['body']))
    $errors[] = '"body" requerido.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'Validation Error', 'messages' => $errors]);
    exit;
}

// ── 8. Enviar ─────────────────────────────────────────────────────────────────
try {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        throw new \RuntimeException(
            'PHPMailer no está instalado. Ejecuta: composer install'
        );
    }

    (new MailService())->send(
        [
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'encryption' => $smtp['encryption'],
            'from_address' => $smtp['from_address'],
            'from_name' => $smtp['from_name'] ?? $smtp['from_address'],
        ],
        [
            'to' => $body['to'],
            'to_name' => $body['to_name'] ?? '',
            'subject' => $body['subject'],
            'body' => $body['body'],        // se envía TAL CUAL
            'is_html' => $body['is_html'] ?? true,
            'alt_body' => $body['alt_body'] ?? null,
            'cc' => $body['cc'] ?? [],
            'bcc' => $body['bcc'] ?? [],
            'reply_to' => $body['reply_to'] ?? '',
        ]
    );

    echo json_encode(['success' => true, 'message' => 'Correo enviado.', 'to' => $body['to']]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Mail Error', 'message' => $e->getMessage()]);
}
