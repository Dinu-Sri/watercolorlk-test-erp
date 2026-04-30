<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Checkout - Watercolor.LK</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="assets/images/brand/logo-watercolorlk.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800;900&family=Playfair+Display:wght@600;700;800&family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php include __DIR__ . '/partials/chrome-styles.php'; ?>
<style>
/* ====== Single-screen Shopify-style checkout ====== */
body.is-checkout { background: #faf6f0; }
body.is-checkout .site-header { box-shadow: 0 1px 0 rgba(0,0,0,.04); }
/* hide footer body, keep only thin copyright row */
body.is-checkout .footer-trust-band,
body.is-checkout .footer-main .footer-grid,
body.is-checkout .footer-main .footer-payments { display: none; }
body.is-checkout .site-footer { background: transparent; color: #6b7388; padding: 0; border-top: 1px solid var(--line); }
body.is-checkout .site-footer .footer-main { padding: 8px 0; }
body.is-checkout .site-footer .footer-bottom { color: #6b7388; padding-top: 0; border-top: 0; font-size: .78rem; }
body.is-checkout .bottom-nav { display: none !important; }

.cx-wrap { padding: 14px 0 28px; }
.cx-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.cx-bar h1 { margin: 0; color: var(--brand-navy-deep); font: 800 1.4rem/1.1 'Playfair Display', serif; }
.cx-bar .back { color: var(--brand-navy); text-decoration: none; font: 700 .86rem/1 'Montserrat', sans-serif; display: inline-flex; align-items: center; gap: 4px; }
.cx-bar .back:hover { color: var(--amber-deep); }
.cx-secure { color: #6b7388; font: 600 .8rem/1 'Source Sans 3', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
.cx-secure svg { color: var(--accent-mint); }

.cx-grid { display: grid; grid-template-columns: 1.35fr 1fr; gap: 20px; align-items: start; }

.cx-card {
    background: #fff; border: 1px solid var(--line); border-radius: 14px;
    padding: 14px 16px; box-shadow: var(--shadow-sm); margin-bottom: 12px;
}
.cx-card h2 {
    margin: 0 0 10px; color: var(--brand-navy-deep);
    font: 800 .95rem/1.2 'Montserrat', sans-serif; letter-spacing: .04em; text-transform: uppercase;
    display: flex; align-items: center; gap: 8px;
}
.cx-card h2 .num {
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--amber); color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font: 800 .76rem/1 'Montserrat', sans-serif;
}

.cx-row { display: grid; gap: 10px; }
.cx-row.two { grid-template-columns: 1fr 1fr; }
.cx-field { display: block; }
.cx-field label {
    display: block; margin-bottom: 4px; color: #4a5468;
    font: 700 .76rem/1 'Montserrat', sans-serif; letter-spacing: .03em;
}
.cx-field label .opt { color: #98a1b3; font-weight: 600; margin-left: 4px; }
.cx-field input, .cx-field select, .cx-field textarea {
    width: 100%; box-sizing: border-box; padding: 10px 12px;
    border: 1px solid var(--line); border-radius: 10px; background: #fff;
    font: 600 .94rem/1.2 'Source Sans 3', sans-serif; color: var(--brand-navy-deep);
    outline: none; transition: border-color .15s, box-shadow .15s;
}
.cx-field input:focus, .cx-field select:focus, .cx-field textarea:focus {
    border-color: var(--amber); box-shadow: 0 0 0 3px rgba(232,118,10,.15);
}
.cx-field.invalid input, .cx-field.invalid select, .cx-field.invalid textarea {
    border-color: #b8232f; background: #fff5f5;
}
.cx-field .err {
    display: none; color: #b8232f; font: 600 .76rem/1.3 'Source Sans 3', sans-serif; margin-top: 4px;
}
.cx-field.invalid .err { display: block; }
.cx-field .hint {
    color: #6b7388; font: 500 .76rem/1.3 'Source Sans 3', sans-serif; margin-top: 4px;
    display: inline-flex; align-items: center; gap: 4px;
}
.cx-field .hint svg { width: 12px; height: 12px; color: #25d366; }

/* Payment radios as cards */
.pay-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.pay-options label {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    border: 2px solid var(--line); border-radius: 12px; padding: 10px 8px;
    cursor: pointer; background: #fff;
    transition: border-color .15s, background .15s;
    text-align: center;
}
.pay-options label:hover { border-color: var(--amber); }
.pay-options input { position: absolute; opacity: 0; pointer-events: none; }
.pay-options input:checked + .pc-body {
    color: var(--brand-navy);
}
.pay-options label:has(input:checked) {
    border-color: var(--amber-deep);
    background: linear-gradient(180deg, #fffaf0, #fff);
    box-shadow: 0 0 0 3px rgba(232,118,10,.12);
}
.pay-options .pc-body { display: contents; }
.pay-options svg, .pay-options .pc-icon { width: 26px; height: 26px; color: var(--brand-navy); }
.pay-options strong { color: var(--brand-navy-deep); font: 800 .82rem/1 'Montserrat', sans-serif; }
.pay-options span { color: #6b7388; font: 600 .7rem/1.2 'Source Sans 3', sans-serif; }

/* Notes collapsible */
details.cx-notes summary {
    cursor: pointer; list-style: none; color: var(--amber-deep);
    font: 700 .82rem/1 'Montserrat', sans-serif; padding: 4px 0;
    display: inline-flex; align-items: center; gap: 6px;
}
details.cx-notes summary::-webkit-details-marker { display: none; }
details.cx-notes[open] summary { color: var(--brand-navy); }
details.cx-notes textarea { margin-top: 6px; min-height: 64px; resize: vertical; }

/* Submit */
.cx-submit {
    width: 100%; padding: 15px; border: 0; border-radius: 14px; cursor: pointer; color: #fff;
    background: linear-gradient(180deg, #ff5b3a, #b8232f);
    font: 800 1.05rem/1.2 'Montserrat', sans-serif; letter-spacing: .04em;
    box-shadow: 0 12px 26px rgba(184,35,47,.32);
    margin-top: 4px;
}
.cx-submit:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
.cx-submit .spinner {
    display: none; width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,.45); border-top-color: #fff;
    animation: spin 1s linear infinite; margin-right: 8px; vertical-align: -3px;
}
.cx-submit.is-loading .spinner { display: inline-block; }
.cx-submit.is-loading span.lbl { opacity: .8; }
@keyframes spin { to { transform: rotate(360deg); } }
.cx-error { color: #b8232f; font: 600 .88rem/1.3 'Source Sans 3', sans-serif; margin-top: 8px; min-height: 16px; }

/* Right column summary */
.cx-summary {
    position: sticky; top: 84px;
    background: #fff; border: 1px solid var(--line); border-radius: 14px;
    padding: 16px; box-shadow: var(--shadow-sm);
}
.cx-summary h2 { margin: 0 0 10px; color: var(--brand-navy-deep); font: 800 1rem/1.2 'Playfair Display', serif; }
.cx-items { display: grid; gap: 12px; max-height: 38vh; overflow-y: auto; padding: 8px 4px 4px 8px; }
.cx-items .it { display: grid; grid-template-columns: 56px 1fr auto; gap: 12px; align-items: center; }
.cx-items .it img { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; background: #f3eee6; border: 1px solid var(--line); display: block; }
.cx-items .it .qpip {
    position: relative; display: inline-block; line-height: 0;
}
.cx-items .it .qpip::after {
    content: attr(data-q);
    position: absolute; top: -7px; right: -7px;
    background: var(--brand-navy); color: #fff; min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 999px;
    font: 800 .72rem/20px 'Montserrat', sans-serif; text-align: center;
    box-shadow: 0 2px 6px rgba(16,32,58,.3);
    box-sizing: border-box;
}
.cx-items .it .nm { color: var(--brand-navy-deep); font: 700 .82rem/1.25 'Source Sans 3', sans-serif; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.cx-items .it .pr { color: var(--brand-navy); font: 800 .85rem/1 'Source Sans 3', sans-serif; white-space: nowrap; }
.cx-summary hr { border: 0; border-top: 1px dashed var(--line); margin: 10px 0; }
.cx-summary .ln { display: flex; justify-content: space-between; padding: 4px 0; color: #4a5468; font: 600 .88rem/1.3 'Source Sans 3', sans-serif; }
.cx-summary .ln strong { color: var(--brand-navy); font-weight: 800; }
.cx-summary .ship-free { color: #17633e; font-weight: 800; }
.cx-summary .total { display: flex; justify-content: space-between; align-items: baseline; margin-top: 4px; padding-top: 8px; border-top: 1px dashed var(--line); }
.cx-summary .total .lbl { color: var(--brand-navy-deep); font: 800 .88rem/1 'Montserrat', sans-serif; letter-spacing: .04em; text-transform: uppercase; }
.cx-summary .total .amt { color: #b8232f; font: 900 1.4rem/1 'Source Sans 3', sans-serif; }
.cx-summary .edit-cart { display: inline-block; margin-top: 4px; color: var(--amber-deep); text-decoration: none; font: 700 .8rem/1 'Montserrat', sans-serif; }
.cx-summary .edit-cart:hover { text-decoration: underline; }
.cx-summary .trust-mini { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; align-items: center; }
.cx-summary .trust-mini .chip { padding: 4px 9px; border-radius: 999px; border: 1px solid var(--line); font: 700 .72rem/1 'Montserrat', sans-serif; color: #4a5468; }
.cx-summary .payhere-banner { display: block; margin-top: 12px; }
.cx-summary .payhere-banner img { width: 100%; max-width: 100%; height: auto; display: block; border-radius: 8px; }

/* Empty cart redirect notice */
.cx-empty {
    background: #fff; border: 1px dashed #d9cab8; border-radius: 14px;
    padding: 24px; text-align: center; color: #6b7388;
    font: 600 .92rem/1.5 'Source Sans 3', sans-serif;
}
.cx-empty a { color: var(--amber-deep); font-weight: 800; }

/* Mobile collapsible summary */
.cx-summary-toggle { display: none; }
@media (max-width: 980px) {
    .cx-grid { grid-template-columns: 1fr; }
    .cx-summary { position: static; order: -1; }
    .cx-summary-toggle {
        display: flex; justify-content: space-between; align-items: center;
        background: #fff; border: 1px solid var(--line); border-radius: 12px;
        padding: 10px 14px; cursor: pointer; margin-bottom: 8px;
        font: 700 .92rem/1 'Montserrat', sans-serif; color: var(--brand-navy);
    }
    .cx-summary-toggle .amt { color: #b8232f; font-weight: 900; }
    .cx-summary.is-collapsed .cx-items { display: none; }
    .cx-summary.is-collapsed hr { display: none; }
    .cx-row.two { grid-template-columns: 1fr; }
    .pay-options { grid-template-columns: 1fr; }
    .pay-options label { flex-direction: row; justify-content: flex-start; padding: 12px; }
}
</style>
</head>
<body class="is-checkout">
<?php
$showPromoBar = false;
$headerSearchValue = '';
$cartCount = 0;
include __DIR__ . '/partials/site-header.php';
?>

<main class="wrap cx-wrap">
    <div class="cx-bar">
        <h1>Checkout</h1>
        <a class="back" href="cart.php">&larr; Back to cart</a>
        <span class="cx-secure">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Secure checkout
        </span>
    </div>

    <div id="cxEmpty" class="cx-empty" hidden>
        Your cart is empty. <a href="index.php">Continue shopping</a>.
    </div>

    <button class="cx-summary-toggle" id="cxSummaryToggle" type="button">
        <span>View / hide items</span>
        <span class="amt" id="cxToggleAmt">LKR 0.00</span>
    </button>

    <form class="cx-grid" id="cxForm" novalidate hidden>
        <div class="cx-form">
            <section class="cx-card">
                <h2><span class="num">1</span> Contact</h2>
                <div class="cx-row two">
                    <div class="cx-field" data-required>
                        <label for="cxPhone">Phone <span class="opt">(WhatsApp preferred)</span></label>
                        <input type="tel" id="cxPhone" name="customer_phone" autocomplete="tel" placeholder="07X XXX XXXX">
                        <span class="hint">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 3.5A10 10 0 0 0 4 16l-1 5 5-1A10 10 0 1 0 20 3.5z"/></svg>
                            We'll send tracking via WhatsApp
                        </span>
                        <span class="err">Phone is required.</span>
                    </div>
                    <div class="cx-field">
                        <label for="cxAltPhone">Alternate phone <span class="opt">(optional)</span></label>
                        <input type="tel" id="cxAltPhone" name="alt_phone" autocomplete="tel-national" placeholder="Backup number">
                        <span class="hint" style="color:#6b7388;">Courier may call before delivery</span>
                    </div>
                </div>
                <div class="cx-row" style="margin-top:10px;">
                    <div class="cx-field">
                        <label for="cxEmail">Email <span class="opt">(optional)</span></label>
                        <input type="email" id="cxEmail" name="customer_email" autocomplete="email" placeholder="for receipt &amp; order updates">
                        <span class="err">Enter a valid email.</span>
                    </div>
                </div>
            </section>

            <section class="cx-card">
                <h2><span class="num">2</span> Shipping</h2>
                <div class="cx-row">
                    <div class="cx-field" data-required>
                        <label for="cxName">Full name</label>
                        <input type="text" id="cxName" name="customer_name" autocomplete="name" placeholder="As on the parcel">
                        <span class="err">Name is required.</span>
                    </div>
                    <div class="cx-field" data-required>
                        <label for="cxAddress">Delivery address</label>
                        <textarea id="cxAddress" name="address" rows="3" autocomplete="street-address" placeholder="House no, street, village, city, postal code" style="resize:vertical;"></textarea>
                        <span class="hint" style="color:#6b7388;">Include landmarks if helpful for the courier</span>
                        <span class="err">Address is required.</span>
                    </div>
                </div>
            </section>

            <section class="cx-card">
                <h2><span class="num">3</span> Payment</h2>
                <div class="pay-options">
                    <label>
                        <input type="radio" name="payment_method" value="payhere" checked>
                        <span class="pc-body">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/></svg>
                            <strong>PayHere</strong>
                            <span>Card / wallet</span>
                        </span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="cod">
                        <span class="pc-body">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                            <strong>Cash on Delivery</strong>
                            <span>Pay courier</span>
                        </span>
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="bank">
                        <span class="pc-body">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V10l7-5 7 5v11"/><path d="M9 21v-7h6v7"/></svg>
                            <strong>Bank Transfer</strong>
                            <span>Direct deposit</span>
                        </span>
                    </label>
                </div>
                <details class="cx-notes" style="margin-top:10px;">
                    <summary>+ Add order notes (optional)</summary>
                    <div class="cx-field">
                        <textarea id="cxNotes" name="notes" placeholder="Delivery instructions, gift message..."></textarea>
                    </div>
                </details>
            </section>

            <div class="cx-error" id="cxError"></div>
        </div>

        <aside class="cx-summary" id="cxSummary">
            <h2>Order summary</h2>
            <div class="cx-items" id="cxItems"></div>
            <hr>
            <div class="ln"><span>Subtotal</span><strong id="cxSub">LKR 0.00</strong></div>
            <div class="ln cx-discount-line" id="cxDiscountLine" hidden><span>Discount <em id="cxDiscountCode" style="font-style:normal;font-weight:800;color:var(--brand-navy);"></em></span><strong id="cxDiscount" style="color:#17633e">− LKR 0.00</strong></div>
            <div class="ln"><span>Shipping</span><strong id="cxShip">LKR 0.00</strong></div>
            <div class="total"><span class="lbl">Total</span><span class="amt" id="cxTotal">LKR 0.00</span></div>
            <div class="cx-coupon" style="margin-top:14px;">
                <label for="cxCoupon" style="display:block;font:700 .76rem/1 'Montserrat',sans-serif;color:var(--brand-navy-deep);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Have a promo code?</label>
                <div style="display:flex;gap:6px;">
                    <input id="cxCoupon" type="text" autocomplete="off" placeholder="Enter code" style="flex:1;text-transform:uppercase;font:700 .9rem/1 'Source Sans 3',sans-serif;letter-spacing:.05em;padding:9px 11px;border:1px solid var(--line);border-radius:9px;">
                    <button id="cxCouponApply" type="button" style="padding:9px 14px;border-radius:9px;border:0;background:var(--brand-navy);color:#fff;font:700 .85rem/1 'Montserrat',sans-serif;cursor:pointer;">Apply</button>
                </div>
                <div id="cxCouponMsg" style="margin-top:6px;font:600 .8rem/1.3 'Source Sans 3',sans-serif;"></div>
            </div>
            <button type="submit" class="cx-submit" id="cxSubmit" form="cxForm" disabled style="margin-top:14px;">
                <span class="spinner" aria-hidden="true"></span>
                <span class="lbl">Place order &mdash; <span id="cxBtnAmt">LKR 0.00</span></span>
            </button>
            <a class="edit-cart" href="cart.php">&larr; Edit cart</a>
            <div class="trust-mini">
                <span class="chip">7-day returns</span>
                <span class="chip">Buyer protection</span>
                <span class="chip">Secure SSL</span>
            </div>
            <div class="payhere-banner">
                <img src="https://www.payhere.lk/downloads/images/payhere_long_banner.png" alt="PayHere accepts Visa, Mastercard, Amex, eZ Cash, mCash, Genie" loading="lazy">
            </div>
        </aside>
    </form>
</main>

<?php include __DIR__ . '/partials/site-footer.php'; ?>
<?php include __DIR__ . '/partials/site-scripts.php'; ?>

<script>
(function() {
    function ready(fn) {
        if (window.WLKCart) return fn();
        var t = setInterval(function() { if (window.WLKCart) { clearInterval(t); fn(); } }, 30);
    }
    ready(function() {
        var form = document.getElementById('cxForm');
        var emptyEl = document.getElementById('cxEmpty');
        var summaryToggle = document.getElementById('cxSummaryToggle');
        var summary = document.getElementById('cxSummary');
        var itemsEl = document.getElementById('cxItems');
        var subEl = document.getElementById('cxSub');
        var shipEl = document.getElementById('cxShip');
        var totalEl = document.getElementById('cxTotal');
        var btnAmt = document.getElementById('cxBtnAmt');
        var toggleAmt = document.getElementById('cxToggleAmt');
        var submitBtn = document.getElementById('cxSubmit');
        var errBox = document.getElementById('cxError');
        var couponInput = document.getElementById('cxCoupon');
        var couponBtn = document.getElementById('cxCouponApply');
        var couponMsg = document.getElementById('cxCouponMsg');
        var discountLine = document.getElementById('cxDiscountLine');
        var discountAmtEl = document.getElementById('cxDiscount');
        var discountCodeEl = document.getElementById('cxDiscountCode');
        var DRAFT_KEY = 'wlk_checkout_draft';
        var COUPON_KEY = 'wlk_checkout_coupon';
        var appliedCoupon = null; // { code, coupon_id, discount, type }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]);
            });
        }

        function renderSummary() {
            var items = WLKCart.items();
            if (items.length === 0) {
                form.setAttribute('hidden', '');
                emptyEl.removeAttribute('hidden');
                summaryToggle.setAttribute('hidden', '');
                return;
            }
            emptyEl.setAttribute('hidden', '');
            form.removeAttribute('hidden');

            itemsEl.innerHTML = items.map(function(it) {
                var img = it.image_url || 'assets/images/brand/logo-watercolorlk.png';
                return '<div class="it">' +
                    '<span class="qpip" data-q="' + Number(it.qty) + '"><img src="' + escapeHtml(img) + '" alt="" onerror="this.onerror=null;this.src=\'assets/images/brand/logo-watercolorlk.png\';"></span>' +
                    '<span class="nm">' + escapeHtml(it.name) + '</span>' +
                    '<span class="pr">' + WLKCart.formatLKR(Number(it.price) * Number(it.qty)) + '</span>' +
                '</div>';
            }).join('');

            var sub = WLKCart.subtotal();
            var ship = WLKCart.shipping(sub);
            subEl.textContent = WLKCart.formatLKR(sub);
            shipEl.innerHTML = ship === 0 ? '<span class="ship-free">FREE</span>' : WLKCart.formatLKR(ship);

            var discount = 0;
            var freeShipFromCoupon = false;
            if (appliedCoupon) {
                discount = Number(appliedCoupon.discount || 0);
                if (appliedCoupon.type === 'free_ship') {
                    freeShipFromCoupon = true;
                    ship = 0;
                    shipEl.innerHTML = '<span class="ship-free">FREE (coupon)</span>';
                }
            }
            if (discount > 0) {
                discountLine.removeAttribute('hidden');
                discountAmtEl.textContent = '− ' + WLKCart.formatLKR(discount);
                discountCodeEl.textContent = '(' + appliedCoupon.code + ')';
            } else if (freeShipFromCoupon) {
                discountLine.setAttribute('hidden', '');
            } else {
                discountLine.setAttribute('hidden', '');
            }

            var grandTotal = Math.max(0, sub - discount + ship);
            totalEl.textContent = WLKCart.formatLKR(grandTotal);
            btnAmt.textContent = WLKCart.formatLKR(grandTotal);
            toggleAmt.textContent = WLKCart.formatLKR(grandTotal);
            validate();
        }

        /* Validation */
        function fields() {
            return Array.from(form.querySelectorAll('.cx-field[data-required]'));
        }
        function emailField() {
            return document.getElementById('cxEmail').closest('.cx-field');
        }
        function getValue(field) {
            var el = field.querySelector('input, select, textarea');
            return (el && el.value || '').trim();
        }
        function isFieldValid(field) {
            var el = field.querySelector('input, select, textarea');
            if (!el) return true;
            var v = (el.value || '').trim();
            if (field.hasAttribute('data-required') && !v) return false;
            if (el.type === 'tel') return /^[0-9+\s\-]{7,}$/.test(v);
            if (el.type === 'email') return v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
            return true;
        }
        function validate() {
            var ok = true;
            fields().forEach(function(f) {
                if (!isFieldValid(f)) ok = false;
            });
            if (!isFieldValid(emailField())) ok = false;
            if (WLKCart.count() === 0) ok = false;
            submitBtn.disabled = !ok;
            return ok;
        }
        function markField(field) {
            field.classList.toggle('invalid', !isFieldValid(field));
        }
        form.addEventListener('input', function(e) {
            var f = e.target.closest('.cx-field');
            if (f && f.classList.contains('invalid')) markField(f);
            saveDraft();
            validate();
        });
        form.addEventListener('blur', function(e) {
            var f = e.target.closest && e.target.closest('.cx-field');
            if (f) markField(f);
        }, true);

        /* Draft persistence */
        function saveDraft() {
            try {
                var d = {
                    customer_phone: document.getElementById('cxPhone').value,
                    alt_phone: document.getElementById('cxAltPhone').value,
                    customer_email: document.getElementById('cxEmail').value,
                    customer_name: document.getElementById('cxName').value,
                    address: document.getElementById('cxAddress').value,
                    notes: document.getElementById('cxNotes').value,
                    payment_method: (form.querySelector('input[name=payment_method]:checked') || {}).value || 'payhere'
                };
                localStorage.setItem(DRAFT_KEY, JSON.stringify(d));
            } catch (_) {}
        }
        function loadDraft() {
            try {
                var d = JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}');
                if (!d || typeof d !== 'object') return;
                ['customer_phone','alt_phone','customer_email','customer_name','address','notes'].forEach(function(k) {
                    var map = { customer_phone:'cxPhone', alt_phone:'cxAltPhone', customer_email:'cxEmail', customer_name:'cxName', address:'cxAddress', notes:'cxNotes' };
                    var el = document.getElementById(map[k]);
                    if (el && d[k]) el.value = d[k];
                });
                if (d.payment_method) {
                    var pm = form.querySelector('input[name=payment_method][value="' + d.payment_method + '"]');
                    if (pm) pm.checked = true;
                }
            } catch (_) {}
        }

        /* Mobile summary collapse */
        summaryToggle.addEventListener('click', function() {
            summary.classList.toggle('is-collapsed');
        });
        if (window.matchMedia('(max-width: 980px)').matches) {
            summary.classList.add('is-collapsed');
        }

        /* Submit */
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            errBox.textContent = '';
            // run hard validation
            var valid = true;
            fields().forEach(function(f) {
                markField(f);
                if (!isFieldValid(f)) valid = false;
            });
            markField(emailField());
            if (!isFieldValid(emailField())) valid = false;
            if (!valid) {
                errBox.textContent = 'Please fix the highlighted fields above.';
                var firstBad = form.querySelector('.cx-field.invalid');
                if (firstBad) firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            if (WLKCart.count() === 0) {
                errBox.textContent = 'Your cart is empty.';
                return;
            }

            var paymentMethod = (form.querySelector('input[name=payment_method]:checked') || {}).value || 'payhere';
            var fullAddress = document.getElementById('cxAddress').value.trim();
            var altPhone = document.getElementById('cxAltPhone').value.trim();
            var notesText = document.getElementById('cxNotes').value.trim();
            var notesParts = ['Address: ' + fullAddress];
            if (altPhone) notesParts.push('Alt phone: ' + altPhone);
            if (notesText) notesParts.push('Notes: ' + notesText);
            if (appliedCoupon) notesParts.push('Coupon: ' + appliedCoupon.code);

            var sub = WLKCart.subtotal();
            var ship = WLKCart.shipping(sub);
            var discount = appliedCoupon ? Number(appliedCoupon.discount || 0) : 0;
            if (appliedCoupon && appliedCoupon.type === 'free_ship') ship = 0;
            var grandTotal = Math.max(0, sub - discount + ship);

            var payload = {
                customer_name: document.getElementById('cxName').value.trim(),
                customer_phone: document.getElementById('cxPhone').value.trim(),
                customer_email: document.getElementById('cxEmail').value.trim(),
                payment_method: paymentMethod,
                notes: notesParts.join(' | '),
                subtotal_amount: Number(sub.toFixed(2)),
                shipping_amount: Number(ship.toFixed(2)),
                discount_amount: Number(discount.toFixed(2)),
                total_amount: Number(grandTotal.toFixed(2)),
                coupon_code: appliedCoupon ? appliedCoupon.code : null,
                items: WLKCart.items().map(function(it) {
                    return {
                        kind: it.kind || 'simple',
                        erp_product_id: Number(it.erp_product_id || 0),
                        storefront_product_id: it.storefront_product_id ? Number(it.storefront_product_id) : null,
                        parent_storefront_id: it.parent_storefront_id ? Number(it.parent_storefront_id) : null,
                        variant_child_id: it.variant_child_id ? Number(it.variant_child_id) : null,
                        variant_label: it.variant_label || null,
                        pack_children: it.pack_children || null,
                        sku: it.sku || '',
                        name: it.name || '',
                        quantity: Number(it.qty),
                        unit_price: Number(it.price)
                    };
                })
            };

            submitBtn.classList.add('is-loading');
            submitBtn.disabled = true;

            fetch('api/place-order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(r) {
                return r.json().then(function(j) { return { ok: r.ok, data: j }; });
            }).then(function(res) {
                submitBtn.classList.remove('is-loading');
                if (!res.ok || !res.data || !res.data.success) {
                    submitBtn.disabled = false;
                    errBox.textContent = (res.data && res.data.error) || 'Something went wrong placing your order. Please try again.';
                    return;
                }
                // TODO: PayHere SDK handoff goes here when keys are configured.
                WLKCart.clear();
                try { localStorage.removeItem(DRAFT_KEY); } catch (_) {}
                try { localStorage.removeItem(COUPON_KEY); } catch (_) {}
                window.location.href = 'checkout-success.php?order_id=' + encodeURIComponent(res.data.order_id) + '&method=' + encodeURIComponent(paymentMethod);
            }).catch(function(err) {
                submitBtn.classList.remove('is-loading');
                submitBtn.disabled = false;
                errBox.textContent = 'Network error: ' + (err && err.message ? err.message : 'try again');
            });
        });

        loadDraft();
        renderSummary();
        WLKCart.on('change', renderSummary);

        /* ====== Coupon apply / re-validate ====== */
        function setCouponMsg(text, ok) {
            couponMsg.textContent = text || '';
            couponMsg.style.color = ok ? '#17633e' : '#b8232f';
        }
        function clearCoupon() {
            appliedCoupon = null;
            couponInput.value = '';
            couponInput.disabled = false;
            couponBtn.textContent = 'Apply';
            try { localStorage.removeItem(COUPON_KEY); } catch (_) {}
            renderSummary();
        }
        function applyCoupon(code) {
            code = String(code || '').trim().toUpperCase();
            if (!code) { setCouponMsg('Enter a coupon code.', false); return; }
            couponBtn.disabled = true;
            setCouponMsg('Checking…', true);
            fetch('api/validate-coupon.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    code: code,
                    customer_phone: document.getElementById('cxPhone').value.trim(),
                    items: WLKCart.items().map(function(it) {
                        return {
                            erp_product_id: Number(it.erp_product_id || 0),
                            storefront_product_id: it.storefront_product_id || null,
                            parent_storefront_id: it.parent_storefront_id || null,
                            qty: Number(it.qty),
                            price: Number(it.price)
                        };
                    })
                })
            }).then(function(r) { return r.json(); }).then(function(j) {
                couponBtn.disabled = false;
                if (!j || !j.ok) {
                    setCouponMsg((j && j.error) || 'Coupon could not be applied.', false);
                    appliedCoupon = null;
                    renderSummary();
                    return;
                }
                appliedCoupon = { code: j.code, coupon_id: j.coupon_id, discount: j.discount, type: j.type };
                couponInput.value = j.code;
                couponInput.disabled = true;
                couponBtn.textContent = 'Remove';
                var msg = j.type === 'free_ship'
                    ? 'Free shipping unlocked!'
                    : 'Saved ' + WLKCart.formatLKR(j.discount) + '!';
                setCouponMsg(msg, true);
                try { localStorage.setItem(COUPON_KEY, JSON.stringify(appliedCoupon)); } catch (_) {}
                renderSummary();
            }).catch(function(err) {
                couponBtn.disabled = false;
                setCouponMsg('Network error: ' + (err && err.message || 'try again'), false);
            });
        }
        couponBtn.addEventListener('click', function() {
            if (appliedCoupon) { clearCoupon(); setCouponMsg(''); return; }
            applyCoupon(couponInput.value);
        });
        couponInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyCoupon(couponInput.value); }
        });
        /* Re-validate on cart change to ensure discount stays correct */
        WLKCart.on('change', function() {
            if (appliedCoupon) applyCoupon(appliedCoupon.code);
        });
        /* Restore previous attempt */
        try {
            var saved = JSON.parse(localStorage.getItem(COUPON_KEY) || 'null');
            if (saved && saved.code) applyCoupon(saved.code);
        } catch (_) {}
    });
})();
</script>
</body>
</html>
