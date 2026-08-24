<?php
require_once __DIR__ . '/auth.php'; require_admin();
if (!is_admin()) { http_response_code(403); exit('Only the administrator can manage moderators.'); }
require_once __DIR__ . '/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        $response = api_request('DELETE', '/api/moderators/' . (int)($_POST['id'] ?? 0), null, admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Moderator removed.' : api_message($response, 'Could not remove moderator.'));
    } else {
        $response = api_request('POST', '/api/moderators', ['username'=>trim($_POST['username']??''), 'password'=>$_POST['password']??''], admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Moderator account created.' : api_message($response, 'Could not create moderator.'));
    }
    header('Location: /admin/moderators.php'); exit;
}
$response = api_request('GET', '/api/moderators', null, admin_token());
$moderators = $response['ok'] ? $response['data'] : [];
admin_header('Moderators', 'moderators');
?>
<div class="admin-list-heading"><div><h2>Moderator access</h2><p>Create staff credentials. Moderators can manage posts, characters, and comments—but not accounts or site settings.</p></div></div>
<div class="settings-grid"><section class="admin-card form-card"><h3>Add moderator</h3><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create"><label>Username<input name="username" minlength="3" maxlength="40" pattern="[A-Za-z0-9_]+" required></label><label>Password<input type="password" name="password" minlength="6" required></label><button class="button primary full" type="submit">Create moderator →</button></form></section><section class="admin-card"><div class="admin-card-head"><div><h2>Current moderators</h2><p><?= count($moderators) ?> active account<?= count($moderators)===1?'':'s' ?></p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Username</th><th>Created</th><th></th></tr></thead><tbody><?php foreach ($moderators as $moderator): ?><tr><td><strong><?= e($moderator['username']) ?></strong></td><td><?= e(format_date($moderator['created_at'])) ?></td><td class="actions"><form method="post" data-confirm="Remove this moderator?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$moderator['id'] ?>"><button type="submit">Remove</button></form></td></tr><?php endforeach; ?></tbody></table></div></section></div>
<?php admin_footer(); ?>
