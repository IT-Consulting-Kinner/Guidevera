<?php
/**
 * @var \App\View\AppView $this
 * @var array $auth
 * @var array $public
 * @var string $csrfToken
 */
$appName = $public['appName'] ?? 'AppProfileSafe';
$useLogo = ($public['useLogo'] ?? false) && !empty($public['logoPath'] ?? '');
$logoPath = $public['logoPath'] ?? '';
$appLang = $public['appLanguage'] ?? 'en';
$textDir = $public['textDirection'] ?? 'ltr';
$csrfToken = $csrfToken ?? '';
$isAuth = !empty($auth['id']);
$isAdmin = ($auth['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="<?= $appLang ?>" dir="<?= $textDir ?>">
<head>
    <title><?= h($appName) ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= $this->fetch('description') ?>">
    <link rel="stylesheet" href="/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/all.min.css">
    <link rel="stylesheet" href="/css/jquery-ui.css">
    <link rel="stylesheet" href="/css/summernote-lite.css">
    <link rel="stylesheet" href="/css/app<?= \Cake\Core\Configure::read('debug') ? '' : '.min' ?>.css">
    <?= $this->fetch('css') ?>
    <link rel="manifest" href="/site.webmanifest">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <script src="/js/jquery-3.5.1.js"></script>
    <script src="/js/jquery-ui.js"></script>
    <script src="/js/bootstrap.bundle.min.js"></script>
    <script src="/js/summernote-0.8.18/summernote-lite.min.js"></script>
    <?= $this->fetch('script') ?>
    <?php $nonce = $nonce ?? ($this->request->getAttribute('cspNonce') ?? ''); ?>
    <script nonce="<?= $nonce ?>">
        var arrErrorMessage = [], arrSuccessMessage = [];
        var displayMessageFadeDuration = 500, displayMessageDuration = 2000, displayMessageOffset = 50;
        
        var strCsrfToken = '<?= $csrfToken ?>';

        var t = {
            unsaved_changes: '<?= __('Unsaved changes will be lost. Do you really want to exit the edit mode?') ?>',
            leave_edit: '<?= __('Please save or close the editor first.') ?>',
            pages_error_create: '<?= __('Error creating the page!') ?>',
            pages_error_save: '<?= __('Error saving the page!') ?>',
            pages_error_delete: '<?= __('Error deleting the page!') ?>',
            pages_error_has_children: '<?= __('The page cannot be deleted because it contains sub-pages!') ?>',
            pages_error_show: '<?= __('Error loading the page! It may no longer be available. The main page is loading.') ?>',
            pages_error_edit: '<?= __('Error loading page for editing! It may no longer be available.') ?>',
            pages_error_status: '<?= __('Error setting the status!') ?>',
            pages_error_tree: '<?= __('Error loading the directory tree!') ?>',
            pages_error_save_tree: '<?= __('Error saving page position!') ?>',
            pages_error_save_state: '<?= __('Error while saving the folder status!') ?>',
            pages_error_book: '<?= __('Error loading the main page! Please try again later.') ?>',
            pages_error_index: '<?= __('Error loading the index!') ?>',
            pages_error_search: '<?= __('Error loading search results!') ?>',
            pages_saved: '<?= __('The page has been saved.') ?>',
            pages_deleted: '<?= __('The page has been deleted.') ?>',
            pages_published: '<?= __('The page has been published.') ?>',
            pages_unpublished: '<?= __('The page has been unpublished.') ?>',
            pages_confirm_delete: '<?= __('Do you really want to delete the page?') ?>',
            pages_new_unnamed: '<?= __('[New page]') ?>',
            pages_no_pages: '<?= __('No pages') ?>',
            pages_no_keywords: '<?= __('No keywords were found.') ?>',
            pages_no_results: '<?= __('No pages were found.') ?>',
            pages_expand_all: '<?= __('expand all') ?>',
            pages_collapse_all: '<?= __('close all') ?>',
            pages_title: '<?= __('Title') ?>',
            pages_description: '<?= __('Description (for search engines)') ?>',
            pages_content: '<?= __('Content') ?>',
            pages_keywords: '<?= __('Keywords') ?>',
            pages_edit: '<?= __('Edit page') ?>',
            pages_save: '<?= __('Save page') ?>',
            pages_delete: '<?= __('Delete page') ?>',
            pages_copy_link: '<?= __('Copy link') ?>',
            pages_print: '<?= __('Print page') ?>',
            pages_created: '<?= __('Created') ?>',
            pages_modified: '<?= __('Modified') ?>',
            pages_not_published: '<?= __('This page is not published.') ?>',
            pages_exit_edit: '<?= __('Exit edit mode') ?>',
            pages_publish: '<?= __('Publish page') ?>',
            pages_unpublish: '<?= __('Unpublish page') ?>',
            pages_browse: '<?= __('Select file or page') ?>',
            pages_label: '<?= __('Pages') ?>',
            pages_insert: '<?= __('Insert page') ?>',
            pages_placeholder_desc: '<?= __('Short description for search engines') ?>',
            pages_placeholder_kw: '<?= __('Comma-separated keywords') ?>',
            pages_search_btn: '<?= __('search') ?>',
            file_files: '<?= __('Files') ?>',
            edit_locale: '<?= __('Language') ?>',
            search_basic_mode: '<?= __('Basic search mode (short words may not be indexed)') ?>',
            // Comments
            comments_title: '<?= __('Internal Comments') ?>',
            comment_placeholder: '<?= __('Add a comment... Use @username to mention') ?>',
            comment_send: '<?= __('Send') ?>',
            comment_error: '<?= __('Failed to add comment.') ?>',
            // Cookie
            cookie_message: '<?= __('This site uses cookies to ensure the best experience.') ?>',
            // Feedback
            feedback_helpful: '<?= __('Was this page helpful?') ?>',
            feedback_yes: '<?= __('Yes') ?>',
            feedback_no: '<?= __('No') ?>',
            feedback_comment_placeholder: '<?= __('Optional: Tell us what could be improved...') ?>',
            feedback_submit: '<?= __('Submit feedback') ?>',
            feedback_thanks: '<?= __('Thank you for your feedback!') ?>',
            feedback_rate_limited: '<?= __('You have already submitted feedback for this page.') ?>',
            feedback_error: '<?= __('Failed to submit feedback.') ?>',
            // Revisions
            revisions_title: '<?= __('Page History') ?>',
            revision_restore: '<?= __('Restore this version') ?>',
            revision_back: '<?= __('Back to list') ?>',
            revision_detail: '<?= __('Revision Detail') ?>',
            revision_confirm_restore: '<?= __('Restore this revision? Current content will be saved first.') ?>',
            revision_restored: '<?= __('Revision restored successfully.') ?>',
            revision_error: '<?= __('Failed to restore revision.') ?>',
            revision_diff: '<?= __('Compare') ?>',
            revision_compare: '<?= __('Compare selected') ?>',
            revision_select_two: '<?= __('Select exactly two revisions to compare.') ?>',
            related_pages: '<?= __('Related pages') ?>',
            search_results_count: '<?= __('results') ?>',
            tags_label: '<?= __('Tags') ?>',
            tags_hint: '<?= __('comma-separated') ?>',
            workflow_status: '<?= __('Workflow') ?>',
            workflow_draft: '<?= __('Draft') ?>',
            workflow_review: '<?= __('In Review') ?>',
            workflow_published: '<?= __('Published') ?>',
            workflow_archived: '<?= __('Archived') ?>',
            subscribe_btn: '<?= __('Subscribe') ?>',
            unsubscribe: '<?= __('Unsubscribe') ?>',
            subscribed: '<?= __('Subscribed!') ?>',
            unsubscribed: '<?= __('Unsubscribed.') ?>',
            acknowledge_btn: '<?= __('I have read and understood this page') ?>',
            acknowledged: '<?= __('Acknowledged.') ?>',
            file_uploaded: '<?= __('File uploaded.') ?>',
            file_deleted: '<?= __('File deleted.') ?>',
            file_error_upload: '<?= __('Upload failed.') ?>',
            file_error_delete: '<?= __('Could not delete the file.') ?>',
            file_error_no_file: '<?= __('No file selected') ?>',
            file_confirm_delete: '<?= __('Do you really want to delete this file?') ?>',
            file_no_files: '<?= __('No files available. Drag files here to upload.') ?>',
            user_saved: '<?= __('The change has been saved.') ?>',
            user_error_save: '<?= __('Error saving change!') ?>',
            user_updated: '<?= __('User data saved.') ?>',
            user_deleted: '<?= __('The user was deleted.') ?>',
            user_error_delete: '<?= __('Error deleting the user!') ?>',
            user_confirm_delete: '<?= __('Are you sure you want to delete the user?') ?>'
        };

        jQuery.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (settings.type && settings.type.toUpperCase() === 'POST') {
                    xhr.setRequestHeader('X-CSRF-Token', strCsrfToken);
                }
            }
        });

        function show_error(text) {
            if (!text) return;
            arrErrorMessage.push(text);
            display_error();
        }
        function display_error() {
            if (jQuery('#cover_error').is(':visible') || jQuery('#cover_success').is(':visible')) { setTimeout(display_error, displayMessageOffset); return; }
            var msg = arrErrorMessage.shift();
            if (!msg) return;
            jQuery('#errortext').html(msg);
            jQuery('#cover_error').stop(true,true).show().delay(displayMessageDuration).fadeOut(displayMessageFadeDuration);
            if (arrErrorMessage.length) setTimeout(display_error, displayMessageOffset);
        }
        function show_success(text) {
            if (!text) return;
            arrSuccessMessage.push(text);
            display_success();
        }
        function display_success() {
            if (jQuery('#cover_error').is(':visible') || jQuery('#cover_success').is(':visible')) { setTimeout(display_success, displayMessageOffset); return; }
            var msg = arrSuccessMessage.shift();
            if (!msg) return;
            jQuery('#successtext').html(msg);
            jQuery('#cover_success').stop(true,true).show().delay(displayMessageDuration).fadeOut(displayMessageFadeDuration);
            if (arrSuccessMessage.length) setTimeout(display_success, displayMessageOffset);
        }

        function load_module(link, target) {
            if ((!window.pageState || !window.pageState.hasChanges) || confirm(t.unsaved_changes)) {
                if (window.pageState) window.pageState.hasChanges = false;
                if (!target || target === '') window.location.href = link;
                if (target === 'blank') window.open(link, '_blank');
            }
        }
        function message_leave_edit() { show_error(t.leave_edit); }
        function add_text_direction(dir) { jQuery('html').attr('dir', dir); }

        // Dark mode, font size → /js/init.js

        // Mobile sidebar toggle
        function toggleSidebar() {
            jQuery('.app-sidebar').toggleClass('mobile-open');
            jQuery('.sidebar-backdrop').toggleClass('open');
        }
        function closeSidebar() {
            jQuery('.app-sidebar').removeClass('mobile-open');
            jQuery('.sidebar-backdrop').removeClass('open');
        }
    </script>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="Menu">
            <span class="fas fa-bars"></span>
        </button>
        <a href="/" class="app-header__brand">
            <?php if ($useLogo): ?>
                <img src="<?= h($logoPath) ?>" alt="<?= h($appName) ?>">
            <?php else: ?>
                <?= h($appName) ?>
            <?php endif; ?>
        </a>
        <div class="app-header__spacer"></div>
        <?php if ($public['enableDarkMode'] ?? false): ?>
        <button class="sidebar-toggle-btn" id="darkModeToggle" onclick="toggleDarkMode()" title="<?= __('Toggle dark mode') ?>" style="display:inline-flex;margin-right:0.25rem">
            <span class="fas fa-moon" id="darkModeIcon"></span>
        </button>
        <?php endif; ?>
        <?php if ($public['enableFontSize'] ?? false): ?>
        <button class="sidebar-toggle-btn" onclick="changeFontSize(-1)" title="A-" style="display:inline-flex;margin-right:0;font-size:0.8rem;font-weight:700">A-</button>
        <button class="sidebar-toggle-btn" onclick="changeFontSize(1)" title="A+" style="display:inline-flex;margin-right:0.25rem;font-size:1.1rem;font-weight:700">A+</button>
        <?php endif; ?>
        <?php if ($isAuth): ?>
        <div class="app-header__user">
            <div class="app-header__user-btn" onclick="jQuery('.app-header__dropdown').toggleClass('open')">
                <img src="/img/<?= h($auth['gender'] ?? 'male') ?>.png" alt="">
                <span><?= h($auth['fullname'] ?? '') ?></span>
                <span class="fas fa-chevron-down" style="font-size:0.65rem"></span>
            </div>
            <div class="app-header__dropdown">
                <a href="javascript:" onclick="load_module('/user/profil')"><span class="fas fa-user-edit"></span> <?= __('Edit profile') ?></a>
                <?php if ($isAdmin): ?>
                <a href="javascript:" onclick="load_module('/user')"><span class="fas fa-users"></span> <?= __('Manage users') ?></a>
                <a href="javascript:" onclick="load_module('/file')"><span class="fas fa-upload"></span> <?= __('Manage files') ?></a>
                <?php endif; ?>
                <a href="javascript:" onclick="load_module('/pages')"><span class="far fa-file-alt"></span> <?= __('Manage pages') ?></a>
                <?php if ($isAdmin && ($public['enablePrint'] ?? false)): ?>
                <a href="javascript:" onclick="load_module('/pages/print_all', 'blank')"><span class="fas fa-print"></span> <?= __('Print book') ?></a>
                <?php endif; ?>
                <hr>
                <a href="javascript:" onclick="load_module('/user/logout')"><span class="fas fa-sign-out-alt"></span> <?= __('Logout user') ?></a>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <!-- Mobile sidebar backdrop -->
    <div class="sidebar-backdrop" onclick="closeSidebar()"></div>

    <!-- Main content -->
    <div class="app-main" id="main">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
    </div>

    <!-- Notifications -->
    <div id="cover_error"><div id="error"><span id="errortext"></span></div></div>
    <div id="cover_success"><div id="success"><span id="successtext"></span></div></div>

    <?php $nonce = $this->request->getAttribute('cspNonce') ?? ''; ?>
    <script src="/js/init.js" nonce="<?= $nonce ?>"></script>
    <script nonce="<?= $nonce ?>">
    jQuery(document).ready(function() {
        <?php if ($textDir === 'rtl'): ?>add_text_direction('rtl');<?php endif; ?>
        jQuery(document).on('click', function(e) {
            if (!jQuery(e.target).closest('.app-header__user').length) {
                jQuery('.app-header__dropdown').removeClass('open');
            }
        });
    });
    </script>
    <?php if ($public['enableCookieConsent'] ?? false): ?>
    <!-- Cookie Consent Banner -->
    <div id="cookieBanner" style="display:none;position:fixed;bottom:0;left:0;right:0;background:var(--bg-surface);border-top:1px solid var(--border-color);padding:1rem 2rem;z-index:9999;box-shadow:0 -2px 8px rgba(0,0,0,0.1);text-align:center">
        <span style="font-size:0.9rem"><?= __('This site uses cookies to ensure the best experience.') ?></span>
        <button onclick="acceptCookies()" style="margin-left:1rem;padding:0.4rem 1.25rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);cursor:pointer;font-size:0.85rem"><?= __('Accept') ?></button>
        <button onclick="rejectCookies()" style="margin-left:0.5rem;padding:0.4rem 1.25rem;background:var(--bg-hover);color:var(--text-primary);border:1px solid var(--border-color);border-radius:var(--radius-sm);cursor:pointer;font-size:0.85rem"><?= __('Reject') ?></button>
    </div>
    <?php endif; ?>
</body>
</html>
