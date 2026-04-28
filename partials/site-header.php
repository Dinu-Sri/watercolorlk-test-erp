<?php
/**
 * Shared header partial.
 *
 * Inputs (set before include):
 *   $showPromoBar (bool, default false) — show the rotating promo strip above the header.
 *   $headerSearchValue (string, default '') — pre-fill value for the search input.
 *   $cartCount (int, default 0) — pip number; if 0, the pip is hidden.
 *
 * The search input has id="search". JS on each page may bind to it for live suggestions.
 */
$showPromoBar = isset($showPromoBar) ? (bool)$showPromoBar : false;
$headerSearchValue = isset($headerSearchValue) ? (string)$headerSearchValue : '';
$cartCount = isset($cartCount) ? (int)$cartCount : 0;
?>
<?php if ($showPromoBar): ?>
<div id="promoBar" class="promo-bar">
    <div class="promo-track">
        <span><strong>FREE delivery</strong> on orders over LKR 5,000</span><span class="dot"></span>
        <span><strong>Cash on Delivery</strong> island-wide</span><span class="dot"></span>
        <span>Flash deals end in <strong id="promoCountdown">--:--:--</strong></span><span class="dot"></span>
        <span>100% authentic stock - sourced from official brands</span><span class="dot"></span>
        <span><strong>FREE delivery</strong> on orders over LKR 5,000</span><span class="dot"></span>
        <span><strong>Cash on Delivery</strong> island-wide</span><span class="dot"></span>
        <span>Flash deals end in <strong>tonight</strong></span><span class="dot"></span>
        <span>100% authentic stock - sourced from official brands</span>
    </div>
    <button id="promoClose" class="promo-close" aria-label="Dismiss promo">&times;</button>
</div>
<?php endif; ?>

<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="index.php">
            <img class="logo" src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK">
            <span class="brand-sub">පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</span>
        </a>
        <div class="header-search">
            <svg class="search-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>
            <input id="search" class="header-search-input" placeholder="Search 1,000+ products, brands, categories..." autocomplete="off" value="<?= htmlspecialchars($headerSearchValue) ?>">
            <div id="suggestions" class="suggestions"></div>
        </div>
        <div class="header-actions">
            <a class="icon-btn" href="admin/index.php" aria-label="Account">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <a class="icon-btn" href="cart.php" aria-label="Cart" id="cartButton">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>
                <span class="pip" id="cartPip"<?= $cartCount > 0 ? '' : ' hidden' ?>><?= $cartCount > 0 ? (int)$cartCount : 0 ?></span>
            </a>
        </div>
    </div>
</header>
