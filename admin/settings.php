<?php
require_once __DIR__ . '/auth.php'; require_admin();
if (!is_admin()) { http_response_code(403); exit('Only the administrator can change site settings.'); }
require_once __DIR__ . '/layout.php';

function settings_image_value(string $fieldName, string $fallback = ''): string
{
    $textValue = (string)($_POST[$fieldName] ?? '');
    $currentValue = (string)($_POST['current_' . $fieldName] ?? $fallback);
    $uploadField = $fieldName . '_file';
    $hasUpload = isset($_FILES[$uploadField]) && (int)($_FILES[$uploadField]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasUpload) return process_image_input($uploadField, $textValue, $currentValue);
    if (trim($textValue) === '') return $fallback;
    return process_image_input($uploadField, $textValue, $currentValue ?: $fallback);
}

function color_value(string $fieldName, string $fallback): string
{
    $textValue = trim((string)($_POST[$fieldName . '_text'] ?? ''));
    $value = $textValue !== '' ? $textValue : trim((string)($_POST[$fieldName] ?? $fallback));
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
}

function image_setting_control(string $fieldName, string $label, array $settings, string $default = ''): void
{
    $value = setting_value($settings, $fieldName, $default);
    $preview = $value !== '' ? image_url($value) : image_url($default);
    ?>
    <div class="image-setting" data-image-setting>
        <label><?= e($label) ?> <span class="optional-label">JPG, PNG, WebP or GIF</span>
            <input type="file" name="<?= e($fieldName) ?>_file" accept="image/jpeg,image/png,image/webp,image/gif" data-setting-file>
        </label>
        <div class="image-divider"><span>or</span></div>
        <label>Image URL or ImgBB HTML
            <input name="<?= e($fieldName) ?>" value="<?= e($value) ?>" placeholder="<?= e($default ?: 'https://i.ibb.co/... or <a><img src=...></a>') ?>" data-setting-image-input>
        </label>
        <input type="hidden" name="current_<?= e($fieldName) ?>" value="<?= e($value) ?>">
        <div class="home-background-preview"><img src="<?= e($preview ?: '/assets/images/hero-original.svg') ?>" alt="<?= e($label) ?> preview" data-setting-preview></div>
    </div>
<?php }

function color_control(string $fieldName, string $label, array $settings, string $default): void
{
    $value = theme_color($settings, $fieldName, $default);
    ?>
    <label><?= e($label) ?>
        <span class="color-field"><input type="color" name="<?= e($fieldName) ?>" value="<?= e($value) ?>"><input name="<?= e($fieldName) ?>_text" value="<?= e($value) ?>" data-color-text></span>
    </label>
<?php }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $payload = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_tagline' => trim($_POST['site_tagline'] ?? ''),
            'donation_label' => trim($_POST['donation_label'] ?? ''),
            'donation_number' => trim($_POST['donation_number'] ?? ''),
            'home_background_url' => settings_image_value('home_background_url', '/assets/images/hero-original.svg'),
            'anime_cover_url' => settings_image_value('anime_cover_url'),
            'manga_cover_url' => settings_image_value('manga_cover_url'),
            'news_cover_url' => settings_image_value('news_cover_url'),
            'characters_cover_url' => settings_image_value('characters_cover_url', '/assets/images/characters-banner.svg'),
            'login_cover_url' => settings_image_value('login_cover_url', '/assets/images/login-original.svg'),
            'theme_ink' => color_value('theme_ink', '#070711'),
            'theme_panel' => color_value('theme_panel', '#11111f'),
            'theme_panel2' => color_value('theme_panel2', '#17172a'),
            'theme_text' => color_value('theme_text', '#f5f3ff'),
            'theme_muted' => color_value('theme_muted', '#a5a3b8'),
            'theme_primary' => color_value('theme_primary', '#8b5cf6'),
            'theme_secondary' => color_value('theme_secondary', '#6d28d9'),
            'theme_accent' => color_value('theme_accent', '#38bdf8'),
            'copyright_text' => trim($_POST['copyright_text'] ?? ''),
        ];
        $response = api_request('PUT', '/api/settings', $payload, admin_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Site settings updated.' : api_message($response, 'Could not save settings.'));
    } catch (RuntimeException $exception) {
        flash('error', $exception->getMessage());
    }
    header('Location: /admin/settings.php'); exit;
}

$settings = site_settings(); admin_header('Site settings', 'settings');
?>
<div class="admin-list-heading"><div><h2>Site settings</h2><p>Control branding, covers, colors, donation details, and footer text.</p></div></div>
<form method="post" enctype="multipart/form-data" class="settings-page-grid admin-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <section class="admin-card form-card settings-panel">
        <h3>Branding</h3>
        <label>Site name<input name="site_name" value="<?= e($settings['site_name']??'') ?>" required></label>
        <label>Site tagline<textarea name="site_tagline" rows="3" required><?= e($settings['site_tagline']??'') ?></textarea></label>
        <div class="form-row"><label>Donation label<input name="donation_label" value="<?= e($settings['donation_label']??'') ?>" placeholder="Support AniScope"></label><label>Donation number / account<input name="donation_number" value="<?= e($settings['donation_number']??'') ?>" placeholder="bKash / Nagad / payment number"></label></div>
        <label>Copyright text<input name="copyright_text" value="<?= e($settings['copyright_text']??'') ?>" required></label>
    </section>

    <section class="admin-card form-card settings-panel">
        <h3>Site Covers</h3>
        <div class="cover-settings-grid">
            <?php image_setting_control('home_background_url', 'Homepage hero cover', $settings, '/assets/images/hero-original.svg'); ?>
            <?php image_setting_control('anime_cover_url', 'Anime page cover', $settings); ?>
            <?php image_setting_control('manga_cover_url', 'Manga page cover', $settings); ?>
            <?php image_setting_control('news_cover_url', 'News page cover', $settings); ?>
            <?php image_setting_control('characters_cover_url', 'Characters page cover', $settings, '/assets/images/characters-banner.svg'); ?>
            <?php image_setting_control('login_cover_url', 'Login and signup cover', $settings, '/assets/images/login-original.svg'); ?>
        </div>
    </section>

    <section class="admin-card form-card settings-panel">
        <h3>Theme Colors</h3>
        <div class="color-settings-grid">
            <?php color_control('theme_ink', 'Page background', $settings, '#070711'); ?>
            <?php color_control('theme_panel', 'Panel background', $settings, '#11111f'); ?>
            <?php color_control('theme_panel2', 'Second panel', $settings, '#17172a'); ?>
            <?php color_control('theme_text', 'Main text', $settings, '#f5f3ff'); ?>
            <?php color_control('theme_muted', 'Muted text', $settings, '#a5a3b8'); ?>
            <?php color_control('theme_primary', 'Primary accent', $settings, '#8b5cf6'); ?>
            <?php color_control('theme_secondary', 'Secondary accent', $settings, '#6d28d9'); ?>
            <?php color_control('theme_accent', 'Link accent', $settings, '#38bdf8'); ?>
        </div>
    </section>

    <div class="settings-savebar"><button class="button primary" type="submit">Save all settings →</button></div>
</form>
<?php admin_footer(); ?>
