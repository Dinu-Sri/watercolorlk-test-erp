<?php

declare(strict_types=1);

/**
 * Google OAuth 2.0 Web flow.
 *  - authorizeUrl(state) — for the "Continue with Google" button
 *  - exchange(code)      — exchange auth code for tokens & decode id_token
 *
 * Configure via constants GOOGLE_OAUTH_CLIENT_ID / SECRET / REDIRECT.
 */
class GoogleOAuth
{
    private const AUTH = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';

    public static function isConfigured(): bool
    {
        return GOOGLE_OAUTH_CLIENT_ID !== ''
            && GOOGLE_OAUTH_CLIENT_SECRET !== ''
            && GOOGLE_OAUTH_REDIRECT !== '';
    }

    public static function authorizeUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
            'redirect_uri'  => GOOGLE_OAUTH_REDIRECT,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'online',
            'prompt'        => 'select_account',
            'state'         => $state,
        ]);
        return self::AUTH . '?' . $params;
    }

    /**
     * @return array{sub:string,email:string,email_verified:bool,name:?string,picture:?string}
     */
    public static function exchange(string $code): array
    {
        $ch = curl_init(self::TOKEN);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_POSTFIELDS => http_build_query([
                'code'          => $code,
                'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
                'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
                'redirect_uri'  => GOOGLE_OAUTH_REDIRECT,
                'grant_type'    => 'authorization_code',
            ]),
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $http >= 400) {
            throw new RuntimeException('Google token exchange failed: ' . ($err ?: $resp));
        }
        $data = json_decode((string)$resp, true);
        if (!is_array($data) || empty($data['id_token'])) {
            throw new RuntimeException('Google response missing id_token');
        }
        $claims = self::decodeIdToken((string)$data['id_token']);
        $aud = (string)($claims['aud'] ?? '');
        if ($aud !== GOOGLE_OAUTH_CLIENT_ID) {
            throw new RuntimeException('Google id_token audience mismatch');
        }
        if (empty($claims['sub']) || empty($claims['email'])) {
            throw new RuntimeException('Google id_token missing subject/email');
        }
        return [
            'sub'            => (string)$claims['sub'],
            'email'          => strtolower((string)$claims['email']),
            'email_verified' => !empty($claims['email_verified']),
            'name'           => $claims['name'] ?? null,
            'picture'        => $claims['picture'] ?? null,
        ];
    }

    /**
     * Decode (without signature verification) a Google-issued JWT.
     * Trust is established by exchanging a freshly-issued code over TLS with our
     * client secret. Signature verification would require fetching JWKs.
     */
    private static function decodeIdToken(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new RuntimeException('Malformed id_token');
        $payload = $parts[1];
        $payload = strtr($payload, '-_', '+/');
        $pad = strlen($payload) % 4;
        if ($pad) $payload .= str_repeat('=', 4 - $pad);
        $json = base64_decode($payload, true);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data)) throw new RuntimeException('Cannot decode id_token');
        return $data;
    }
}
