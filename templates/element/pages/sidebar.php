<?php
/**
 * Pages sidebar element
 *
 * @var \App\View\AppView $this
 * @var bool $isAuth
 * @var bool $showTopNav
 * @var bool $showNavIcons
 * @var bool $showNavRoot
 * @var string $ssrNavHtml
 */
?>
<aside class="app-sidebar" id="sidebar">
    <?php if ($showTopNav): ?>
    <div class="sidebar-tabs" id="sidemenu">
        <div class="sidebar-tabs__tab active" id="sidemenu_content" onclick="show_sidebar('content')">
            <span class="far fa-copy"></span>
            <?= __('Pages') ?>
        </div>
        <div class="sidebar-tabs__tab" id="sidemenu_index" onclick="show_sidebar('index')">
            <span class="fas fa-bars"></span>
            <?= __('Index') ?>
        </div>
        <div class="sidebar-tabs__tab" id="sidemenu_search" onclick="show_sidebar('search')">
            <span class="fas fa-search"></span>
            <?= __('Search') ?>
        </div>
    </div>
    <?php endif; ?>

    <div id="sidebar_content" class="sidebar-panel">
        <!-- Template for new tree nodes (cloned by JS) -->
        <div style="display:none">
            <li id="new_li">
                <div class="hasmenu p-1"><span class="pe-2"></span><a href="javascript:"></a></div>
            </li>
        </div>
        <div id="new_page" style="display:none; text-align:center; padding:2rem 1rem">
            <?php if ($isAuth): ?>
                <button id="new_page_button" onclick="post_page_create(new_page);"><?= __('Create new page') ?></button>
            <?php else: ?>
                <p style="color:var(--text-secondary)"><?= __('No pages') ?></p>
                <a class="btn btn-primary btn-sm" href="/user/login"><?= __('login') ?></a>
            <?php endif; ?>
        </div>
        <nav>
            <ul id="page_navigation"<?= !empty($ssrNavHtml) && !$isAuth ? ' style="display:block"' : '' ?>>
                <?= $ssrNavHtml ?? '' ?>
            </ul>
        </nav>
    </div>
    <div id="sidebar_index" class="sidebar-panel" style="display:none"></div>
    <div id="sidebar_search" class="sidebar-panel" style="display:none"></div>
</aside>
