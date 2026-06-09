<?php

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/MailService.php';

loadEnv(__DIR__ . '/.env');

$uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && $uri === '/api/send-email') {
    handleApi();
    exit;
}

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

renderLandingPage();
exit;

// ─── API ──────────────────────────────────────────────────────────────────

function handleApi(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    verifyBearerToken();

    $body = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bad Request', 'message' => 'JSON inválido.']);
        return;
    }

    $smtp   = $body['smtp'] ?? [];
    $errors = [];

    if (empty($smtp['host']))          $errors[] = 'smtp.host requerido.';
    if (empty($smtp['port']))          $errors[] = 'smtp.port requerido.';
    if (empty($smtp['username']))      $errors[] = 'smtp.username requerido.';
    if (empty($smtp['password']))      $errors[] = 'smtp.password requerido.';
    if (empty($smtp['encryption']))    $errors[] = 'smtp.encryption requerido (ssl|tls).';
    if (empty($smtp['from_address']))  $errors[] = 'smtp.from_address requerido.';

    if (empty($body['to']))
        $errors[] = '"to" requerido.';
    elseif (!filter_var($body['to'], FILTER_VALIDATE_EMAIL))
        $errors[] = '"to" no es un email válido.';
    if (empty($body['subject']))       $errors[] = '"subject" requerido.';
    if (empty($body['body']))          $errors[] = '"body" requerido.';

    if ($errors) {
        http_response_code(422);
        echo json_encode(['error' => 'Validation Error', 'messages' => $errors]);
        return;
    }

    try {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new \RuntimeException('PHPMailer no está instalado. Ejecuta: composer install');
        }

        (new MailService())->send(
            [
                'host'         => $smtp['host'],
                'port'         => $smtp['port'],
                'username'     => $smtp['username'],
                'password'     => $smtp['password'],
                'encryption'   => $smtp['encryption'],
                'from_address' => $smtp['from_address'],
                'from_name'    => $smtp['from_name'] ?? $smtp['from_address'],
            ],
            [
                'to'       => $body['to'],
                'to_name'  => $body['to_name'] ?? '',
                'subject'  => $body['subject'],
                'body'     => $body['body'],
                'is_html'  => $body['is_html'] ?? true,
                'alt_body' => $body['alt_body'] ?? null,
                'cc'       => $body['cc'] ?? [],
                'bcc'      => $body['bcc'] ?? [],
                'reply_to' => $body['reply_to'] ?? '',
            ]
        );

        echo json_encode(['success' => true, 'message' => 'Correo enviado.', 'to' => $body['to']]);

    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Mail Error', 'message' => $e->getMessage()]);
    }
}

// ─── LANDING PAGE ──────────────────────────────────────────────────────────

function renderLandingPage(): void
{
    header('Content-Type: text/html; charset=utf-8');

    $repoUrl = 'https://github.com/U-SITE-SAS-BIC/email-api';
    $siteUrl = 'https://u-site.app';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email API — Switch SMTP Puro | U/SITE.APP</title>
    <meta name="description" content="API ligera y sin estado para reenvío de correos SMTP. Recibe credenciales SMTP + HTML renderizado y entrega sin almacenar ni modificar.">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📧</text></svg>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0b1a14;
            --surface: #11271e;
            --border:  #1e3d2f;
            --green:   #24b36b;
            --green-hover: #2dce7a;
            --text:    #d9e6df;
            --muted:   #7a9286;
            --radius:  12px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { color: var(--green); text-decoration: none; }
        a:hover { color: var(--green-hover); text-decoration: underline; }

        .container { max-width: 960px; margin: 0 auto; padding: 0 24px; width: 100%; }

        /* ── Nav ───────────────────────────────────────────── */
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            max-width: 1008px;
            margin: 0 auto;
            width: 100%;
        }

        nav .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 18px;
            color: var(--text);
        }

        nav .logo span { color: var(--green); }

        nav .links {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
        }

        nav .links a { color: var(--muted); }
        nav .links a:hover { color: var(--text); text-decoration: none; }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background .2s, transform .1s;
        }

        .btn:active { transform: scale(.97); }

        .btn-primary {
            background: var(--green);
            color: #0b1a14;
        }

        .btn-primary:hover { background: var(--green-hover); color: #0b1a14; text-decoration: none; }

        .btn-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-outline:hover { border-color: var(--green); color: var(--green); text-decoration: none; }

        /* ── Hero ───────────────────────────────────────────── */
        .hero {
            text-align: center;
            padding: 80px 0 60px;
        }

        .hero .badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 100px;
            background: rgba(36, 179, 107, .12);
            color: var(--green);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .3px;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-size: clamp(32px, 5vw, 52px);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
        }

        .hero h1 span { color: var(--green); }

        .hero p {
            font-size: 18px;
            color: var(--muted);
            max-width: 640px;
            margin: 0 auto 32px;
        }

        .hero .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* ── Stats ──────────────────────────────────────────── */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin: 40px 0 80px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px 16px;
            text-align: center;
        }

        .stat-card .num {
            font-size: 28px;
            font-weight: 800;
            color: var(--green);
        }

        .stat-card .label {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }

        /* ── Section ────────────────────────────────────────── */
        section { padding: 60px 0; }

        section h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        section .sub {
            color: var(--muted);
            font-size: 16px;
            margin-bottom: 40px;
            max-width: 560px;
        }

        /* ── Cards ──────────────────────────────────────────── */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px;
            transition: border-color .2s;
        }

        .card:hover { border-color: var(--green); }

        .card .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(36, 179, 107, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 16px;
        }

        .card h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
        .card p  { font-size: 14px; color: var(--muted); }

        /* ── Code block ─────────────────────────────────────── */
        .code-block {
            background: #06120e;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            overflow-x: auto;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 13px;
            line-height: 1.7;
            color: #bcd5ca;
        }

        .code-block .comment { color: #4a6b5b; }
        .code-block .key     { color: #7ecba1; }
        .code-block .string  { color: #d4a76a; }
        .code-block .method  { color: #6db3f2; }

        /* ── Table ──────────────────────────────────────────── */
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--surface);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
        }

        tr:last-child td { border-bottom: none; }

        td .tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }

        .tag-req { background: rgba(36, 179, 107, .15); color: var(--green); }
        .tag-opt { background: rgba(122, 146, 134, .15); color: var(--muted); }

        /* ── Endpoint badge ─────────────────────────────────── */
        .endpoint {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .endpoint .method {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            background: var(--green);
            color: #0b1a14;
        }

        .endpoint .path {
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 16px;
            color: var(--text);
        }

        /* ── Footer ─────────────────────────────────────────── */
        footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 32px 24px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }

        footer a { color: var(--muted); }
        footer a:hover { color: var(--green); }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 640px) {
            nav .links .btn-outline { display: none; }
            .hero { padding: 48px 0 32px; }
            section { padding: 40px 0; }
        }
    </style>
</head>
<body>

    <nav>
        <div class="logo">
            <svg width="28" height="28" viewBox="0 0 100 100" fill="none">
                <rect width="100" height="100" rx="20" fill="#24b36b"/>
                <path d="M30 40 L50 55 L70 40" stroke="#0b1a14" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <rect x="25" y="35" width="50" height="35" rx="6" stroke="#0b1a14" stroke-width="6" fill="none"/>
            </svg>
            Email <span>API</span>
        </div>
        <div class="links">
            <a href="#docs">Documentación</a>
            <a href="https://github.com/U-SITE-SAS-BIC/email-api" target="_blank">GitHub</a>
            <a href="<?= $siteUrl ?>" target="_blank">U/SITE.APP</a>
            <a href="#try" class="btn btn-primary">Probar</a>
        </div>
    </nav>

    <!-- ─── HERO ────────────────────────────────────────────── -->
    <section class="hero">
        <div class="container">
            <div class="badge">🚀 Open Source &bull; Apache 2.0</div>
            <h1>Email <span>Switch</span> API</h1>
            <p>
                Una API ligera y sin estado que recibe credenciales SMTP + HTML renderizado
                y lo reenvía tal cual. Sin almacenamiento, sin plantillas, sin complicaciones.
            </p>
            <div class="actions">
                <a href="#docs" class="btn btn-primary">📖 Documentación</a>
                <a href="<?= $repoUrl ?>" target="_blank" class="btn btn-outline">GitHub</a>
            </div>
        </div>
    </section>

    <div class="container">

        <!-- ─── STATS ────────────────────────────────────────── -->
        <div class="stats">
            <div class="stat-card">
                <div class="num">Zero</div>
                <div class="label">Almacenamiento</div>
            </div>
            <div class="stat-card">
                <div class="num">1</div>
                <div class="label">Endpoint</div>
            </div>
            <div class="stat-card">
                <div class="num">~150</div>
                <div class="label">Líneas de código</div>
            </div>
            <div class="stat-card">
                <div class="num">Apache 2.0</div>
                <div class="label">Licencia</div>
            </div>
        </div>

        <!-- ─── CONCEPTO ─────────────────────────────────────── -->
        <section id="concept">
            <h2>¿Cómo funciona?</h2>
            <p class="sub">Un intermediario switch puro — no guarda nada, solo reenvía.</p>
            <div class="card-grid">
                <div class="card">
                    <div class="icon">📤</div>
                    <h3>Recibe</h3>
                    <p>Credenciales SMTP y HTML renderizado desde Laravel, Node.js o cualquier backend.</p>
                </div>
                <div class="card">
                    <div class="icon">🔄</div>
                    <h3>Reenvía</h3>
                    <p>Pasa el mensaje a PHPMailer sin modificar una sola línea del contenido.</p>
                </div>
                <div class="card">
                    <div class="icon">🧹</div>
                    <h3>Olvida</h3>
                    <p>No almacena nada. Las credenciales y el cuerpo viven solo en memoria durante la petición.</p>
                </div>
            </div>
        </section>

        <!-- ─── DOCS ─────────────────────────────────────────── -->
        <section id="docs">
            <h2>Referencia de la API</h2>
            <p class="sub">Un solo endpoint. Todo lo que necesitas para enviar correos desde cualquier lenguaje.</p>

            <div class="endpoint">
                <span class="method">POST</span>
                <span class="path">/api/send-email</span>
            </div>

            <h3 style="margin-bottom:12px;font-size:15px;font-weight:600;">Headers</h3>
            <div class="table-wrap" style="margin-bottom:32px;">
                <table>
                    <thead>
                        <tr><th>Header</th><th>Valor</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>Authorization</code></td><td><code>Bearer &lt;tu_token&gt;</code></td></tr>
                        <tr><td><code>Content-Type</code></td><td><code>application/json</code></td></tr>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-bottom:12px;font-size:15px;font-weight:600;">Bloque SMTP</h3>
            <div class="table-wrap" style="margin-bottom:32px;">
                <table>
                    <thead>
                        <tr><th>Campo</th><th>Tipo</th><th></th><th>Descripción</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>host</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>Servidor SMTP</td></tr>
                        <tr><td><code>port</code></td><td>int</td><td><span class="tag tag-req">req</span></td><td>465 (SSL) o 587 (TLS)</td></tr>
                        <tr><td><code>username</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>Usuario SMTP</td></tr>
                        <tr><td><code>password</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>Contraseña SMTP</td></tr>
                        <tr><td><code>encryption</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td><code>"ssl"</code> o <code>"tls"</code></td></tr>
                        <tr><td><code>from_address</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>Email del remitente</td></tr>
                        <tr><td><code>from_name</code></td><td>string</td><td><span class="tag tag-opt">opt</span></td><td>Nombre del remitente</td></tr>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-bottom:12px;font-size:15px;font-weight:600;">Datos del mensaje</h3>
            <div class="table-wrap" style="margin-bottom:32px;">
                <table>
                    <thead>
                        <tr><th>Campo</th><th>Tipo</th><th></th><th>Descripción</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>to</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>Email destino</td></tr>
                        <tr><td><code>subject</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>Asunto</td></tr>
                        <tr><td><code>body</code></td><td>string</td><td><span class="tag tag-req">req</span></td><td>HTML pre-renderizado</td></tr>
                        <tr><td><code>to_name</code></td><td>string</td><td><span class="tag tag-opt">opt</span></td><td>Nombre destino</td></tr>
                        <tr><td><code>is_html</code></td><td>bool</td><td><span class="tag tag-opt">opt</span></td><td>Default <code>true</code></td></tr>
                        <tr><td><code>alt_body</code></td><td>string</td><td><span class="tag tag-opt">opt</span></td><td>Texto plano alternativo</td></tr>
                        <tr><td><code>cc</code></td><td>string[]</td><td><span class="tag tag-opt">opt</span></td><td>Copia</td></tr>
                        <tr><td><code>bcc</code></td><td>string[]</td><td><span class="tag tag-opt">opt</span></td><td>Copia oculta</td></tr>
                        <tr><td><code>reply_to</code></td><td>string</td><td><span class="tag tag-opt">opt</span></td><td>Reply-To</td></tr>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-bottom:12px;font-size:15px;font-weight:600;">Ejemplo con curl</h3>
            <div class="code-block">
                <span class="comment"># Enviar un correo</span><br>
                curl -X POST https://email.u-s.app/api/send-email \<br>
                &nbsp;&nbsp;-H <span class="string">"Authorization: Bearer tu_token"</span> \<br>
                &nbsp;&nbsp;-H <span class="string">"Content-Type: application/json"</span> \<br>
                &nbsp;&nbsp;-d <span class="string">'</span>{<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"smtp"</span>: {<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"host"</span>:         <span class="string">"smtp.hostinger.com"</span>,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"port"</span>:         465,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"username"</span>:     <span class="string">"info@ejemplo.com"</span>,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"password"</span>:     <span class="string">"tu_password"</span>,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"encryption"</span>:   <span class="string">"ssl"</span>,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"from_address"</span>: <span class="string">"info@ejemplo.com"</span><br>
                &nbsp;&nbsp;&nbsp;&nbsp;},<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"to"</span>:      <span class="string">"destino@ejemplo.com"</span>,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"subject"</span>: <span class="string">"Asunto del correo"</span>,<br>
                &nbsp;&nbsp;&nbsp;&nbsp;<span class="key">"body"</span>:    <span class="string">"&lt;h1&gt;Hola&lt;/h1&gt;&lt;p&gt;HTML renderizado&lt;/p&gt;"</span><br>
                &nbsp;&nbsp;}<span class="string">'</span>
            </div>
        </section>

        <!-- ─── TRY ──────────────────────────────────────────── -->
        <section id="try">
            <h2>Probar la API</h2>
            <p class="sub">Usa el explorador interactivo o curl desde tu terminal.</p>

            <div class="card-grid">
                <div class="card">
                    <div class="icon">🐚</div>
                    <h3>curl</h3>
                    <p>Copia el ejemplo de arriba, cambia los valores y ejecútalo en tu terminal.</p>
                </div>
                <div class="card">
                    <div class="icon">⚡</div>
                    <h3>Laravel</h3>
                    <p><code>Http::withToken(token)->post('...')</code> — integración directa con HTTP Client.</p>
                </div>
                <div class="card">
                    <div class="icon">🟢</div>
                    <h3>Node.js</h3>
                    <p><code>fetch(url, { method: 'POST', headers, body })</code> — compatible con cualquier runtime.</p>
                </div>
            </div>
        </section>

        <!-- ─── OPEN SOURCE ──────────────────────────────────── -->
        <section>
            <h2>Open Source</h2>
            <p class="sub">Código abierto, transparente y libre bajo licencia Apache 2.0.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="<?= $repoUrl ?>" target="_blank" class="btn btn-primary">Ver en GitHub</a>
                <a href="<?= $repoUrl ?>/blob/main/LICENSE" target="_blank" class="btn btn-outline">Licencia</a>
            </div>
        </section>

    </div>

    <!-- ─── FOOTER ──────────────────────────────────────────── -->
    <footer>
        <div class="container">
            <p>
                Hecho con ❤️ por <a href="<?= $siteUrl ?>" target="_blank">U/SITE.APP SAS BIC</a>
                &bull; Apache 2.0 &bull;
                <a href="<?= $repoUrl ?>" target="_blank">GitHub</a>
            </p>
        </div>
    </footer>

</body>
</html>
<?php
}
