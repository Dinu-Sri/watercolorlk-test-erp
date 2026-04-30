<?php

declare(strict_types=1);

/**
 * Lightweight transactional mailer.
 *
 * Transports:
 *   - 'mail' : PHP mail()
 *   - 'smtp' : minimal socket SMTP (PLAIN/LOGIN auth, STARTTLS or implicit SSL)
 *   - 'log'  : append to storage/mail.log (no real send)
 *
 * Usage:
 *   $m = new Mailer();
 *   $m->send('to@x.com', 'Subject', '<h1>html</h1>');
 */
class Mailer
{
    /**
     * @return array{ok:bool,error?:string}
     */
    public function send(string $to, string $subject, string $html, ?string $text = null): array
    {
        $text ??= trim(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html) ?? ''));
        $from     = MAIL_FROM;
        $fromName = MAIL_FROM_NAME;
        $replyTo  = MAIL_REPLY_TO ?: $from;

        $boundary = 'wlk_' . bin2hex(random_bytes(8));
        $headers = [
            'From'         => $this->encodeAddress($fromName, $from),
            'Reply-To'     => $replyTo,
            'MIME-Version' => '1.0',
            'Content-Type' => "multipart/alternative; boundary=\"$boundary\"",
            'X-Mailer'     => 'WatercolorLK',
            'Date'         => date('r'),
            'Message-ID'   => '<' . bin2hex(random_bytes(12)) . '@' . ($this->host()) . '>',
        ];
        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $text . "\r\n\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= "--$boundary--\r\n";

        $transport = strtolower(MAIL_TRANSPORT);
        try {
            if ($transport === 'log') {
                return $this->writeLog($to, $subject, $headers, $body);
            }
            if ($transport === 'smtp') {
                return $this->sendSmtp($to, $subject, $headers, $body);
            }
            return $this->sendMailFunc($to, $subject, $headers, $body);
        } catch (Throwable $e) {
            $this->writeLog($to, '[FAILED] ' . $subject, $headers, $body, $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function host(): string
    {
        $h = parse_url(SITE_URL, PHP_URL_HOST);
        return is_string($h) && $h !== '' ? $h : 'watercolor.lk';
    }

    private function encodeAddress(string $name, string $email): string
    {
        $clean = trim($name);
        if ($clean === '') return $email;
        return '=?UTF-8?B?' . base64_encode($clean) . "?= <$email>";
    }

    private function sendMailFunc(string $to, string $subject, array $headers, string $body): array
    {
        $hdr = '';
        foreach ($headers as $k => $v) $hdr .= "$k: $v\r\n";
        $subjEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $ok = @mail($to, $subjEnc, $body, rtrim($hdr));
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'mail() returned false'];
    }

    private function writeLog(string $to, string $subject, array $headers, string $body, ?string $err = null): array
    {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $fp = @fopen($dir . '/mail.log', 'ab');
        if (!$fp) return ['ok' => false, 'error' => 'cannot open mail log'];
        fwrite($fp, str_repeat('=', 70) . "\n");
        fwrite($fp, '[' . date('c') . "] To: $to | Subject: $subject\n");
        if ($err) fwrite($fp, "ERROR: $err\n");
        foreach ($headers as $k => $v) fwrite($fp, "$k: $v\n");
        fwrite($fp, "\n" . $body . "\n\n");
        fclose($fp);
        return ['ok' => true];
    }

    /* ===== Minimal SMTP ===== */

    private function sendSmtp(string $to, string $subject, array $headers, string $body): array
    {
        if (SMTP_HOST === '') {
            throw new RuntimeException('SMTP_HOST not configured');
        }
        $headers['To'] = $to;
        $headers['Subject'] = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $remote = SMTP_HOST;
        $port = SMTP_PORT;
        $enc = strtolower(SMTP_ENCRYPTION);
        $transport = ($enc === 'ssl') ? 'ssl://' : '';
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client(
            $transport . $remote . ':' . $port,
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
        );
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: $errno $errstr");
        }
        stream_set_timeout($fp, 15);

        $expect = function (int $code) use ($fp): string {
            $buf = '';
            while (!feof($fp)) {
                $line = fgets($fp, 8192);
                if ($line === false) break;
                $buf .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            if ((int)substr($buf, 0, 3) !== $code) {
                throw new RuntimeException('SMTP unexpected: ' . trim($buf));
            }
            return $buf;
        };
        $send = function (string $cmd) use ($fp): void { fwrite($fp, $cmd . "\r\n"); };

        $expect(220);
        $send('EHLO ' . $this->host());
        $expect(250);

        if ($enc === 'tls') {
            $send('STARTTLS');
            $expect(220);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            $send('EHLO ' . $this->host());
            $expect(250);
        }

        if (SMTP_USER !== '') {
            $send('AUTH LOGIN');
            $expect(334);
            $send(base64_encode(SMTP_USER));
            $expect(334);
            $send(base64_encode(SMTP_PASS));
            $expect(235);
        }

        $send('MAIL FROM:<' . MAIL_FROM . '>');
        $expect(250);
        $send('RCPT TO:<' . $to . '>');
        $expect(250);
        $send('DATA');
        $expect(354);

        $hdr = '';
        foreach ($headers as $k => $v) $hdr .= "$k: $v\r\n";
        $payload = $hdr . "\r\n" . $body;
        // Dot-stuffing per RFC 5321
        $payload = preg_replace('/^\./m', '..', $payload);
        fwrite($fp, $payload . "\r\n.\r\n");
        $expect(250);

        $send('QUIT');
        fclose($fp);
        return ['ok' => true];
    }

    /* ===== Templates ===== */

    public function renderLayout(string $title, string $bodyHtml, string $ctaUrl = '', string $ctaLabel = ''): string
    {
        $site = htmlspecialchars(SITE_NAME);
        $cta = '';
        if ($ctaUrl !== '' && $ctaLabel !== '') {
            $cta = '<p style="margin:24px 0;"><a href="' . htmlspecialchars($ctaUrl) . '" style="display:inline-block;padding:12px 22px;background:#0f2440;color:#fff;text-decoration:none;border-radius:8px;font:700 14px/1 Arial,sans-serif;">' . htmlspecialchars($ctaLabel) . '</a></p>';
        }
        return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;color:#1a2230;">'
             . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;"><tr><td align="center">'
             . '<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 6px 20px rgba(15,36,64,.08);">'
             . '<tr><td style="padding:24px 30px;background:#0f2440;color:#fff;font:800 18px/1.2 Arial,sans-serif;">' . $site . '</td></tr>'
             . '<tr><td style="padding:30px;font:400 15px/1.55 Arial,sans-serif;color:#1a2230;">'
             . '<h1 style="margin:0 0 14px;font:800 22px/1.2 Arial,sans-serif;color:#0f2440;">' . htmlspecialchars($title) . '</h1>'
             . $bodyHtml
             . $cta
             . '<hr style="border:0;border-top:1px solid #e6eaf0;margin:24px 0;">'
             . '<p style="margin:0;color:#7a8699;font-size:12px;">If you did not expect this email, you can safely ignore it.</p>'
             . '</td></tr>'
             . '<tr><td style="padding:14px 30px;background:#f4f6f9;color:#7a8699;font:400 12px/1.4 Arial,sans-serif;">'
             . '&copy; ' . date('Y') . ' ' . $site . '</td></tr>'
             . '</table></td></tr></table></body></html>';
    }
}
