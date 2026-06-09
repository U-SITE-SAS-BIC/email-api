# Email API — Pure SMTP Switch

[![License](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4?logo=php)](composer.json)
[![PHPMailer](https://img.shields.io/badge/PHPMailer-%5E7.0-orange)](composer.json)

**A lightweight, stateless email relay API.**  
Receives SMTP credentials + fully rendered HTML (from Laravel, Blade, or any backend) and forwards the message **without storing, modifying, or inspecting the content**.

Created and maintained by **[U/SITE.APP SAS BIC](https://u-s.app)**.

---

## Concept

This API acts as a **pure switch intermediary**. It does **not**:
- Store email configurations
- Load or render templates
- Log message content

The consumer (e.g., Laravel with Blade) is responsible for generating the complete HTML. The API simply delivers it.

---

## Features

- Stateless — zero storage, zero database
- Bring your own SMTP — credentials per request
- HTML & plain text support
- CC / BCC / Reply-To
- CORS enabled
- Apache-friendly (`.htaccess`) or PHP built-in server
- Single 145-line entry point

---

## Requirements

- PHP 8.1+
- [Composer](https://getcomposer.org/)
- A PHP-enabled web server (Apache / Nginx / built-in)

---

## Installation

```bash
# 1. Clone
git clone https://github.com/U-SITE-SAS-BIC/email-api.git
cd email-api

# 2. Install dependencies
composer install

# 3. Configure
cp .env.example .env
# Edit .env and set your MAIL_API_TOKEN

# 4. Run (development)
php -S localhost:8000
```

### Production (Apache)

Copy the files to your web root. The included `.htaccess` rewrites all requests to `index.php`.

Make sure `mod_rewrite` is enabled and `AllowOverride All` is set for the directory.

### Production (Nginx)

```nginx
server {
    listen 80;
    server_name email.yourdomain.com;
    root /var/www/email-api;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## Configuration

The only required configuration is the `MAIL_API_TOKEN` in `.env`:

```ini
MAIL_API_TOKEN=your_secret_token_here
```

> **Security:** Never commit `.env` to version control. The `.gitignore` already excludes it.

All other SMTP credentials are provided **per request** via the JSON body — nothing is stored on the server.

---

## API Reference

### `POST /api/send-email`

#### Headers

| Header | Value |
|---|---|
| `Authorization` | `Bearer <your_token>` |
| `Content-Type` | `application/json` |

#### Body

##### `smtp` block (SMTP configuration)

| Field | Type | Required | Description |
|---|---|---|---|
| `host` | string | ✅ | SMTP server (e.g., `smtp.hostinger.com`) |
| `port` | integer | ✅ | Port (e.g., `465` for SSL, `587` for TLS) |
| `username` | string | ✅ | SMTP username / email |
| `password` | string | ✅ | SMTP password |
| `encryption` | string | ✅ | `"ssl"` or `"tls"` |
| `from_address` | string | ✅ | Sender email |
| `from_name` | string | ❌ | Sender name (defaults to `from_address`) |

##### Message data

| Field | Type | Required | Description |
|---|---|---|---|
| `to` | string | ✅ | Recipient email |
| `to_name` | string | ❌ | Recipient name |
| `subject` | string | ✅ | Email subject |
| `body` | string | ✅ | **Pre-rendered HTML** — sent as-is |
| `is_html` | boolean | ❌ | Default `true` |
| `alt_body` | string | ❌ | Plain text fallback |
| `cc` | string[] | ❌ | Carbon copy recipients |
| `bcc` | string[] | ❌ | Blind carbon copy recipients |
| `reply_to` | string | ❌ | Reply-To address |

#### Example request

```bash
curl -X POST https://email.yourdomain.com/api/send-email \
  -H "Authorization: Bearer your_secret_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "smtp": {
      "host": "smtp.hostinger.com",
      "port": 465,
      "username": "info@example.com",
      "password": "your_smtp_password",
      "encryption": "ssl",
      "from_address": "info@example.com",
      "from_name": "My App"
    },
    "to": "user@example.com",
    "subject": "Your invoice is ready",
    "body": "<html><body><h1>Hello</h1><p>Your invoice details...</p></body></html>",
    "cc": ["manager@example.com"],
    "alt_body": "Hello, your invoice is ready."
  }'
```

#### Success response

```json
{
  "success": true,
  "message": "Correo enviado.",
  "to": "user@example.com"
}
```

#### Error response

```json
{
  "error": "Validation Error",
  "messages": ["smtp.host requerido.", "\"to\" requerido."]
}
```

HTTP status codes: `200` OK, `400` Bad JSON, `401` Unauthorized, `404` Not Found, `405` Method Not Allowed, `422` Validation Error, `500` SMTP / Server Error.

---

## Integration examples

### Laravel

```php
use Illuminate\Support\Facades\Http;

Http::withToken(config('services.email_api.token'))
    ->post('https://email.yourdomain.com/api/send-email', [
        'smtp' => [
            'host'         => config('mail.mailers.smtp.host'),
            'port'         => config('mail.mailers.smtp.port'),
            'username'     => config('mail.mailers.smtp.username'),
            'password'     => config('mail.mailers.smtp.password'),
            'encryption'   => config('mail.mailers.smtp.encryption'),
            'from_address' => config('mail.from.address'),
            'from_name'    => config('mail.from.name'),
        ],
        'to'      => $user->email,
        'subject' => $mailable->subject,
        'body'    => view('emails.invoice', $data)->render(),
    ]);
```

### JavaScript / Node.js

```javascript
const response = await fetch('https://email.yourdomain.com/api/send-email', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer your_secret_token_here',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    smtp: { host, port, username, password, encryption, from_address, from_name },
    to: 'user@example.com',
    subject: 'Hello',
    body: '<h1>Test</h1>',
  }),
});
```

---

## Project structure

```
email-api/
├── index.php               ← Entry point (145 lines)
├── src/
│   ├── helpers.php         ← .env parser
│   ├── auth.php            ← Bearer token middleware
│   └── MailService.php     ← PHPMailer wrapper
├── templates/
│   └── default.html        ← Example email template
├── .env.example            ← Configuration template
├── .htaccess               ← Apache rewrite rules
├── composer.json           ← Dependencies
└── LICENSE                 ← Apache 2.0
```

---

## Security

- **No data stored** — credentials and content exist only in memory during request processing
- **Bearer token** — all requests must include a valid `Authorization` header
- **.env gitignored** — secrets never reach version control
- **Input validated** — all required fields are checked before sending

---

## License

Copyright 2025 [U/SITE.APP SAS BIC](https://u-s.app)

Licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE) for the full license text.

---

<div align="center">
  <sub>Built with ❤️ by <a href="https://u-s.app">U/SITE.APP</a></sub>
</div>
