<?php
require_once __DIR__ . '/auth.php'; require_admin(); require_once __DIR__ . '/layout.php';
$posts = api_data('/api/posts'); $characters = api_data('/api/characters');
$published = array_filter($posts, fn($post) => $post['status'] === 'published');
$drafts = array_filter($posts, fn($post) => $post['status'] === 'draft');
admin_header('Overview', 'dashboard');
?>
<section class="dashboard-welcome"><div><span class="eyebrow">Good to see you, <?= e(current_user()['username'] ?? 'staff') ?></span><h2>Your universe at a glance.</h2><p>Keep the AniScope library fresh, thoughtful, and worth returning to.</p></div><a class="button primary" href="/admin/posts.php?new=1">+ New post</a></section>
<section class="stat-grid"><div class="stat-card"><span>Total posts</span><strong><?= count($posts) ?></strong><small><?= count($published) ?> published</small></div><div class="stat-card"><span>Characters</span><strong><?= count($characters) ?></strong><small>Original profiles</small></div><div class="stat-card"><span>Drafts</span><strong><?= count($drafts) ?></strong><small>Waiting for review</small></div><div class="stat-card accent"><span>Database</span><strong><?= $posts || $characters ? 'Live' : 'Ready' ?></strong><small>PHP and MySQL</small></div></section>
<section class="admin-card"><div class="admin-card-head"><div><h2>Recent stories</h2><p>Your latest editorial activity.</p></div><a href="/admin/posts.php">Manage all →</a></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Story</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php foreach (array_slice($posts, 0, 6) as $post): ?><tr><td><div class="table-title"><img src="<?= e(image_url($post['image_url'])) ?>" alt=""><strong><?= e($post['title']) ?></strong></div></td><td><?= e($post['category']) ?></td><td><span class="status <?= e($post['status']) ?>"><?= e($post['status']) ?></span></td><td><?= e(format_date($post['updated_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_footer(); ?>
