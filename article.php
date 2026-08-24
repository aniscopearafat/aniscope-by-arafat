<?php

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/ads.php';
require_once __DIR__ . '/includes/cards.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$post = $id ? api_data('/api/posts/' . $id) : [];

if (!$post || !is_array($post)) {

    http_response_code(404);

    $pageTitle = 'Story Not Found — AniScope';

    require __DIR__ . '/includes/header.php';

    echo '<section class="section not-found">';
    echo '<div class="container">';

    empty_state('That story may have moved or does not exist.');

    echo '</div>';
    echo '</section>';

    require __DIR__ . '/includes/footer.php';

    exit;
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    if (!logged_in()) {

        $_SESSION['return_to'] =
            '/article.php?id=' . $id . '#community';

        header('Location: /login.php');

        exit;
    }

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Add Comment
    |--------------------------------------------------------------------------
    */

    if ($action === 'comment') {

        $content = trim($_POST['content'] ?? '');

        $response = api_request(
            'POST',
            '/api/posts/' . $id . '/comments',
            [
                'content' => $content
            ],
            auth_token()
        );

        flash(
            $response['ok'] ? 'success' : 'error',
            $response['ok']
                ? 'Your comment is live.'
                : api_message(
                    $response,
                    'Could not add comment.'
                )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Like Post
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'like') {

        $response = api_request(
            'POST',
            '/api/posts/' . $id . '/like',
            [],
            auth_token()
        );

        flash(
            $response['ok'] ? 'success' : 'error',
            $response['ok']
                ? (
                    !empty($response['data']['liked'])
                        ? 'Post liked.'
                        : 'Like removed.'
                )
                : api_message(
                    $response,
                    'Could not update like.'
                )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Comment
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete_comment') {

        $commentId = (int) ($_POST['comment_id'] ?? 0);

        $response = api_request(
            'DELETE',
            '/api/comments/' . $commentId,
            null,
            auth_token()
        );

        flash(
            $response['ok'] ? 'success' : 'error',
            $response['ok']
                ? 'Comment removed.'
                : api_message(
                    $response,
                    'Could not remove comment.'
                )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect after POST
    |--------------------------------------------------------------------------
    */

    header(
        'Location: /article.php?id=' . $id . '#community'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Load Comments
|--------------------------------------------------------------------------
*/

$comments = api_data(
    '/api/posts/' . $id . '/comments'
);

if (!is_array($comments)) {
    $comments = [];
}


/*
|--------------------------------------------------------------------------
| Load Likes
|--------------------------------------------------------------------------
*/

$userId = logged_in()
    ? (int) (current_user()['id'] ?? 0)
    : 0;

$likesEndpoint =
    '/api/posts/' . $id . '/likes';

if ($userId) {

    $likesEndpoint .=
        '?user_id=' . $userId;
}

$likes = api_data($likesEndpoint);

if (!is_array($likes)) {
    $likes = [];
}


/*
|--------------------------------------------------------------------------
| YouTube
|--------------------------------------------------------------------------
*/

$youtubeEmbed = youtube_embed_url(
    $post['youtube_url'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

$flashMessage = pull_flash();


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/

$pageTitle =
    ($post['title'] ?? 'Article') .
    ' — AniScope by Arafat';

$activePage =
    strtolower($post['category'] ?? '');


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require __DIR__ . '/includes/header.php';

?>


<!-- =========================================================
     ARTICLE
========================================================= -->

<article class="article-page">


    <!-- =====================================================
         ARTICLE HERO
    ====================================================== -->

    <header class="article-hero">

        <img
            src="<?= e(image_url($post['image_url'] ?? '')) ?>"
            alt="<?= e($post['title'] ?? 'Article image') ?>"
        >

        <div class="article-shade"></div>

        <div class="container article-heading reveal">

            <span class="category-tag">
                <?= e($post['category'] ?? 'Anime') ?>
            </span>

            <h1>
                <?= e($post['title'] ?? 'Untitled Story') ?>
            </h1>

            <?php if (!empty($post['excerpt'])): ?>

                <p>
                    <?= e($post['excerpt']) ?>
                </p>

            <?php endif; ?>


            <div class="article-meta">

                <span>
                    By AniScope Editorial
                </span>

                <?php if (!empty($post['created_at'])): ?>

                    <span>
                        <?= e(format_date($post['created_at'])) ?>
                    </span>

                <?php endif; ?>

                <span>
                    5 min read
                </span>

            </div>

        </div>

    </header>


    <!-- =====================================================
         ARTICLE LAYOUT
    ====================================================== -->

    <div class="container article-layout">


        <!-- Share rail -->

        <div class="share-rail">

            <span>
                Share
            </span>

            <button
                type="button"
                data-share="copy"
                title="Copy article link"
            >
                ↗
            </button>

        </div>


        <!-- =================================================
             ARTICLE CONTENT
        ================================================== -->

        <div class="article-content">


            <!-- Excerpt -->

            <?php if (!empty($post['excerpt'])): ?>

                <p class="lead">
                    <?= e($post['excerpt']) ?>
                </p>

            <?php endif; ?>


            <!-- =================================================
                 NATIVE AD
            ================================================== -->

            <?php show_native_ad(); ?>


            <!-- =================================================
                 MAIN ARTICLE CONTENT
            ================================================== -->

            <?php

            $content = trim(
                (string) ($post['content'] ?? '')
            );

            /*
             * Normalize line endings.
             */
            $content = str_replace(
                ["\r\n", "\r"],
                "\n",
                $content
            );

            /*
             * Split paragraphs by blank lines.
             */
            $paragraphs = preg_split(
                "/\n\s*\n/",
                $content
            );

            if (!$paragraphs) {
                $paragraphs = [$content];
            }

            ?>


            <?php if ($content !== ''): ?>

                <?php foreach ($paragraphs as $paragraph): ?>

                    <?php

                    $paragraph = trim($paragraph);

                    if ($paragraph === '') {
                        continue;
                    }

                    ?>

                    <p>
                        <?= nl2br(e($paragraph)) ?>
                    </p>

                <?php endforeach; ?>

            <?php else: ?>

                <p class="no-content">
                    This story does not have any additional content yet.
                </p>

            <?php endif; ?>


            <!-- =================================================
                 YOUTUBE VIDEO
            ================================================== -->

            <?php if (!empty($youtubeEmbed)): ?>

                <div class="video-frame">

                    <iframe
                        src="<?= e($youtubeEmbed) ?>"
                        title="YouTube video for <?= e($post['title'] ?? 'AniScope') ?>"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen>
                    </iframe>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 QUOTE
            ================================================== -->

            <blockquote>
                Good stories do more than entertain—they give us another angle on courage, friendship, and change.
            </blockquote>


            <p>
                This article is part of the AniScope editorial library.
                All visual artwork on this site is original placeholder art.
            </p>


        </div>

    </div>

</article>


<!-- =========================================================
     COMMUNITY
========================================================= -->

<section
    class="community-section"
    id="community"
>

    <div class="container community-wrap">


        <!-- =================================================
             COMMUNITY HEADER
        ================================================== -->

        <div class="community-head">

            <div>

                <span class="eyebrow">
                    Reader community
                </span>

                <h2>
                    Join the conversation
                </h2>

            </div>


            <div class="article-actions">


                <!-- Like -->

                <form method="post">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="like"
                    >

                    <button
                        class="engagement-button <?= !empty($likes['liked']) ? 'liked' : '' ?>"
                        type="submit"
                    >

                        ♥
                        <span>
                            <?= (int) ($likes['count'] ?? 0) ?>
                        </span>

                    </button>

                </form>


                <!-- Share -->

                <button
                    class="engagement-button"
                    type="button"
                    data-share="copy"
                >
                    ↗ Share
                </button>

            </div>

        </div>


        <!-- =================================================
             FLASH MESSAGE
        ================================================== -->

        <?php if ($flashMessage): ?>

            <div class="alert <?= e($flashMessage['type']) ?>">

                <?= e($flashMessage['message']) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             COMMENT FORM
        ================================================== -->

        <?php if (logged_in()): ?>

            <form
                method="post"
                class="comment-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="comment"
                >


                <div class="comment-avatar">

                    <?= e(
                        strtoupper(
                            substr(
                                current_user()['username'] ?? 'U',
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>


                <label>

                    <span>
                        Commenting as
                        <?= e(
                            current_user()['username'] ?? ''
                        ) ?>
                    </span>

                    <textarea
                        name="content"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Add something thoughtful to the discussion…"
                    ></textarea>

                </label>


                <button
                    class="button primary"
                    type="submit"
                >
                    Post comment
                </button>

            </form>


        <?php else: ?>


            <!-- Login prompt -->

            <div class="login-to-comment">

                <div>
                    ✦
                </div>

                <h3>
                    Sign in to comment or like
                </h3>

                <p>
                    Reading and sharing stay open to everyone.
                    A free member account lets you join the discussion.
                </p>

                <div>

                    <a
                        class="button primary"
                        href="/login.php"
                    >
                        Login
                    </a>

                    <a
                        class="button ghost"
                        href="/signup.php"
                    >
                        Create account
                    </a>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             COMMENTS
        ================================================== -->

        <div class="comments-list">

            <h3>

                <?= count($comments) ?>

                comment<?= count($comments) === 1 ? '' : 's' ?>

            </h3>


            <?php foreach ($comments as $comment): ?>

                <article class="comment">


                    <div class="comment-avatar">

                        <?= e(
                            strtoupper(
                                substr(
                                    $comment['username'] ?? 'U',
                                    0,
                                    1
                                )
                            )
                        ) ?>

                    </div>


                    <div>

                        <div class="comment-meta">

                            <strong>
                                <?= e(
                                    $comment['username'] ?? 'User'
                                ) ?>
                            </strong>

                            <?php if (!empty($comment['created_at'])): ?>

                                <span>
                                    <?= e(
                                        format_date(
                                            $comment['created_at']
                                        )
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <p>
                            <?= nl2br(
                                e(
                                    $comment['content'] ?? ''
                                )
                            ) ?>
                        </p>


                        <!-- Delete comment -->

                        <?php if (
                            logged_in()
                            &&
                            (
                                is_staff()
                                ||
                                (
                                    (int) ($comment['user_id'] ?? 0)
                                    ===
                                    (int) (current_user()['id'] ?? 0)
                                )
                            )
                        ): ?>

                            <form
                                method="post"
                                data-confirm="Remove this comment?"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_comment"
                                >

                                <input
                                    type="hidden"
                                    name="comment_id"
                                    value="<?= (int) ($comment['id'] ?? 0) ?>"
                                >

                                <button
                                    class="comment-delete"
                                    type="submit"
                                >
                                    Delete
                                </button>

                            </form>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>


            <?php if (empty($comments)): ?>

                <p class="no-comments">
                    No comments yet. You could be the first.
                </p>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php require __DIR__ . '/includes/footer.php'; ?>