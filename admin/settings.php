<?php
require_once __DIR__ . '/auth.php'; require_admin();
if (!is_admin()) { http_response_code(403); exit('Only the administrator can change site settings.'); }
require_once __DIR__ . '/layout.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $payload = ['site_name'=>trim($_POST['site_name']??''),'site_tagline'=>trim($_POST['site_tagline']??''),'donation_label'=>trim($_POST['donation_label']??''),'donation_number'=>trim($_POST['donation_number']??''),'home_background_url'=>trim($_POST['home_background_url']??''),'copyright_text'=>trim($_POST['copyright_text']??'')];
    $response = api_request('PUT', '/api/settings', $payload, admin_token());
    flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Site information updated.' : api_message($response, 'Could not save settings.'));
    header('Location: /admin/settings.php'); exit;
}
$settings = site_settings(); admin_header('Site settings', 'settings');
?>
<div class="admin-list-heading"><div><h2>Site information</h2><p>Update public branding, homepage artwork, donation details, and footer copyright text.</p></div></div><section class="admin-card form-card wide-form"><form method="post" class="admin-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Site name<input name="site_name" value="<?= e($settings['site_name']??'') ?>" required></label><label>Site tagline<textarea name="site_tagline" rows="3" required><?= e($settings['site_tagline']??'') ?></textarea></label><label>Homepage background image URL <span class="optional-label">Local path or full URL</span><input name="home_background_url" value="<?= e($settings['home_background_url']??'/assets/images/hero-original.svg') ?>" placeholder="/assets/images/hero-original.svg" data-home-background-input></label><div class="home-background-preview"><img src="<?= e(image_url($settings['home_background_url']??'/assets/images/hero-original.svg')) ?>" alt="Homepage background preview" data-home-background-preview></div><p class="form-hint">Use an original or properly licensed image. Clear the field to restore the default AniScope artwork.</p><div class="form-row"><label>Donation label<input name="donation_label" value="<?= e($settings['donation_label']??'') ?>" placeholder="Support AniScope"></label><label>Donation number / account<input name="donation_number" value="<?= e($settings['donation_number']??'') ?>" placeholder="bKash / Nagad / payment number"></label></div><label>Copyright text<input name="copyright_text" value="<?= e($settings['copyright_text']??'') ?>" required></label><button class="button primary" type="submit">Save site information →</button></form></section>
<?php admin_footer(); ?>
