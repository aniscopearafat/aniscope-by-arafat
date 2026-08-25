<?php
require_once __DIR__ . '/auth.php';
require_admin();
require_once __DIR__ . '/layout.php';
require_once dirname(__DIR__) . '/includes/streaming.php';

$streamAnimeList = stream_anime_list(true);
$editing = null; $showForm = isset($_GET['new']) || isset($_GET['edit']);
if (isset($_GET['edit'])) $editing = api_data('/api/posts/' . (int)$_GET['edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $response = api_request('DELETE', '/api/posts/' . (int)$_POST['id'], null, admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Post deleted.' : api_message($response, 'Delete failed.'));
    } else {
        try {
            $imageUrl = process_image_input('image_file', (string)($_POST['image_url'] ?? ''), (string)($_POST['current_image_url'] ?? ''));
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            header('Location: /admin/posts.php' . (!empty($_POST['id']) ? '?edit='.(int)$_POST['id'] : '?new=1')); exit;
        }
        $payload = ['title'=>trim($_POST['title']??''),'category'=>$_POST['category']??'Anime','excerpt'=>trim($_POST['excerpt']??''),'content'=>trim($_POST['content']??''),'image_url'=>$imageUrl,'youtube_url'=>trim($_POST['youtube_url']??''),
'stream_anime_id'=>(int)($_POST['stream_anime_id']??0),
'status'=>$_POST['status']??'published'];
        $id = (int)($_POST['id'] ?? 0); $response = api_request($id ? 'PUT' : 'POST', '/api/posts' . ($id ? '/'.$id : ''), $payload, admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? ($id ? 'Post updated.' : 'Post created.') : api_message($response, 'Save failed.'));
    }
    header('Location: /admin/posts.php'); exit;
}
$posts = api_data('/api/posts'); admin_header($showForm ? ($editing ? 'Edit post' : 'Create post') : 'Posts', 'posts');
$value = fn(string $key, string $default='') => e($editing[$key] ?? $default);
?>
<?php if ($showForm): ?>
<div class="editor-heading"><div><a href="/admin/posts.php">← Back to posts</a><h2><?= $editing ? 'Edit story' : 'Create a new story' ?></h2><p>Write clearly, choose original artwork, and publish when it feels ready.</p></div></div>
<form method="post" enctype="multipart/form-data" class="editor-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>"><input type="hidden" name="current_image_url" value="<?= $value('image_url', '/assets/images/post-fire.svg') ?>"><section class="admin-card admin-form"><label>Title<input name="title" value="<?= $value('title') ?>" required maxlength="200" placeholder="A story worth opening"></label><div class="form-row"><label>Category<select name="category"><?php foreach (['Anime','Manga','News'] as $category): ?><option <?= ($editing['category']??'Anime')===$category?'selected':'' ?>><?= $category ?></option><?php endforeach; ?></select></label><label>Status<select name="status"><option value="published" <?= ($editing['status']??'published')==='published'?'selected':'' ?>>Published</option><option value="draft" <?= ($editing['status']??'')==='draft'?'selected':'' ?>>Draft</option></select></label></div><label>Excerpt<textarea name="excerpt" rows="3" maxlength="500" required placeholder="A sharp, inviting summary…"><?= $value('excerpt') ?></textarea></label><label>Content<textarea name="content" rows="14" required placeholder="Write the full story here…"><?= $value('content') ?></textarea></label><label>YouTube URL <span class="optional-label">Optional</span><input type="url" name="youtube_url" value="<?= $value('youtube_url') ?>" placeholder="https://www.youtube.com/watch?v=..."></label><p class="form-hint">When supplied, a privacy-enhanced video frame appears inside the article. Leave empty to show no frame.</p>

<label>
Related Watch Anime
<span class="optional-label">Optional</span>
<select name="stream_anime_id">
<option value="0">No streaming link</option>
<?php foreach ($streamAnimeList as $streamAnime): ?>
<option value="<?= (int)$streamAnime['id'] ?>"
<?= (int)($editing['stream_anime_id'] ?? 0) === (int)$streamAnime['id'] ? 'selected' : '' ?>>
<?= e($streamAnime['title']) ?>
</option>
<?php endforeach; ?>
</select>
</label>

<p class="form-hint">
When selected, readers will see a Watch Anime button linking directly to this anime.
</p></section><aside class="admin-card admin-form sticky-editor"><label>Upload image <span class="optional-label">JPG, PNG, WebP or GIF</span><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-image-file></label><div class="image-divider"><span>or</span></div><label>Image URL or ImgBB HTML<input name="image_url" value="<?= $value('image_url', '/assets/images/post-fire.svg') ?>" placeholder="https://i.ibb.co/... or <a><img src=...></a>"></label><div class="image-preview"><img id="image-preview" src="<?= $value('image_url', '/assets/images/post-fire.svg') ?>" alt="Preview"></div><p class="form-hint">A new uploaded file takes priority. ImgBB embed HTML is reduced safely to its image URL.</p><button class="button primary full" type="submit"><?= $editing ? 'Save changes' : 'Publish story' ?> →</button></aside></form>
<?php else: ?>
<div class="admin-list-heading"><div><h2>All posts</h2><p>Create, edit, and organize your editorial library.</p></div><a class="button primary" href="?new=1">+ New post</a></div>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Story</th><th>Category</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody><?php foreach ($posts as $post): ?><tr><td><div class="table-title"><img src="<?= e(image_url($post['image_url'])) ?>" alt=""><strong><?= e($post['title']) ?></strong></div></td><td><?= e($post['category']) ?></td><td><span class="status <?= e($post['status']) ?>"><?= e($post['status']) ?></span></td><td><?= e(format_date($post['updated_at'])) ?></td><td class="actions"><a href="?edit=<?= (int)$post['id'] ?>">Edit</a><form method="post" data-confirm="Delete this post permanently?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; admin_footer(); ?>
