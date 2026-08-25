<?php
require_once __DIR__ . '/ads.php';
?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="/index.php"><img src="/assets/images/logo-mark.svg" alt="" width="38" height="38"><span><?= e($siteSettings['site_name'] ?? 'AniScope by Arafat') ?><em>anime editorial</em></span></a>
            <p>Stories, worlds, and characters—viewed a little closer.</p>
        </div>
        <div><h3>Explore</h3><a href="/anime.php">Anime</a><a href="/manga.php">Manga</a><a href="/characters.php">Characters</a></div>
        <div><h3>Updates</h3><a href="/news.php">Anime News</a><a href="/signup.php">Join community</a></div>
        <div><h3><?= e($siteSettings['donation_label'] ?? 'Support AniScope') ?></h3><?php if (!empty($siteSettings['donation_number'])): ?><p class="donation-number"><?= e($siteSettings['donation_number']) ?></p><?php else: ?><p>All artwork is original placeholder art.</p><?php endif; ?></div>
    </div>
    <div class="container footer-bottom"><span><?= e($siteSettings['copyright_text'] ?? ('© '.date('Y').' AniScope by Arafat')) ?></span><span>Made with curiosity and a little neon.</span></div>
</footer>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/player.js"></script>
<?php show_social_bar(); ?>
<?php show_popunder(); ?>
</body>
</html>
