# Email API — Switch SMTP Puro

[![License](https://img.shields.io/badge/Licencia-Apache_2.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4?logo=php)](composer.json)
[![PHPMailer](https://img.shields.io/badge/PHPMailer-%5E7.0-orange)](composer.json)

**API ligera y sin estado para reenvío de correos SMTP.**  
Recibe credenciales SMTP + HTML ya renderizado (desde Laravel, Blade o cualquier backend) y entrega el mensaje **sin almacenar, modificar ni inspeccionar el contenido**.

Creado y mantenido por **[U/SITE.APP SAS BIC](https://u-site.app)**.

---

## Concepto

Esta API actúa como un **intermediario switch puro**. **No**:
- Almacena configuraciones de correo
- Carga o renderiza plantillas
- Guarda el contenido de los mensajes

La aplicación consumidora (ej: Laravel con Blade) es responsable de generar el HTML completo. La API solo lo entrega.

---

## Características

- Sin estado — cero almacenamiento, cero base de datos
- Trae tu propio SMTP — credenciales por petición
- Soporte HTML y texto plano
- CC / BCC / Reply-To
- CORS habilitado
- Compatible con Apache (`.htaccess`) o servidor PHP integrado
- Punto de entrada de solo 145 líneas

---

## Requisitos

- PHP 8.1+
- [Composer](https://getcomposer.org/)
- Servidor web con PHP (Apache / Nginx / integrado)

---

## Instalación

```bash
# 1. Clonar
git clone https://github.com/U-SITE-SAS-BIC/email-api.git
cd email-api

# 2. Instalar dependencias
composer install

# 3. Configurar
cp .env.example .env
# Edita .env y define tu MAIL_API_TOKEN

# 4. Ejecutar (desarrollo)
php -S localhost:8000
```

### Producción (Apache)

Copia los archivos a la raíz web. El `.htaccess` incluido redirige todas las peticiones a `index.php`.

Asegúrate de que `mod_rewrite` esté habilitado y `AllowOverride All` esté configurado para el directorio.

### Producción (Nginx)

```nginx
server {
    listen 80;
    server_name email.tudominio.com;
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

## Configuración

La única configuración requerida es el `MAIL_API_TOKEN` en `.env`:

```ini
MAIL_API_TOKEN=tu_token_secreto_aqui
```

> **Seguridad:** Nunca subas `.env` al repositorio. El `.gitignore` ya lo excluye.

Todas las demás credenciales SMTP se proporcionan **por petición** vía JSON — nada se almacena en el servidor.

---

## Referencia de la API

### `POST /api/send-email`

#### Headers

| Header | Valor |
|---|---|
| `Authorization` | `Bearer <tu_token>` |
| `Content-Type` | `application/json` |

#### Cuerpo

##### Bloque `smtp` (configuración SMTP)

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `host` | string | ✅ | Servidor SMTP (ej: `smtp.hostinger.com`) |
| `port` | integer | ✅ | Puerto (ej: `465` para SSL, `587` para TLS) |
| `username` | string | ✅ | Usuario / email SMTP |
| `password` | string | ✅ | Contraseña SMTP |
| `encryption` | string | ✅ | `"ssl"` o `"tls"` |
| `from_address` | string | ✅ | Email del remitente |
| `from_name` | string | ❌ | Nombre del remitente (por defecto usa `from_address`) |

##### Datos del mensaje

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `to` | string | ✅ | Email del destinatario |
| `to_name` | string | ❌ | Nombre del destinatario |
| `subject` | string | ✅ | Asunto del correo |
| `body` | string | ✅ | **HTML pre-renderizado** — se envía tal cual |
| `is_html` | boolean | ❌ | Por defecto `true` |
| `alt_body` | string | ❌ | Versión en texto plano (fallback) |
| `cc` | string[] | ❌ | Destinatarios en copia |
| `bcc` | string[] | ❌ | Destinatarios en copia oculta |
| `reply_to` | string | ❌ | Dirección de respuesta |

#### Ejemplo de petición

```bash
curl -X POST https://email.tudominio.com/api/send-email \
  -H "Authorization: Bearer tu_token_secreto_aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "smtp": {
      "host": "smtp.hostinger.com",
      "port": 465,
      "username": "info@ejemplo.com",
      "password": "tu_contraseña_smtp",
      "encryption": "ssl",
      "from_address": "info@ejemplo.com",
      "from_name": "Mi App"
    },
    "to": "usuario@ejemplo.com",
    "subject": "Tu factura está lista",
    "body": "<html><body><h1>Hola</h1><p>Detalles de tu factura...</p></body></html>",
    "cc": ["gerente@ejemplo.com"],
    "alt_body": "Hola, tu factura está lista."
  }'
```

#### Respuesta exitosa

```json
{
  "success": true,
  "message": "Correo enviado.",
  "to": "usuario@ejemplo.com"
}
```

#### Respuesta de error

```json
{
  "error": "Validation Error",
  "messages": ["smtp.host requerido.", "\"to\" requerido."]
}
```

Códigos HTTP: `200` OK, `400` JSON inválido, `401` No autorizado, `404` No encontrado, `405` Método no permitido, `422` Error de validación, `500` Error SMTP / del servidor.

---

## Ejemplos de integración

### Laravel

```php
use Illuminate\Support\Facades\Http;

Http::withToken(config('services.email_api.token'))
    ->post('https://email.tudominio.com/api/send-email', [
        'smtp' => [
            'host'         => config('mail.mailers.smtp.host'),
            'port'         => config('mail.mailers.smtp.port'),
            'username'     => config('mail.mailers.smtp.username'),
            'password'     => config('mail.mailers.smtp.password'),
            'encryption'   => config('mail.mailers.smtp.encryption'),
            'from_address' => config('mail.from.address'),
            'from_name'    => config('mail.from.name'),
        ],
        'to'      => $usuario->email,
        'subject' => $mailable->subject,
        'body'    => view('emails.invoice', $data)->render(),
    ]);
```

### JavaScript / Node.js

```javascript
const response = await fetch('https://email.tudominio.com/api/send-email', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer tu_token_secreto_aqui',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    smtp: { host, port, username, password, encryption, from_address, from_name },
    to: 'usuario@ejemplo.com',
    subject: 'Hola',
    body: '<h1>Prueba</h1>',
  }),
});
```

---

## Estructura del proyecto

```
email-api/
├── index.php               ← Punto de entrada (145 líneas)
├── src/
│   ├── helpers.php         ← Cargador de .env
│   ├── auth.php            ← Middleware de Bearer token
│   └── MailService.php     ← Wrapper de PHPMailer
├── templates/
│   └── default.html        ← Ejemplo de plantilla HTML
├── .env.example            ← Plantilla de configuración
├── .htaccess               ← Reglas de reescritura Apache
├── composer.json           ← Dependencias
└── LICENSE                 ← Apache 2.0
```

---

## Seguridad

- **Sin almacenamiento** — las credenciales y el contenido existen solo en memoria durante la petición
- **Bearer token** — todas las peticiones deben incluir un `Authorization` válido
- **.env ignorado** — los secretos nunca llegan al control de versiones
- **Validación de entrada** — todos los campos requeridos se verifican antes de enviar

---

## Licencia

Copyright 2025 [U/SITE.APP SAS BIC](https://u-site.app)

Licenciado bajo Apache License, Version 2.0. Ver [LICENSE](LICENSE) para el texto completo.

---

<div align="center">
  <sub>Hecho con ❤️ por <a href="https://u-site.app">U/SITE.APP</a></sub>
</div>
