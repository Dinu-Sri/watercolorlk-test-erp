<?php
/**
 * Shared chrome JS — runs on every page.
 * Wires up: bottom-nav search/cart buttons, activity ticker, promo bar dismiss.
 * Does NOT bind to the header search input or the cart button — pages handle those.
 */
?>
<script src="assets/js/cart.js?v=1" defer></script>
<script>
(function() {
    /* Bottom nav: focus header search */
    const bs = document.getElementById('bottomNavSearch');
    if (bs) {
        bs.addEventListener('click', (e) => {
            e.preventDefault();
            const s = document.getElementById('search');
            if (s) { s.focus(); s.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        });
    }
    /* Bottom nav cart navigates via cart.js href, no JS needed here. */

    /* Promo bar dismiss (resets at next visit naturally if not stored) */
    const pb = document.getElementById('promoBar');
    const pc = document.getElementById('promoClose');
    if (pb && pc) {
        if (sessionStorage.getItem('wlk_promo_hidden') === '1') pb.classList.add('is-hidden');
        pc.addEventListener('click', () => {
            pb.classList.add('is-hidden');
            sessionStorage.setItem('wlk_promo_hidden', '1');
        });
    }

    /* Activity ticker */
    const toast = document.getElementById('activityToast');
    const text = document.getElementById('actText');
    const sub = document.getElementById('actSub');
    if (!toast || !text) return;
    const names = ['Nimal', 'Ayesha', 'Dilani', 'Kasun', 'Ravi', 'Tharushi', 'Sahan', 'Imali', 'Pasindu', 'Hiruni', 'Janani', 'Sanduni'];
    const cities = ['Colombo', 'Kandy', 'Galle', 'Negombo', 'Jaffna', 'Matara', 'Kurunegala', 'Anuradhapura', 'Ratnapura', 'Batticaloa'];
    const verbs = ['just ordered', 'just added to cart', 'is viewing', 'just bought'];
    const subs = ['Verified order', 'Live on site', 'Just now', 'Confirmed checkout'];
    function rand(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
    function pickProductName() {
        const cards = document.querySelectorAll('.deal-name, .name, h1, .best-name');
        if (!cards.length) return 'a watercolor product';
        const c = cards[Math.floor(Math.random() * cards.length)];
        return (c.textContent || '').trim().slice(0, 60) || 'a watercolor product';
    }
    let rounds = 0;
    function show() {
        const name = rand(names);
        const city = rand(cities);
        const verb = rand(verbs);
        const product = pickProductName();
        const mins = 1 + Math.floor(Math.random() * 12);
        text.innerHTML = `<strong>${name}</strong> from ${city} ${verb} <em style="font-style:normal;color:var(--amber)">${product}</em> &middot; ${mins} min ago`;
        if (sub) sub.textContent = rand(subs);
        toast.classList.add('is-visible');
        setTimeout(() => toast.classList.remove('is-visible'), 5500);
    }
    setTimeout(show, 4500);
    setInterval(() => { rounds++; if (rounds > 12) return; show(); }, 12000);
})();
</script>
