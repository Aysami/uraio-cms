<?php
/**
 * Email delivery with editable templates. Uses PHP mail() by default and a
 * lightweight SMTP transport when SMTP credentials are configured.
 */

function email_templates(): array
{
    return db_all('SELECT * FROM email_templates ORDER BY name ASC');
}

function render_email_template(string|array $template, array $vars): array
{
    if (is_string($template)) {
        $template = db_row("SELECT * FROM email_templates WHERE slug = ?", [$template]) ?? [];
    }
    $subject = $template['subject'] ?? '';
    $body = $template['body'] ?? '';
    $vars += ['app_name' => 'Uraio Business OS', 'year' => date('Y'), 'app_url' => app_url('')];
    foreach ($vars as $key => $value) {
        $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
        $body = str_replace('{{' . $key . '}}', (string) $value, $body);
    }
    return ['subject' => $subject, 'body' => $body];
}

function send_email_template(string $slug, string $to, array $vars = []): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $template = db_row("SELECT * FROM email_templates WHERE slug = ? AND status = 'active'", [$slug]);
    if (!$template) {
        $template = [
            'subject' => '{{app_name}} notification',
            'body' => '<p>Hello {{name}},</p><p>{{message}}</p>',
        ];
        $vars += ['message' => 'You have a new notification from Uraio Business OS.'];
    }
    $rendered = render_email_template($template, $vars);
    return send_mail($to, $rendered['subject'], $rendered['body']);
}

function send_mail(string $to, string $subject, string $htmlBody): bool
{
    $from = (string) config('mail.from', 'no-reply@uraio.com');
    $fromName = (string) config('mail.from_name', 'Uraio Business OS');
    $logLine = sprintf("[%s] to=%s subject=%s\n", date('c'), $to, $subject);
    @file_put_contents(BASE_PATH . '/logs/mail.log', $logLine, FILE_APPEND);

    $smtpHost = (string) config('mail.smtp_host', '');
    if ($smtpHost !== '') {
        return smtp_send($to, $subject, $htmlBody, $from, $fromName);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: Uraio-Business-OS',
    ];
    return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

function smtp_send(string $to, string $subject, string $htmlBody, string $from, string $fromName): bool
{
    $host = (string) config('mail.smtp_host');
    $port = (int) config('mail.smtp_port', 587);
    $secure = (string) config('mail.smtp_secure', 'tls');
    $user = (string) config('mail.smtp_user', '');
    $pass = (string) config('mail.smtp_pass', '');
    $prefix = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 20);
    if (!$socket) {
        error_log('SMTP connect failed: ' . $errstr);
        return false;
    }
    stream_set_timeout($socket, 20);
    $read = function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $expect = [250]) use ($socket, $read): bool {
        fwrite($socket, $command . "\r\n");
        $response = $read();
        $code = (int) substr($response, 0, 3);
        return in_array($code, $expect, true);
    };
    if ((int) substr($read(), 0, 3) !== 220) {
        fclose($socket);
        return false;
    }
    $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!$cmd('EHLO ' . $ehloHost, [250])) {
        $cmd('HELO ' . $ehloHost, [250]);
    }
    if ($secure === 'tls') {
        if (!$cmd('STARTTLS', [220]) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    if ($user !== '') {
        if (!$cmd('AUTH LOGIN', [334]) || !$cmd(base64_encode($user), [334]) || !$cmd(base64_encode($pass), [235])) {
            fclose($socket);
            return false;
        }
    }
    if (!$cmd('MAIL FROM:<' . $from . '>', [250]) || !$cmd('RCPT TO:<' . $to . '>', [250, 251])) {
        fclose($socket);
        return false;
    }
    if (!$cmd('DATA', [354])) {
        fclose($socket);
        return false;
    }
    $headers = 'From: ' . $fromName . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $subject . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
        . 'Date: ' . date('r') . "\r\n";
    $data = $headers . "\r\n" . preg_replace('/^\./m', '..', $htmlBody) . "\r\n.";
    $ok = $cmd($data, [250]);
    $cmd('QUIT', [221]);
    fclose($socket);
    return $ok;
}

function email_layout(string $heading, string $bodyHtml, string $buttonText = '', string $buttonUrl = ''): string
{
    $button = $buttonText !== '' && $buttonUrl !== ''
        ? '<p style="margin:28px 0"><a href="' . e($buttonUrl) . '" style="background:#0f766e;color:#fff;padding:13px 22px;border-radius:10px;text-decoration:none;font-weight:600;display:inline-block">' . e($buttonText) . '</a></p>'
        : '';
    return '<!doctype html><html><body style="margin:0;background:#f4f7f6;font-family:Arial,sans-serif;color:#16211f">'
        . '<div style="max-width:600px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4ebe8">'
        . '<div style="padding:22px 28px;background:#0b1220;color:#fff;font-size:19px;font-weight:700">Uraio Business OS</div>'
        . '<div style="padding:30px 28px"><h1 style="font-size:23px;margin:0 0 16px">' . e($heading) . '</h1>'
        . '<div style="font-size:15px;line-height:1.65;color:#3c4a47">' . $bodyHtml . '</div>' . $button
        . '<p style="margin-top:32px;font-size:12px;color:#87938f">Uraio Technology Limited · Tech Made Easy.</p></div></div></body></html>';
}
