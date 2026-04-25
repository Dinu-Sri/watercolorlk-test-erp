# Watercolor.LK ERP API Reference

This document is a working reference for integrating the Watercolor.LK ecommerce site with the UltimatePOS / ERP API running at `https://erppro.lk/public`.

It is based on tested requests against the live ERP instance and the current Watercolor.LK business location.

## 1. Environment Summary

- ERP base URL: `https://erppro.lk/public`
- Store location code: `BL0002`
- Store location numeric ID: `5`
- Business name: `Watercolor.LK`
- Product endpoint root: `/connector/api`
- OAuth token endpoint: `/oauth/token`

## 2. Important Security Note

Do not expose ERP credentials, client secret, or user password directly in frontend JavaScript for production use.

Use a server-side proxy like [erp-proxy.php](erp-proxy.php) to:

- authenticate with the ERP
- cache the token
- call ERP endpoints server-side
- return only the data needed by the storefront

The current storefront already uses this pattern.

## 3. Authentication

The ERP is working with OAuth2 password grant.

### Token Endpoint

`POST https://erppro.lk/public/oauth/token`

### Request Body

```json
{
  "grant_type": "password",
  "client_id": 3,
  "client_secret": "YOUR_CLIENT_SECRET",
  "username": "YOUR_USERNAME",
  "password": "YOUR_PASSWORD",
  "scope": ""
}
```

### Successful Response Shape

```json
{
  "token_type": "Bearer",
  "expires_in": 31536000,
  "access_token": "...",
  "refresh_token": "..."
}
```

### Required Header For Protected Endpoints

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

## 4. Working API Base Pattern

Protected ecommerce endpoints are available under:

`https://erppro.lk/public/connector/api/...`

The tested product listing endpoint is:

`GET /connector/api/product`

This is the correct route. The following route does not work:

`GET /api/product`

## 5. Business Locations

### Endpoint

`GET /connector/api/business-location`

### Purpose

Use this to discover location IDs and codes when filtering products for a specific store.

### Confirmed Locations

- `id: 4` -> `BL0001` -> `Clossyan Technologies (PVT) Ltd`
- `id: 5` -> `BL0002` -> `Watercolor.LK`
- `id: 6` -> `BL0003` -> `Clossyan Electronics`

For the ecommerce storefront, use:

- `location_id=5`

## 6. Product Listing

### Endpoint

`GET /connector/api/product?location_id=5&per_page=200`

### Purpose

Returns products available for the Watercolor.LK location.

### Confirmed Result

- Total products at Watercolor.LK: `92`

### Pagination

The API returns pagination metadata under `meta`.

Example:

```json
{
  "data": [...],
  "links": {...},
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 200,
    "total": 94
  }
}
```

Typical query parameters:

- `location_id=5`
- `per_page=200`
- `page=1`

### Example Request

```http
GET https://erppro.lk/public/connector/api/product?location_id=5&per_page=200
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

## 7. Product Detail

The API documentation suggests single-product access using an ID route.

Expected pattern:

`GET /connector/api/product/{id}`

In the current storefront implementation, product detail pages are generated from the cached full product list returned by the listing endpoint, which avoids a second request for each page load.

That is acceptable for the current catalog size.

For a larger catalog later, switch product pages to direct per-product requests.

## 8. Product Response Structure

A product object contains more data than the storefront currently uses.

### Important Top-Level Fields

- `id`
- `name`
- `sku`
- `type`
- `enable_stock`
- `alert_quantity`
- `image`
- `image_url`
- `product_description`
- `weight`
- `is_inactive`
- `not_for_selling`
- `brand`
- `unit`
- `category`
- `sub_category`
- `product_locations`
- `product_variations`

### Pricing Fields

Pricing is inside:

- `product_variations[0].variations[0].default_sell_price`
- `product_variations[0].variations[0].sell_price_inc_tax`

Use `sell_price_inc_tax` if present.

### Stock Fields

Stock is inside:

- `product_variations[0].variations[0].variation_location_details`

For Watercolor.LK, stock must be read from the location detail matching `location_id = 5`.

Example structure:

```json
{
  "variation_location_details": [
    {
      "location_id": 5,
      "qty_available": "1.0000"
    }
  ]
}
```

### Image Fields

- `image_url` is the main field to use for frontend display.
- If no custom image exists, the ERP may return a default placeholder image such as:

`https://erppro.lk/public/img/default.png`

Frontend logic should detect that and use a better store placeholder image if needed.

## 9. Suggested Ecommerce Mapping

These are the recommended storefront mappings.

### Catalog Card

- Product name -> `name`
- SKU -> `sku`
- Price -> `sell_price_inc_tax`
- Product image -> `image_url`
- Stock badge -> derived from `qty_available`

### Product Page

- Title -> `name`
- Subtitle / brand -> `brand.name`
- Description -> `product_description`
- Unit -> `unit.short_name`
- Category -> `category.name`
- Weight -> `weight`
- Purchase CTA -> custom ecommerce logic
- WhatsApp CTA -> custom storefront logic

### Inventory Rules

- Hide or mark out of stock when `qty_available <= 0`
- Show low stock urgency when quantity is small, for example `<= 3`
- Exclude products where `is_inactive = 1`
- Exclude products where `not_for_selling = 1`

## 10. Other Relevant Endpoints

The storefront tester was prepared to support these connector endpoints as well:

- `GET /connector/api/product`
- `GET /connector/api/category`
- `GET /connector/api/brand`
- `GET /connector/api/unit`
- `GET /connector/api/customer`
- `GET /connector/api/supplier`
- `GET /connector/api/location`
- `GET /connector/api/business-location`

Not all were fully exercised during this setup, but the connector namespace is the correct family of routes.

## 11. Example Implementations

### JavaScript Fetch Example

```js
const response = await fetch(
  'https://erppro.lk/public/connector/api/product?location_id=5&per_page=200',
  {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json'
    }
  }
);

const payload = await response.json();
```

### PHP cURL Example

```php
$ch = curl_init('https://erppro.lk/public/connector/api/product?location_id=5&per_page=200');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]
]);

$raw = curl_exec($ch);
$data = json_decode($raw, true);
curl_close($ch);
```

### PowerShell Example

```powershell
$headers = @{
  Authorization = "Bearer $token"
  Accept = "application/json"
}

$response = Invoke-WebRequest \
  -Uri "https://erppro.lk/public/connector/api/product?location_id=5&per_page=200" \
  -Headers $headers \
  -UseBasicParsing

$data = $response.Content | ConvertFrom-Json
```

## 12. Current Project Files

### Storefront

- [index.html](index.html) -> product listing page
- [product.html](product.html) -> product detail page

### API / Integration

- [erp-proxy.php](erp-proxy.php) -> production-safe server-side proxy
- [api-test.html](api-test.html) -> manual API tester

## 13. Recommended Architecture For Full Ecommerce Later

For a more complete ecommerce build, use this structure.

### Server Side

- keep ERP credentials only on the server
- create a dedicated backend or extend [erp-proxy.php](erp-proxy.php)
- cache product responses when possible
- normalize ERP fields into ecommerce-friendly JSON

### Frontend

- product listing page
- product detail page
- category pages
- cart state
- checkout flow
- search and filters
- wishlist

### Backend Features To Add Later

- product detail endpoint with slug support
- category aggregation
- related products logic
- stock refresh jobs
- webhook or scheduled sync from ERP
- order write-back or order export integration

## 14. CORS / Deployment Notes

Direct browser requests from `watercolor.lk` to `erppro.lk` are blocked by CORS.

That is why production must use the same-origin proxy approach:

`Browser -> watercolor.lk/api/erp-proxy.php -> erppro.lk`

This is already the correct production pattern.

## 15. Practical Rules For Future Development

- always filter products by `location_id=5` for Watercolor.LK
- always treat ERP auth as server-side only
- always normalize image, price, and stock before returning frontend JSON
- never trust raw ERP response shape directly in frontend UI code
- prepare for missing category, brand, description, and image values
- prefer cached list responses for the current small catalog
- move to dedicated detail endpoints if the catalog grows significantly

## 16. Quick Checklist

Before building new ecommerce features, confirm:

- ERP token request still works
- connector product endpoint still works
- Watercolor.LK location ID is still `5`
- proxy is uploaded and accessible
- PHP cURL is enabled on hosting
- returned products still include price and stock fields

## 17. Known Verified Facts

- OAuth password grant works
- `GET /connector/api/product` works
- `location_id=5` correctly filters Watercolor.LK products
- product count verified at Watercolor.LK during setup: `92`
- browser-to-ERP direct requests fail because of CORS
- proxy-based integration works around CORS correctly
