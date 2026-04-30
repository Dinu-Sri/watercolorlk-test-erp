<?php

declare(strict_types=1);

/**
 * Auth-attempt rate limiter.
 *
 * Records each attempt to `auth_attempts` and refuses further attempts when
 * the configured threshold (per IP and/or per email) is exceeded inside a
 * sliding window.
 *
 * Usage:
 *   $rl = new RateLimiter(appDb());
 *   $check = $rl->check('login', $email);   // ['ok'=>bool, 'retry_after'=>int]
 *   if (!$check['ok']) { ... 429 ... }
 *   $rl->record('login', $email, $success);
 */
final class RateLimiter
{
    private const RULES = [
        // kind => [ ip_max_per_window, email_max_per_window, window_seconds ]
        'login'  => [10, 8,  900],   // 10/IP and 8/email per 15 min
        'forgot' => [5,  3,  900],   // 5/IP and 3/email per 15 min
        'reset'  => [10, 5,  900],   // 10/IP and 5/email per 15 min
        'signup' => [5,  3,  900],   // 5/IP and 3/email per 15 min
        'google' => [20, 0,  900],   // 20/IP per 15 min (no email key)
    ];

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{ok:bool, retry_after:int, by:string|null}
     */
    public function check(string $kind, ?string $email = null, ?string $ip = null): array
    {
        if (!isset(self::RULES[$kind])) {
            return ['ok' => true, 'retry_after' => 0, 'by' => null];
        }
        $ip = $ip ?? $this->clientIp();
        [$ipMax, $emailMax, $window] = self::RULES[$kind];

        try {
            // IP check
            if ($ipMax > 0) {
                $st = $this->db->prepare(
                    "SELECT COUNT(*) AS c, MIN(created_at) AS first_at
                       FROM auth_attempts
                      WHERE ip = :ip AND kind = :k
                        AND created_at >= NOW() - INTERVAL :w SECOND"
                );
                $st->bindValue(':ip', $ip);
                $st->bindValue(':k', $kind);
                $st->bindValue(':w', $window, PDO::PARAM_INT);
                $st->execute();
                $row = $st->fetch();
                if ($row && (int)$row['c'] >= $ipMax) {
                    $retry = $this->retryAfter((string)$row['first_at'], $window);
                    return ['ok' => false, 'retry_after' => $retry, 'by' => 'ip'];
                }
            }

            // Email check
            if ($emailMax > 0 && $email !== null && $email !== '') {
                $hash = $this->hashEmail($email);
                $st = $this->db->prepare(
                    "SELECT COUNT(*) AS c, MIN(created_at) AS first_at
                       FROM auth_attempts
                      WHERE email_hash = :h AND kind = :k
                        AND created_at >= NOW() - INTERVAL :w SECOND"
                );
                $st->bindValue(':h', $hash);
                $st->bindValue(':k', $kind);
                $st->bindValue(':w', $window, PDO::PARAM_INT);
                $st->execute();
                $row = $st->fetch();
                if ($row && (int)$row['c'] >= $emailMax) {
                    $retry = $this->retryAfter((string)$row['first_at'], $window);
                    return ['ok' => false, 'retry_after' => $retry, 'by' => 'email'];
                }
            }
        } catch (Throwable $e) {
            // If table is missing or DB hiccups, fail open (don't lock users out).
            error_log('RateLimiter::check failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'retry_after' => 0, 'by' => null];
    }

    public function record(string $kind, ?string $email, bool $success, ?string $ip = null): void
    {
        if (!isset(self::RULES[$kind])) return;
        $ip = $ip ?? $this->clientIp();
        try {
            $st = $this->db->prepare(
                "INSERT INTO auth_attempts (ip, email_hash, kind, success, created_at)
                 VALUES (:ip, :h, :k, :s, NOW())"
            );
            $st->execute([
                ':ip' => $ip,
                ':h'  => $email !== null && $email !== '' ? $this->hashEmail($email) : null,
                ':k'  => $kind,
                ':s'  => $success ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('RateLimiter::record failed: ' . $e->getMessage());
        }
    }

    public function purgeOlderThan(int $days = 7): int
    {
        try {
            $st = $this->db->prepare(
                "DELETE FROM auth_attempts WHERE created_at < NOW() - INTERVAL :d DAY"
            );
            $st->bindValue(':d', $days, PDO::PARAM_INT);
            $st->execute();
            return $st->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    private function retryAfter(string $firstAtSql, int $window): int
    {
        $first = strtotime($firstAtSql) ?: time();
        $retry = ($first + $window) - time();
        return max(1, $retry);
    }

    private function clientIp(): string
    {
        // Trust REMOTE_ADDR by default. If running behind Cloudflare/proxy, you
        // can adapt to use CF-Connecting-IP / X-Forwarded-For (after validating
        // the proxy chain). For now we keep it conservative.
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : '0.0.0.0';
    }
}
