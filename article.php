<?php
require_once __DIR__ . '/includes/session.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$post = $id ? api_data('/api/posts/' . $id) : [];
if (!$post) { http_response_code(404); $pageTitle='Story Not Found — AniScope'; require __DIR__.'/includes/header.php'; require_once __DIR__.'/includes/cards.php'; echo '<section class="section not-found"><div class="container">'; empty_state('That story may have moved or does not exist.'); echo '</div></section>'; require __DIR__.'/includes/footer.php'; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!logged_in()) { $_SESSION['return_to'] = '/article.php?id=' . $id . '#community'; header('Location: /login.php'); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'comment') {
        $response = api_request('POST', '/api/posts/' . $id . '/comments', ['content' => trim($_POST['content'] ?? '')], auth_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Your comment is live.' : api_message($response, 'Could not add comment.'));
    } elseif ($action === 'like') {
        $response = api_request('POST', '/api/posts/' . $id . '/like', [], auth_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? ($response['data']['liked'] ? 'Post liked.' : 'Like removed.') : api_message($response, 'Could not update like.'));
    } elseif ($action === 'delete_comment') {
        $response = api_request('DELETE', '/api/comments/' . (int)($_POST['comment_id'] ?? 0), null, auth_token());
        flash($response['ok'] ? 'success' : 'error', $response['ok'] ? 'Comment removed.' : api_message($response, 'Could not remove comment.'));
    }
    header('Location: /article.php?id=' . $id . '#community'); exit;
}

$comments = api_data('/api/posts/' . $id . '/comments');
$userId = logged_in() ? (int)(current_user()['id'] ?? 0) : 0;
$likes = api_data('/api/posts/' . $id . '/likes' . ($userId ? '?user_id=' . $userId : ''));
$youtubeEmbed = youtube_embed_url($post['youtube_url'] ?? '');
$flashMessage = pull_flash();
$pageTitle = $post['title'] . ' — AniScope by Arafat'; $activePage = strtolower($post['category']);
require __DIR__ . '/includes/header.php';
?>
<article class="article-page">
    <header class="article-hero"><img src="<?= e(image_url($post['image_url'])) ?>" alt=""><div class="article-shade"></div><div class="container article-heading reveal"><span class="category-tag"><?= e($post['category']) ?></span><h1><?= e($post['title']) ?></h1><p><?= e($post['excerpt']) ?></p><div class="article-meta"><span>By AniScope Editorial</span><span><?= e(format_date($post['created_at'])) ?></span><span>5 min read</span></div></div></header>
    <div class="container article-layout"><div class="share-rail"><span>Share</span><button data-share="copy" title="Copy article link">↗</button></div><div class="article-content"><p class="lead"><?= e($post['excerpt']) ?><?php show_native_ad(); ?></p><?php foreach (preg_split('/\r?\n\r?\n/', $post['content']) as $paragraph): ?><p><?= nl2br(e($paragraph)) ?></p><?php endforeach; ?><?php if ($youtubeEmbed): ?><div class="video-frame"><iframe src="<?= e($youtubeEmbed) ?>" title="YouTube video for <?= e($post['title']) ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div><?php endif; ?><blockquote>Good stories do more than entertain—they give us another angle on courage, friendship, and change.</blockquote><p>This article is part of the AniScope editorial library. All visual artwork on this site is original placeholder art.</p></div></div>
</article>
<section class="community-section" id="community"><div class="container community-wrap">
    <div class="community-head"><div><span class="eyebrow">Reader community</span><h2>Join the conversation</h2></div><div class="article-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="like"><button class="engagement-button <?= !empty($likes['liked']) ? 'liked' : '' ?>" type="submit">♥ <span><?= (int)($likes['count'] ?? 0) ?></span></button></form><button class="engagement-button" data-share="copy">↗ Share</button></div></div>
    <?php if ($flashMessage): ?><div class="alert <?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div><?php endif; ?>
    <?php if (logged_in()): ?><form method="post" class="comment-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type("hidden", name("action", value("comment"));
    <div class("comment-avatar")"><?= e(strtoupper(substr(current_user()['username'] ?? 'U', 0, 1))) ?></div>
    <label>
        <span>Commenting as <?= e(current_user()['username'] ?? '') ?></span>
        <textarea name("content", rows("4", maxlength("2000", required placeholder("Add something thoughtful to the discussion…")))).</textarea>
    </label>
    <button class("button primary", type("submit"), value("Post comment"));</button>
</form><?php else: ?><div class("login-to-comment")?><div>✦</div><h3>Sign in to comment or like</h3><p>Reading and sharing stay open to everyone. A free member account lets you join the discussion.</p><div><a class("button primary", href("/login.php"), value("Login"));</a><a class("button ghost", href("/signup.php"), value("Create account"));</a></div></div><?php endif; ?>
    <div class="comments-list"><h3><?= count($comments) ?> comment<?= count($comments) === 1 ? '' : 's' ?></h3><?php foreach ($comments as $comment): ?><article class="comment"><div class="comment-avatar"><?= e(strtoupper(substr($comment['username'], 0, 1))) ?></div><div><div class="comment-meta"><strong><?= e($comment['username']) ?></strong><span><?= e(format_date($comment['created_at'])) ?></span></div><p><?= nl2br(e($comment['content'])) ?></p><?php if (logged_in() && (is_staff() || ((int)($comment['user_id'] ?? 0) === (int)(current_user()['id'] ?? 0)))): ?><form method="post" data-confirm="Remove this comment?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>"><button class="comment-delete" type="submit">Delete</button></form><?php endif; ?></div></article><?php endforeach; ?><?php if (!$comments): ?><p class="no-comments">No comments yet. You could be the first.</p><?php endif; ?></div>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
