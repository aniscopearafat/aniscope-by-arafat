<?php
require_once __DIR__ . '/auth.php'; require_admin(); require_once __DIR__ . '/layout.php';
$editing = null; $showForm = isset($_GET['new']) || isset($_GET['edit']);
if (isset($_GET['edit'])) $editing = api_data('/api/characters/' . (int)$_GET['edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $response = api_request('DELETE', '/api/characters/' . (int)$_POST['id'], null, admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Character deleted.' : api_message($response, 'Delete failed.'));
    } else {
        try {
            $imageUrl = process_image_input('image_file', (string)($_POST['image_url'] ?? ''), (string)($_POST['current_image_url'] ?? ''));
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            header('Location: /admin/characters.php' . (!empty($_POST['id']) ? '?edit='.(int)$_POST['id'] : '?new=1')); exit;
        }
        $payload = ['name'=>trim($_POST['name']??''),'anime_name'=>trim($_POST['anime_name']??''),'bio'=>trim($_POST['bio']??''),'abilities'=>trim($_POST['abilities']??''),'image_url'=>$imageUrl];
        $id = (int)($_POST['id'] ?? 0); $response = api_request($id ? 'PUT' : 'POST', '/api/characters' . ($id ? '/'.$id : ''), $payload, admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? ($id ? 'Character updated.' : 'Character created.') : api_message($response, 'Save failed.'));
    }
    header('Location: /admin/characters.php'); exit;
}
$characters = api_data('/api/characters'); admin_header($showForm ? ($editing ? 'Edit character' : 'Create character') : 'Characters', 'characters');
$value = fn(string $key, string $default='') => e($editing[$key] ?? $default);
?>
<?php if ($showForm): ?>
<div class="editor-heading"><div><a href="/admin/characters.php">← Back to characters</a><h2><?= $editing ? 'Edit character' : 'Create an original character' ?></h2><p>Build an original profile without using copyrighted character likenesses.</p></div></div>
<form method="post" enctype="multipart/form-data" class="editor-grid"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>"><input type="hidden" name="current_image_url" value="<?= $value('image_url', '/assets/images/character-blue.svg') ?>"><section class="admin-card admin-form"><div class="form-row"><label>Name<input name="name" value="<?= $value('name') ?>" required></label><label>Original series<input name="anime_name" value="<?= $value('anime_name') ?>" required></label></div><label>Biography<textarea name="bio" rows="8" required><?= $value('bio') ?></textarea></label><label>Abilities<textarea name="abilities" rows="5" required><?= $value('abilities') ?></textarea></label></section><aside class="admin-card admin-form sticky-editor"><label>Upload image <span class="optional-label">JPG, PNG, WebP or GIF</span><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" data-image-file></label><div class="image-divider"><span>or</span></div><label>Image URL or ImgBB HTML<input name="image_url" value="<?= $value('image_url', '/assets/images/character-blue.svg') ?>" placeholder="https://i.ibb.co/... or <a><img src=...></a>"></label><div class="image-preview portrait"><img id="image-preview" src="<?= $value('image_url', '/assets/images/character-blue.svg') ?>" alt="Preview"></div><p class="form-hint">A new uploaded file takes priority. ImgBB embed HTML is reduced safely to its image URL.</p><button class="button primary full" type="submit"><?= $editing ? 'Save changes' : 'Create character' ?> →</button></aside></form>
<?php else: ?>
<div class="admin-list-heading"><div><h2>Character library</h2><p>Manage original heroes, rivals, and wanderers.</p></div><a class="button primary" href="?new=1">+ New character</a></div><section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Character</th><th>Series</th><th>Abilities</th><th></th></tr></thead><tbody><?php foreach ($characters as $character): ?><tr><td><div class="table-title"><img src="<?= e(image_url($character['image_url'])) ?>" alt=""><strong><?= e($character['name']) ?></strong></div></td><td><?= e($character['anime_name']) ?></td><td><?= e($character['abilities']) ?></td><td class="actions"><a href="?edit=<?= (int)$character['id'] ?>">Edit</a><form method="post" data-confirm="Delete this character permanently?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$character['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; admin_footer(); ?>
