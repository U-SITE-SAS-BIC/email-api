# Email API — PHP + PHPMailer (Pure Switch)

Esta API actúa como un **intermediario (switch) puro**. No almacena configuraciones de correo; recibe las credenciales SMTP y el cuerpo del mensaje (HTML renderizado) en cada petición y lo reenvía sin modificaciones.

Ideal para ser consumida por sistemas como **Laravel**, donde el correo se genera con Blade y se envía a través de esta API.

---

## Estructura

```
email/
├── index.php               ← Punto de entrada (validación + switch)
├── src/
│   ├── helpers.php         ← Cargador de .env (solo para el Token)
│   ├── auth.php            ← Middleware Bearer Token
│   └── MailService.php     ← Reenvío SMTP con PHPMailer
├── .env                    ← Contiene el token: MAIL_API_TOKEN=Usite2025*+
├── .htaccess               ← Rewrite para Apache/cPanel
└── vendor/                 ← PHPMailer (composer)
```

---

## Iniciar servidor local

```bash
php -S localhost:8000
```

---

## Endpoint

### `POST /api/send-email`

**Header requerido:**
```
Authorization: Bearer Usite2025*+
Content-Type: application/json
```

**Body JSON:**

```json
{
  "smtp": {
    "host":         "smtp.hostinger.com",
    "port":         465,
    "username":     "info@emunabioresort.com",
    "password":     "Emuna2024*+",
    "encryption":   "ssl",
    "from_address": "info@emunabioresort.com",
    "from_name":    "Emuna Bio Resort"
  },
  "to":        "destino@ejemplo.com",
  "to_name":   "Nombre Cliente",
  "subject":   "Asunto del correo",
  "body":      "<h1>Hola</h1><p>HTML renderizado.</p>",
  "is_html":   true,
  "alt_body":  "Texto plano opcional",
  "cc":        [],
  "bcc":       [],
  "reply_to":  ""
}
```

---

## Verificación con curl

```bash
curl -X POST https://email.u-s.app/api/send-email \
  -H "Authorization: Bearer Usite2025*+" \
  -H "Content-Type: application/json" \
  -d '{
    "smtp": {
      "host": "smtp.hostinger.com",
      "port": 465,
      "username": "info@emunabioresort.com",
      "password": "Emuna2024*+",
      "encryption": "ssl",
      "from_address": "info@emunabioresort.com",
      "from_name": "Emuna Bio Resort"
    },
    "to": "lizandrogd@gmail.com",
    "subject": "Prueba Switch",
    "body": "<h1>Prueba Exitosa</h1><p>Mensaje desde la API.</p>"
  }'
```

---

## Despliegue

1. Sube los archivos al hosting.
2. Asegúrate de que el `.env` tenga el `MAIL_API_TOKEN`.
3. Todo el tráfico es gestionado por `index.php` gracias al `.htaccess`.
