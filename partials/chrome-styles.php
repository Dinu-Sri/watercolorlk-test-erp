<?php
// Shared brand tokens + chrome (header / footer / activity toast / bottom nav).
// Included in <head> of every page so all pages look identical above and below the fold.
?>
<style>
    :root {
        --brand-navy: #1b2d4f;
        --brand-navy-deep: #10203a;
        --paper: #faf8f5;
        --surface: #ffffff;
        --surface-2: #fdf8f1;
        --line: #e7ddd2;
        --text: #1a1a1a;
        --muted: #6b6b6b;
        --amber: #e8760a;
        --amber-deep: #c4600a;
        --rose: #c4705a;
        --danger: #c0392b;
        --success: #2d7a4f;
        --gold: #d8a03d;
        --accent-fire: #e63946;
        --accent-mint: #2d7a4f;
        --ribbon-gold: #f4b740;
        --radius-sm: 10px;
        --radius-md: 16px;
        --radius-lg: 24px;
        --shadow-sm: 0 10px 24px rgba(17, 31, 56, 0.08);
        --shadow-md: 0 16px 40px rgba(17, 31, 56, 0.10);
        --shadow-lg: 0 24px 60px rgba(17, 31, 56, 0.14);
        --grad-fire: linear-gradient(135deg, #ff5b3a 0%, #e63946 60%, #b8232f 100%);
        --grad-navy: linear-gradient(135deg, #1b2d4f 0%, #243a66 100%);
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
        margin: 0;
        color: var(--text);
        background:
            radial-gradient(circle at top left, rgba(196, 112, 90, .15), transparent 32%),
            radial-gradient(circle at top right, rgba(232, 118, 10, .08), transparent 28%),
            linear-gradient(180deg, #fffdfa 0%, var(--paper) 42%, #f5eee6 100%);
        font: 400 17px/1.7 'Source Sans 3', 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    img { max-width: 100%; display: block; }
    a { color: inherit; }
    .wrap { width: min(calc(100% - 32px), 1240px); margin: 0 auto; }

    /* ===== Promo bar ===== */
    .promo-bar {
        background: var(--grad-fire);
        color: #fff;
        font: 700 .82rem/1.2 'Montserrat', sans-serif;
        letter-spacing: .03em;
        position: relative;
        overflow: hidden;
    }
    .promo-bar.is-hidden { display: none; }
    .promo-track {
        display: flex; align-items: center; gap: 22px;
        padding: 9px 44px 9px 18px;
        white-space: nowrap;
        animation: promoSlide 28s linear infinite;
    }
    .promo-track span { display: inline-flex; align-items: center; gap: 8px; }
    .promo-track .dot { width: 5px; height: 5px; border-radius: 50%; background: rgba(255,255,255,.65); display: inline-block; }
    .promo-close {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        background: rgba(0,0,0,.18); color: #fff; border: 0; width: 24px; height: 24px;
        border-radius: 50%; cursor: pointer; font-size: 14px; line-height: 1;
    }
    @keyframes promoSlide { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
    @media (prefers-reduced-motion: reduce) { .promo-track { animation: none; } }

    /* ===== Header ===== */
    .site-header {
        position: sticky; top: 0; z-index: 40;
        border-bottom: 1px solid var(--line);
        backdrop-filter: blur(16px);
        background: rgba(250, 248, 245, .94);
        box-shadow: 0 8px 24px rgba(17, 31, 56, 0.08);
    }
    .header-inner { display: flex; align-items: center; gap: 20px; padding: 14px 0; }
    .brand {
        flex: 0 0 auto; min-width: 0;
        text-decoration: none;
        display: flex; flex-direction: column; align-items: flex-start; gap: 4px;
    }
    .logo { height: 42px; width: auto; display: block; }
    .brand-sub { color: #43516d; font: 700 .79rem/1.2 'Montserrat', sans-serif; letter-spacing: .02em; }
    .header-search { flex: 1; position: relative; }
    .header-search-input {
        width: 100%;
        border: 1px solid #d7c9b8; outline: none; border-radius: 999px;
        padding: 17px 18px 17px 48px;
        background: #fff; color: #1f2a3d;
        font: 600 1.02rem/1.2 'Source Sans 3', sans-serif;
        transition: border-color .15s, box-shadow .15s;
    }
    .header-search-input::placeholder { color: #7a828f; }
    .header-search-input:focus { border-color: var(--amber); box-shadow: 0 0 0 4px rgba(232,118,10,.14); }
    .header-search > .search-ico { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #6d7383; width: 20px; height: 20px; }
    .header-actions { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
    .icon-btn {
        position: relative; width: 46px; height: 46px;
        border: 1px solid var(--line); border-radius: 999px;
        display: inline-flex; align-items: center; justify-content: center;
        color: var(--brand-navy); background: #fff; text-decoration: none;
        box-shadow: var(--shadow-sm);
        transition: transform .15s, box-shadow .15s;
    }
    .icon-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .icon-btn svg { width: 21px; height: 21px; }
    .icon-btn .pip {
        position: absolute; top: -2px; right: -2px;
        background: var(--accent-fire); color: #fff;
        font: 700 10px/1 'Montserrat', sans-serif;
        min-width: 18px; height: 18px; padding: 0 5px;
        border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
        box-shadow: 0 2px 6px rgba(230,57,70,.4);
    }

    /* ===== Header search suggestions ===== */
    .suggestions {
        margin-top: 6px; border: 1px solid #d6cab8; border-radius: 14px;
        background: #fff; display: none; overflow: hidden;
        box-shadow: 0 14px 36px rgba(17, 31, 56, .12);
        position: absolute; left: 0; right: 0; z-index: 30;
    }
    .suggestion {
        padding: 11px 13px; border-bottom: 1px solid #f1ecdf;
        cursor: pointer; font: 600 14px/1.3 'Source Sans 3', sans-serif; color: #273144;
    }
    .suggestion:hover { background: #fdf8f1; }
    .suggestion:last-child { border-bottom: 0; }

    /* ===== Activity ticker (modernised, real) ===== */
    .activity-toast {
        position: fixed; left: 18px; bottom: 22px;
        display: none; align-items: center; gap: 12px;
        padding: 12px 18px 12px 14px; border-radius: 14px;
        background: #fff; color: #1b2d4f;
        border: 1px solid var(--line);
        box-shadow: 0 18px 44px rgba(17,31,56,.18);
        font: 600 .86rem/1.3 'Source Sans 3', sans-serif;
        z-index: 38; max-width: 360px;
        animation: toastIn .35s ease;
    }
    .activity-toast.is-visible { display: inline-flex; }
    .activity-toast .pulse {
        width: 10px; height: 10px; border-radius: 50%;
        background: var(--accent-mint); flex: 0 0 10px; position: relative;
    }
    .activity-toast .pulse::before {
        content: ""; position: absolute; inset: -6px;
        border-radius: 50%; border: 2px solid var(--accent-mint);
        opacity: .5; animation: pulseRing 1.6s ease-out infinite;
    }
    @keyframes pulseRing { 0% { transform: scale(.6); opacity: .8; } 100% { transform: scale(1.4); opacity: 0; } }
    @keyframes toastIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .activity-toast small { display: block; color: #8a8275; font-weight: 500; margin-top: 3px; font-size: .76rem; }

    /* ===== Footer (paper trust band + dark navy body) ===== */
    .site-footer {
        background: linear-gradient(180deg, #1b2d4f 0%, #10203a 100%);
        color: rgba(255,255,255,.82);
        padding: 0 0 24px; margin-top: 28px;
        position: relative;
    }
    .footer-trust-band { background: linear-gradient(180deg, #fdf3e6 0%, #faf8f5 100%); border-top: 1px solid var(--line); padding: 28px 0; }
    .footer-trust { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
    .trust-tile {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 18px; border-radius: var(--radius-md);
        background: #fff; border: 1px solid var(--line);
        box-shadow: var(--shadow-sm);
    }
    .trust-tile .icon {
        width: 46px; height: 46px; border-radius: 12px;
        background: linear-gradient(135deg, rgba(232,118,10,.18), rgba(232,118,10,.08));
        color: var(--amber-deep);
        display: inline-flex; align-items: center; justify-content: center; flex: 0 0 46px;
    }
    .trust-tile .icon svg { width: 24px; height: 24px; }
    .trust-tile strong { display: block; color: var(--brand-navy); font: 800 1rem/1.1 'Montserrat', sans-serif; }
    .trust-tile span { color: #6e7689; font: 500 .82rem/1.3 'Source Sans 3', sans-serif; }

    .footer-main { padding-top: 44px; }
    .footer-grid {
        display: grid; grid-template-columns: 1.4fr repeat(3, 1fr); gap: 30px;
        padding-bottom: 26px; border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .footer-brand .logo-card {
        display: inline-flex; align-items: center; justify-content: center;
        background: #fff; border-radius: 12px;
        padding: 8px 14px; margin-bottom: 14px;
        box-shadow: 0 4px 14px rgba(0,0,0,.2);
    }
    .footer-brand .logo-card img { height: 36px; width: auto; display: block; }
    .footer-brand p { margin: 0 0 14px; font-size: .92rem; line-height: 1.6; opacity: .82; max-width: 380px; }
    .footer-contact { display: grid; gap: 8px; margin: 12px 0 14px; font-size: .88rem; }
    .footer-contact a, .footer-contact span { color: rgba(255,255,255,.78); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .footer-contact a:hover { color: var(--amber); }
    .footer-contact svg { width: 16px; height: 16px; flex: 0 0 16px; opacity: .8; }
    .footer-socials { display: flex; gap: 8px; }
    .footer-socials a {
        width: 38px; height: 38px; border-radius: 50%;
        background: rgba(255,255,255,.08); color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        text-decoration: none; transition: background .15s, transform .15s;
    }
    .footer-socials a:hover { background: var(--amber); transform: translateY(-2px); }
    .footer-socials svg { width: 17px; height: 17px; }

    .footer-payments {
        padding: 22px 0 18px; border-bottom: 1px solid rgba(255,255,255,.1);
        display: grid; grid-template-columns: auto 1fr; gap: 18px; align-items: center;
    }
    .footer-payments h5 { margin: 0; color: #fff; font: 700 .82rem/1 'Montserrat', sans-serif; letter-spacing: .1em; text-transform: uppercase; }
    .pay-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .payhere-banner {
        background: #fff; padding: 8px 12px; border-radius: 10px;
        display: inline-flex; align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,.15);
    }
    .payhere-banner img { height: 30px; width: auto; display: block; }
    .pay-chip {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 14px; border-radius: 10px;
        background: rgba(255,255,255,.1); color: #fff;
        font: 700 .82rem/1 'Montserrat', sans-serif; letter-spacing: .04em;
        border: 1px solid rgba(255,255,255,.15);
    }
    .pay-chip svg { width: 18px; height: 18px; }
    .pay-chip.cod { background: rgba(45,122,79,.25); border-color: rgba(45,122,79,.5); }
    .pay-chip.bank { background: rgba(232,118,10,.18); border-color: rgba(232,118,10,.4); }

    .footer-col h4 { margin: 0 0 14px; color: #fff; font: 700 .82rem/1 'Montserrat', sans-serif; letter-spacing: .1em; text-transform: uppercase; }
    .footer-col ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
    .footer-col a { color: rgba(255,255,255,.72); text-decoration: none; font-size: .92rem; }
    .footer-col a:hover { color: var(--amber); }
    .footer-bottom {
        display: flex; align-items: center; justify-content: space-between;
        gap: 14px; padding-top: 22px; flex-wrap: wrap;
        font-size: .82rem; opacity: .7;
    }

    /* ===== Mobile bottom nav ===== */
    .bottom-nav {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: rgba(255,255,255,.97); backdrop-filter: blur(14px);
        border-top: 1px solid var(--line);
        display: none; grid-template-columns: repeat(4, 1fr);
        padding: 8px 0 calc(8px + env(safe-area-inset-bottom));
        z-index: 39;
        box-shadow: 0 -8px 24px rgba(17,31,56,.08);
    }
    .bottom-nav a {
        display: flex; flex-direction: column; align-items: center; gap: 3px;
        color: var(--brand-navy); text-decoration: none;
        font: 700 .68rem/1 'Montserrat', sans-serif;
        padding: 6px 4px;
    }
    .bottom-nav a svg { width: 22px; height: 22px; }

    /* ===== Chrome responsive ===== */
    @media (max-width: 980px) {
        .footer-grid { grid-template-columns: 1fr 1fr; }
        .footer-trust { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 720px) {
        .wrap { width: min(calc(100% - 24px), 1240px); }
        .header-inner { padding: 12px 0; flex-wrap: wrap; }
        .header-search, .brand { min-width: 100%; }
        .brand { flex-direction: row; align-items: center; gap: 12px; }
        .brand-sub { display: none; }
        .header-actions { margin-left: auto; }
        .footer-grid { grid-template-columns: 1fr; gap: 22px; }
        .footer-payments { grid-template-columns: 1fr; }
        .bottom-nav { display: grid; }
        body { padding-bottom: 78px; }
        .activity-toast { bottom: 88px; left: 12px; right: 12px; max-width: none; }
    }
</style>
