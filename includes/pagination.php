<?php

/*
|--------------------------------------------------------------------------
| AniScope Pagination
|--------------------------------------------------------------------------
|
| Maximum items displayed per public listing page.
|
*/

function paginate_items($items, $perPage = 20)
{
    if (!is_array($items)) {
        $items = [];
    }

    $perPage = max(1, (int) $perPage);

    $totalItems = count($items);

    $totalPages = max(
        1,
        (int) ceil($totalItems / $perPage)
    );

    $page = isset($_GET['page'])
        ? (int) $_GET['page']
        : 1;

    if ($page < 1) {
        $page = 1;
    }

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    return [
        'items' => array_slice(
            $items,
            $offset,
            $perPage
        ),
        'page' => $page,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'from' => $totalItems
            ? $offset + 1
            : 0,
        'to' => min(
            $offset + $perPage,
            $totalItems
        )
    ];
}


function pagination_url($page)
{
    $query = $_GET;

    unset($query['page']);

    $query['page'] = max(
        1,
        (int) $page
    );

    return '?' . http_build_query($query);
}


function render_pagination($currentPage, $totalPages)
{
    $currentPage = max(
        1,
        (int) $currentPage
    );

    $totalPages = max(
        1,
        (int) $totalPages
    );

    if ($totalPages <= 1) {
        return;
    }

    /*
     * Show current page plus nearby pages.
     */
    $start = max(
        1,
        $currentPage - 2
    );

    $end = min(
        $totalPages,
        $currentPage + 2
    );

    ?>

    <nav
        class="aniscope-pagination"
        aria-label="Pagination"
    >

        <?php if ($currentPage > 1): ?>

            <a
                class="pagination-button pagination-prev"
                href="<?= e(pagination_url($currentPage - 1)) ?>"
            >
                ← Previous
            </a>

        <?php else: ?>

            <span
                class="pagination-button disabled"
                aria-disabled="true"
            >
                ← Previous
            </span>

        <?php endif; ?>


        <div class="pagination-pages">

            <?php if ($start > 1): ?>

                <a
                    class="pagination-number"
                    href="<?= e(pagination_url(1)) ?>"
                >
                    1
                </a>

                <?php if ($start > 2): ?>
                    <span class="pagination-dots">…</span>
                <?php endif; ?>

            <?php endif; ?>


            <?php for ($i = $start; $i <= $end; $i++): ?>

                <?php if ($i === $currentPage): ?>

                    <span
                        class="pagination-number active"
                        aria-current="page"
                    >
                        <?= $i ?>
                    </span>

                <?php else: ?>

                    <a
                        class="pagination-number"
                        href="<?= e(pagination_url($i)) ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>


            <?php if ($end < $totalPages): ?>

                <?php if ($end < $totalPages - 1): ?>
                    <span class="pagination-dots">…</span>
                <?php endif; ?>

                <a
                    class="pagination-number"
                    href="<?= e(pagination_url($totalPages)) ?>"
                >
                    <?= $totalPages ?>
                </a>

            <?php endif; ?>

        </div>


        <?php if ($currentPage < $totalPages): ?>

            <a
                class="pagination-button pagination-next"
                href="<?= e(pagination_url($currentPage + 1)) ?>"
            >
                Next →
            </a>

        <?php else: ?>

            <span
                class="pagination-button disabled"
                aria-disabled="true"
            >
                Next →
            </span>

        <?php endif; ?>

    </nav>

    <?php
}
