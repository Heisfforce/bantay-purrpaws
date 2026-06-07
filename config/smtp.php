<?php
/**
 * Email / SMTP configuration for InfinityFree shared hosting.
 *
 * Recommended: Brevo Transactional API (HTTPS) — works when outbound SMTP is blocked.
 * Optional fallback: SMTP or PHP mail() via MAIL_DRIVER setting.
 *
 * MAIL_DRIVER options:
 *   brevo  — Brevo API only (default, best for InfinityFree)
 *   smtp   — Try Brevo first, then SMTP
 *   mail   — Try Brevo first, then PHP mail()
 */

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function smtp_config(): array {
    return [
        'driver'       => strtolower((string) env_value('MAIL_DRIVER', 'brevo')),
        'from_email'   => (string) env_value('MAIL_FROM', ''),
        'from_name'    => (string) env_value('MAIL_FROM_NAME', 'BantayPurrPaws'),
        'brevo_api_key'=> (string) env_value('BREVO_API_KEY', ''),
        'brevo_api_url'=> 'https://api.brevo.com/v3/smtp/email',
        'smtp_host'    => (string) env_value('SMTP_HOST', ''),
        'smtp_port'    => (int) env_value('SMTP_PORT', 587),
        'smtp_user'    => (string) env_value('SMTP_USER', ''),
        'smtp_pass'    => (string) env_value('SMTP_PASSWORD', ''),
        'smtp_secure'  => strtolower((string) env_value('SMTP_SECURE', 'tls')),
    ];
}

function mail_is_configured(): bool {
    $c = smtp_config();
    if ($c['from_email'] === '') {
        return false;
    }
    if ($c['driver'] === 'brevo' || $c['brevo_api_key'] !== '') {
        return $c['brevo_api_key'] !== '';
    }
    if ($c['driver'] === 'smtp') {
        return $c['smtp_host'] !== '' && $c['smtp_user'] !== '';
    }
    return true;
}

/**
 * Send email via PHP mail() — fallback when API/SMTP unavailable.
 * InfinityFree may restrict mail(); Brevo API is preferred.
 */
function send_via_php_mail(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    $c = smtp_config();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromHeader = $c['from_name'] !== ''
        ? sprintf('%s <%s>', $c['from_name'], $c['from_email'])
        : $c['from_email'];

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $fromHeader,
        'Reply-To: ' . $c['from_email'],
        'X-Mailer: BantayPurrPaws',
    ];

    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
    if (!$ok) {
        error_log('BantayPurrPaws: PHP mail() failed for ' . $to);
    }
    return $ok;
}

/**
 * Minimal SMTP sender using STARTTLS (no Composer dependency).
 * Used only when Brevo API is unavailable and SMTP_* vars are set.
 */
function send_via_smtp(string $to, string $subject, string $htmlBody, string $toName = ''): bool {
    $c = smtp_config();
    if ($c['smtp_host'] === '' || $c['smtp_user'] === '') {
        return false;
    }

    $port   = $c['smtp_port'] ?: 587;
    $secure = $c['smtp_secure'];
    $host   = $c['smtp_host'];

    $transport = ($secure === 'ssl') ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($transport, (int) $port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("BantayPurrPaws SMTP connect failed: {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, 15);

    $read = static function () use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = static function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    $read();
    $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $read();

    if ($secure === 'tls') {
        $write('STARTTLS');
        $resp = $read();
        if (!str_starts_with($resp, '220') && !str_starts_with($resp, '250')) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $read();
    }

    $write('AUTH LOGIN');
    $read();
    $write(base64_encode($c['smtp_user']));
    $read();
    $write(base64_encode($c['smtp_pass']));
    $authResp = $read();
    if (!str_starts_with($authResp, '235')) {
        fclose($socket);
        error_log('BantayPurrPaws SMTP auth failed.');
        return false;
    }

    $from = $c['from_email'];
    $write('MAIL FROM:<' . $from . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $read();
    $write('DATA');
    $read();

    $body = chunk_split(base64_encode($htmlBody));
    $msg  = "From: {$c['from_name']} <{$from}>\r\n";
    $msg .= "To: " . ($toName !== '' ? "{$toName} <{$to}>" : $to) . "\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= $body . "\r\n.";

    $write($msg);
    $dataResp = $read();
    $write('QUIT');
    fclose($socket);

    return str_starts_with($dataResp, '250');
}
