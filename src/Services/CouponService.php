<?php

declare(strict_types=1);

/**
 * Validates and applies coupon codes to a cart subtotal.
 *
 * A "cart" passed here is an array shaped like:
 *   [
 *     'subtotal' => float,
 *     'lines' => [
 *        ['storefront_product_id' => int, 'category_ids' => [int,...], 'amount' => float], ...
 *     ],
 *     'customer_phone' => ?string,
 *   ]
 */
final class CouponService
{
    public function __construct(private CouponRepository $repo) {}

    /**
     * @return array{ok:bool, coupon?:array, discount?:float, type?:string, error?:string}
     */
    public function validate(string $code, array $cart): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return ['ok' => false, 'error' => 'Enter a coupon code.'];
        }

        $coupon = $this->repo->getByCode($code);
        if (!$coupon || !(int)$coupon['is_active']) {
            return ['ok' => false, 'error' => 'This coupon code is invalid.'];
        }

        $now = time();
        if (!empty($coupon['starts_at']) && strtotime((string)$coupon['starts_at']) > $now) {
            return ['ok' => false, 'error' => 'This coupon is not active yet.'];
        }
        if (!empty($coupon['ends_at']) && strtotime((string)$coupon['ends_at']) < $now) {
            return ['ok' => false, 'error' => 'This coupon has expired.'];
        }
        if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
            return ['ok' => false, 'error' => 'This coupon is no longer available.'];
        }
        if ($coupon['usage_limit_per_customer'] !== null) {
            $phone = (string)($cart['customer_phone'] ?? '');
            $used = $this->repo->customerUsageCount((int)$coupon['id'], $phone !== '' ? $phone : null);
            if ($used >= (int)$coupon['usage_limit_per_customer']) {
                return ['ok' => false, 'error' => 'You have already used this coupon.'];
            }
        }

        $subtotal = (float)($cart['subtotal'] ?? 0);
        if ($coupon['min_subtotal'] !== null && $subtotal < (float)$coupon['min_subtotal']) {
            return [
                'ok' => false,
                'error' => 'Minimum cart subtotal is LKR ' . number_format((float)$coupon['min_subtotal'], 2) . '.',
            ];
        }

        // Compute the eligible amount (lines covered by the coupon's targeting).
        $eligible = $subtotal;
        if ($coupon['applies_to'] !== 'all') {
            $targets = $this->repo->getTargets((int)$coupon['id']);
            $catIds = array_map(static fn($t) => (int)$t['target_id'], array_filter($targets, static fn($t) => $t['target_type'] === 'category'));
            $spIds = array_map(static fn($t) => (int)$t['target_id'], array_filter($targets, static fn($t) => $t['target_type'] === 'storefront_product'));

            $eligible = 0.0;
            foreach (($cart['lines'] ?? []) as $line) {
                $lineSpId = (int)($line['storefront_product_id'] ?? 0);
                $lineCats = array_map('intval', (array)($line['category_ids'] ?? []));
                $matches = false;
                if ($coupon['applies_to'] === 'products' && in_array($lineSpId, $spIds, true)) $matches = true;
                if ($coupon['applies_to'] === 'categories' && array_intersect($lineCats, $catIds)) $matches = true;
                if ($matches) $eligible += (float)($line['amount'] ?? 0);
            }
            if ($eligible <= 0) {
                return ['ok' => false, 'error' => 'This coupon does not apply to any item in your cart.'];
            }
        }

        $discount = 0.0;
        $type = (string)$coupon['type'];
        switch ($type) {
            case 'percent':
                $discount = $eligible * ((float)$coupon['value'] / 100.0);
                break;
            case 'fixed':
                $discount = (float)$coupon['value'];
                break;
            case 'free_ship':
                $discount = 0.0; // shipping handled by checkout flow; signal via type
                break;
        }

        if ($coupon['max_discount'] !== null && $discount > (float)$coupon['max_discount']) {
            $discount = (float)$coupon['max_discount'];
        }
        if ($discount > $eligible) {
            $discount = $eligible;
        }
        $discount = round($discount, 2);

        return [
            'ok' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'type' => $type,
        ];
    }
}
