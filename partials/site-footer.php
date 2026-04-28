<?php
/**
 * Shared footer + activity toast + mobile bottom nav.
 * Identical on every page.
 */
?>
<!-- ACTIVITY TICKER -->
<div id="activityToast" class="activity-toast" role="status" aria-live="polite">
    <span class="pulse" aria-hidden="true"></span>
    <div><span id="actText">Someone just bought a product</span><small id="actSub">Live activity</small></div>
</div>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="footer-trust-band">
        <div class="wrap footer-trust">
            <div class="trust-tile">
                <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 6v6c0 5 3.5 9 8 10 4.5-1 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg></span>
                <div><strong>100% Authentic</strong><span>Direct from official brands</span></div>
            </div>
            <div class="trust-tile">
                <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg></span>
                <div><strong>Island-wide delivery</strong><span>1-3 days, free over LKR 5,000</span></div>
            </div>
            <div class="trust-tile">
                <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3z"/><path d="M2 11h20"/></svg></span>
                <div><strong>Secure payments</strong><span>PayHere, COD &amp; bank transfer</span></div>
            </div>
            <div class="trust-tile">
                <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.4 8.4 0 0 1-1 4 8.5 8.5 0 0 1-7.6 4.5 8.4 8.4 0 0 1-4-1L3 21l2-5.4a8.4 8.4 0 0 1-1-4 8.5 8.5 0 0 1 4.5-7.5 8.4 8.4 0 0 1 4-1A8.5 8.5 0 0 1 21 11.5z"/></svg></span>
                <div><strong>WhatsApp support</strong><span>9 AM - 9 PM, all week</span></div>
            </div>
        </div>
    </div>
    <div class="wrap footer-main">
        <div class="footer-grid">
            <div class="footer-brand">
                <span class="logo-card"><img src="assets/images/brand/logo-watercolorlk.png" alt="Watercolor.LK"></span>
                <p>Sri Lanka's trusted online store for premium watercolor and art supplies. පටන් ගන්න! පාට කරන්න! ජිවිතය විදින්න!</p>
                <div class="footer-contact">
                    <a href="tel:+94770000000"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z"/></svg>+94 77 000 0000</a>
                    <a href="https://wa.me/94770000000" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 3.5A10 10 0 0 0 4 16l-1 5 5-1A10 10 0 1 0 20 3.5z"/></svg>WhatsApp chat</a>
                    <a href="mailto:hello@watercolor.lk"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>hello@watercolor.lk</a>
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-7.58 8-13a8 8 0 1 0-16 0c0 5.42 8 13 8 13z"/><circle cx="12" cy="9" r="3"/></svg>Colombo, Sri Lanka 🇱🇰</span>
                </div>
                <div class="footer-socials">
                    <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H8v-3h2.4V9.4c0-2.4 1.4-3.7 3.6-3.7 1 0 2.1.2 2.1.2v2.3h-1.2c-1.2 0-1.5.7-1.5 1.5V12h2.6l-.4 3h-2.2v7A10 10 0 0 0 22 12z"/></svg></a>
                    <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg></a>
                    <a href="https://wa.me/94770000000" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 3.5A10 10 0 0 0 4 16l-1 5 5-1A10 10 0 1 0 20 3.5zm-8 16a8 8 0 0 1-4-1.1l-3 .8.8-3A8 8 0 1 1 12 19.5z"/></svg></a>
                    <a href="#" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 3v3a4 4 0 0 0 4 4v3a7 7 0 0 1-4-1.3V16a5 5 0 1 1-5-5v3a2 2 0 1 0 2 2V3z"/></svg></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Shop</h4>
                <ul>
                    <li><a href="index.php?q=paint">Paints</a></li>
                    <li><a href="index.php?q=brush">Brushes</a></li>
                    <li><a href="index.php?q=paper">Papers</a></li>
                    <li><a href="index.php?q=sketch">Sketchbooks</a></li>
                    <li><a href="index.php?q=access">Accessories</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Help</h4>
                <ul>
                    <li><a href="#">Track order</a></li>
                    <li><a href="#">Shipping &amp; delivery</a></li>
                    <li><a href="#">Returns &amp; refunds</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Contact us</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About Watercolor.LK</a></li>
                    <li><a href="#">Artist tutorials</a></li>
                    <li><a href="#">Wholesale &amp; schools</a></li>
                    <li><a href="#">Terms of service</a></li>
                    <li><a href="#">Privacy policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-payments">
            <h5>We Accept</h5>
            <div class="pay-row">
                <a class="payhere-banner" href="https://www.payhere.lk" target="_blank" rel="noopener" aria-label="PayHere">
                    <img src="https://www.payhere.lk/downloads/images/payhere_long_banner.png" alt="PayHere - Visa, MasterCard, Amex, eZ Cash, mCash, Genie, FriMi" loading="lazy">
                </a>
                <span class="pay-chip cod">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3zM16 10h4l2 3v4h-6"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                    Cash on Delivery
                </span>
                <span class="pay-chip bank">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V10l7-5 7 5v11"/><path d="M9 21v-7h6v7"/></svg>
                    Bank Transfer
                </span>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Watercolor.LK - Made with love in Sri Lanka 🇱🇰</span>
            <span>All rights reserved.</span>
        </div>
    </div>
</footer>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav" aria-label="Mobile navigation">
    <a href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 11 9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>Home</a>
    <a href="shop.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Shop</a>
    <a href="#" id="bottomNavSearch"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></svg>Search</a>
    <a href="cart.php" id="bottomNavCart"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/><path d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H7"/></svg>Cart</a>
</nav>
