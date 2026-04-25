<?php
/**
 * ERP Proxy for Watercolor.LK
 * Place this file on the same server as watercolorlk-store.html
 * It forwards requests to erppro.lk to avoid browser CORS restrictions.
 */

// ── Allow same-origin calls from the store page ──
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Config ──
define('ERP_BASE',       'https://erppro.lk/public');
define('CLIENT_ID',      3);
define('CLIENT_SECRET',  'yBlzajjA0ZdR19xZoVrvMokROKXSBEjV6a9vY41D');
define('USERNAME',       'dinu');
define('PASSWORD',       'prime1@PROJECT');
define('LOCATION_ID',    5);
define('TOKEN_CACHE',    __DIR__ . '/.erp_token_cache.json');

// ── Route ──
$action = $_GET['action'] ?? 'products';

if ($action === 'products') {
    echo json_encode(getProducts());
} elseif ($action === 'product' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    echo json_encode(getSingleProduct($id));
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}

// ── Token (cached for 1 hour) ──
function getToken(): string {
    if (file_exists(TOKEN_CACHE)) {
        $cache = json_decode(file_get_contents(TOKEN_CACHE), true);
        if ($cache && isset($cache['token'], $cache['expires_at']) && time() < $cache['expires_at']) {
            return $cache['token'];
        }
    }

    $res = erpRequest('POST', '/oauth/token', [
        'grant_type'    => 'password',
        'client_id'     => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'username'      => USERNAME,
        'password'      => PASSWORD,
        'scope'         => '',
    ], null);

    if (empty($res['access_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'ERP authentication failed', 'detail' => $res]);
        exit;
    }

    file_put_contents(TOKEN_CACHE, json_encode([
        'token'      => $res['access_token'],
        'expires_at' => time() + 3600,
    ]));

    return $res['access_token'];
}

// ── Fetch all products at BL0002 ──
function getProducts(): array {
    $token = getToken();
    $per_page = (int)($_GET['per_page'] ?? 200);
    $page     = (int)($_GET['page'] ?? 1);

    $res = erpRequest('GET',
        '/connector/api/product?location_id=' . LOCATION_ID . '&per_page=' . $per_page . '&page=' . $page,
        null, $token
    );

    // Normalize for the frontend
    $products = [];
    $items = $res['data'] ?? $res ?? [];

    foreach ($items as $p) {
        $variation = $p['product_variations'][0]['variations'][0] ?? [];
        $locDetails = $variation['variation_location_details'] ?? [];

        $qty = 0;
        foreach ($locDetails as $loc) {
            if (is_array($loc) && isset($loc['qty_available'])) {
                $qty = (float)$loc['qty_available'];
                break;
            }
        }

        if (($p['is_inactive'] ?? '0') === '1') continue;
        if (($p['not_for_selling'] ?? '0') === '1') continue;

        $products[] = [
            'id'          => $p['id'],
            'name'        => $p['name'],
            'sku'         => $p['sku'] ?? ($variation['sub_sku'] ?? ''),
            'price'       => (float)($variation['sell_price_inc_tax'] ?? $variation['default_sell_price'] ?? 0),
            'image_url'   => $p['image_url'] ?? null,
            'description' => $p['product_description'] ?? '',
            'category'    => $p['category']['name'] ?? '',
            'brand'       => $p['brand']['name'] ?? '',
            'unit'        => $p['unit']['short_name'] ?? 'Pc',
            'weight'      => $p['weight'] ?? null,
            'stock'       => $qty,
            'type'        => $p['type'] ?? 'single',
        ];
    }

    return [
        'success'  => true,
        'total'    => $res['meta']['total'] ?? count($products),
        'products' => $products,
    ];
}

// ── Fetch single product ──
function getSingleProduct(int $id): array {
    $token = getToken();
    $res = erpRequest('GET', '/connector/api/product/' . $id, null, $token);
    return ['success' => true, 'product' => $res];
}

// ── HTTP helper ──
function erpRequest(string $method, string $path, ?array $body, ?string $token): array {
    $url = ERP_BASE . $path;
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'WatercolorLK-Proxy/1.0',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        http_response_code(502);
        echo json_encode(['error' => 'cURL error: ' . $err]);
        exit;
    }

    $data = json_decode($raw, true);
    if ($code >= 400) {
        http_response_code($code);
        echo json_encode(['error' => 'ERP returned ' . $code, 'detail' => $data]);
        exit;
    }

    return $data ?? [];
}
