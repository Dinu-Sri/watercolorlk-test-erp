<?php

declare(strict_types=1);

final class ErpClient
{
    private string $baseUrl;
    private int $clientId;
    private string $clientSecret;
    private string $username;
    private string $password;
    private int $locationId;
    private string $tokenCacheFile;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->clientId = (int)$config['client_id'];
        $this->clientSecret = (string)$config['client_secret'];
        $this->username = (string)$config['username'];
        $this->password = (string)$config['password'];
        $this->locationId = (int)$config['location_id'];
        $this->tokenCacheFile = (string)$config['token_cache_file'];
    }

    public function getProducts(int $perPage = 200, int $page = 1): array
    {
        $token = $this->getToken();
        $path = sprintf('/connector/api/product?location_id=%d&per_page=%d&page=%d', $this->locationId, $perPage, $page);
        $response = $this->request('GET', $path, null, $token);

        $items = $response['data'] ?? $response ?? [];
        $normalized = [];

        foreach ($items as $p) {
            if (($p['is_inactive'] ?? '0') === '1') {
                continue;
            }
            if (($p['not_for_selling'] ?? '0') === '1') {
                continue;
            }

            $variation = $p['product_variations'][0]['variations'][0] ?? [];
            $qty = 0.0;

            foreach (($variation['variation_location_details'] ?? []) as $loc) {
                if (is_array($loc) && isset($loc['qty_available'])) {
                    $qty = (float)$loc['qty_available'];
                    break;
                }
            }

            $normalized[] = [
                'id' => (int)$p['id'],
                'sku' => $p['sku'] ?? ($variation['sub_sku'] ?? ''),
                'name' => $p['name'] ?? '',
                'description' => $p['product_description'] ?? '',
                'category' => $p['category']['name'] ?? '',
                'brand' => $p['brand']['name'] ?? '',
                'unit' => $p['unit']['short_name'] ?? 'Pc',
                'image_url' => $p['image_url'] ?? null,
                'price' => (float)($variation['sell_price_inc_tax'] ?? $variation['default_sell_price'] ?? 0),
                'stock' => $qty,
                'is_active' => 1,
            ];
        }

        return [
            'products' => $normalized,
            'total' => (int)($response['meta']['total'] ?? count($normalized)),
        ];
    }

    public function createOrder(array $orderPayload): array
    {
        $token = $this->getToken();
        return $this->request('POST', ERP_ORDER_ENDPOINT, $orderPayload, $token);
    }

    public function getToken(): string
    {
        if (is_file($this->tokenCacheFile)) {
            $cache = json_decode((string)file_get_contents($this->tokenCacheFile), true);
            if (is_array($cache) && isset($cache['token'], $cache['expires_at']) && time() < (int)$cache['expires_at']) {
                return (string)$cache['token'];
            }
        }

        if ($this->clientSecret === '' || $this->username === '' || $this->password === '') {
            throw new RuntimeException('Missing ERP credentials in environment variables.');
        }

        $response = $this->request('POST', '/oauth/token', [
            'grant_type' => 'password',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'username' => $this->username,
            'password' => $this->password,
            'scope' => '',
        ], null);

        if (!isset($response['access_token'])) {
            throw new RuntimeException('Could not fetch ERP access token.');
        }

        file_put_contents($this->tokenCacheFile, json_encode([
            'token' => $response['access_token'],
            'expires_at' => time() + 3500,
        ]));

        return (string)$response['access_token'];
    }

    private function request(string $method, string $path, ?array $body, ?string $token): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'WatercolorLK-Commerce/1.0',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            throw new RuntimeException('ERP cURL error: ' . $error);
        }

        $decoded = json_decode((string)$raw, true);
        if ($code >= 400) {
            $detail = is_array($decoded) ? json_encode($decoded) : (string)$raw;
            throw new RuntimeException('ERP HTTP ' . $code . ': ' . $detail);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
