<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class MailService
{
    /**
     * Envía un correo usando la config SMTP y el contenido tal cual llegan.
     *
     * @param array $config  { host, port, username, password, encryption, from_address, from_name? }
     * @param array $data    { to, subject, body, to_name?, is_html?, alt_body?, cc?, bcc?, reply_to? }
     */
    public function send(array $config, array $data): void
    {
        $mailer = new PHPMailer(true);

        // ── SMTP ──────────────────────────────────────────────────────────
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = (int) $config['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];
        $mailer->SMTPSecure = strtolower($config['encryption']) === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';

        // ── Remitente / Destinatario ──────────────────────────────────────
        $mailer->setFrom($config['from_address'], $config['from_name'] ?? $config['from_address']);
        $mailer->addAddress($data['to'], $data['to_name'] ?? '');

        foreach ((array) ($data['cc'] ?? []) as $cc) {
            $mailer->addCC(trim($cc));
        }
        foreach ((array) ($data['bcc'] ?? []) as $bcc) {
            $mailer->addBCC(trim($bcc));
        }
        if (!empty($data['reply_to'])) {
            $mailer->addReplyTo($data['reply_to']);
        }

        // ── Cuerpo — se envía TAL CUAL llega ─────────────────────────────
        $mailer->Subject = $data['subject'];

        $isHtml = $data['is_html'] ?? true;

        if ($isHtml) {
            $mailer->isHTML(true);
            $mailer->Body = $data['body'];                          // HTML de Laravel
            $mailer->AltBody = $data['alt_body'] ?? strip_tags($data['body']); // fallback txt
        } else {
            $mailer->isHTML(false);
            $mailer->Body = $data['body'];
        }

        $mailer->send();
    }
}
