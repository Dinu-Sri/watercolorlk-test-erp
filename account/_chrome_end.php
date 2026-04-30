</main>
<div class="account-chrome">
<?php
include __DIR__ . '/../partials/site-footer.php';
include __DIR__ . '/../partials/site-scripts.php';
?>
</div>
<script>
/* Rewrite header/footer relative asset paths so they resolve from /account/ */
document.querySelectorAll('.account-chrome a[href]').forEach(function(a) {
    var h = a.getAttribute('href');
    if (!h || /^(https?:|#|mailto:|tel:|\/|javascript:)/.test(h)) return;
    a.setAttribute('href', '../' + h);
});
document.querySelectorAll('.account-chrome img[src]').forEach(function(img) {
    var s = img.getAttribute('src');
    if (!s || /^(https?:|data:|\/)/.test(s)) return;
    img.setAttribute('src', '../' + s);
});
</script>
</body>
</html>
