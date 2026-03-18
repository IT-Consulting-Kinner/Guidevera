<?php

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->scope('/', function (RouteBuilder $b): void {
        $b->connect('/', ['controller' => 'Pages', 'action' => 'index']);
        $b->connect('/sitemap.xml', ['controller' => 'Pages', 'action' => 'sitemap']);
        $b->connect('/downloads/*', ['controller' => 'Files', 'action' => 'download']);

        // Pages: Core AJAX (read operations)
        $b->connect('/pages/get_tree', ['controller' => 'Pages', 'action' => 'getTree']);
        $b->connect('/pages/show', ['controller' => 'Pages', 'action' => 'show']);
        $b->connect('/pages/edit', ['controller' => 'Pages', 'action' => 'edit']);
        $b->connect('/pages/search', ['controller' => 'Pages', 'action' => 'search']);
        $b->connect('/pages/browse', ['controller' => 'Pages', 'action' => 'browse']);

        // Pages: Core AJAX (write operations — POST only)
        $b->connect('/pages/save', ['controller' => 'Pages', 'action' => 'save'])->setMethods(['POST']);
        $b->connect('/pages/save_content_silent', ['controller' => 'Pages', 'action' => 'saveContentSilent'])->setMethods(['POST']);
        $b->connect('/pages/create', ['controller' => 'Pages', 'action' => 'create'])->setMethods(['POST']);
        $b->connect('/pages/delete', ['controller' => 'Pages', 'action' => 'delete'])->setMethods(['POST']);
        $b->connect('/pages/set_status', ['controller' => 'Pages', 'action' => 'setStatus'])->setMethods(['POST']);
        $b->connect('/pages/update_order', ['controller' => 'Pages', 'action' => 'updateOrder'])->setMethods(['POST']);
        $b->connect('/pages/update_parent', ['controller' => 'Pages', 'action' => 'updateParent'])->setMethods(['POST']);
        $b->connect('/pages/index', ['controller' => 'Pages', 'action' => 'buildIndex'])->setMethods(['POST']);
        $b->connect('/pages/upload_media', ['controller' => 'Pages', 'action' => 'uploadMedia'])->setMethods(['POST']);

        // Pages: Print
        $b->connect('/pages/print_all', ['controller' => 'Pages', 'action' => 'printAll']);
        $b->connect('/pages/{id}/print/*', ['controller' => 'Pages', 'action' => 'printPage'], ['id' => '\d+',
            'pass' => ['id']]);

        // Pages: Dashboard, Trash, Export, Stats, Audit
        $b->connect('/pages/dashboard', ['controller' => 'Pages', 'action' => 'dashboard']);
        $b->connect('/pages/trash', ['controller' => 'Pages', 'action' => 'trash']);
        $b->connect('/pages/trash_restore', ['controller' => 'Pages', 'action' => 'trashRestore'])->setMethods(['POST']);
        $b->connect('/pages/trash_purge', ['controller' => 'Pages', 'action' => 'trashPurge'])->setMethods(['POST']);
        $b->connect('/pages/export_md', ['controller' => 'Pages', 'action' => 'exportMarkdown'])->setMethods(['POST']);
        $b->connect('/pages/export_pdf', ['controller' => 'Pages', 'action' => 'exportPdf'])->setMethods(['POST']);
        $b->connect('/pages/stats', ['controller' => 'Pages', 'action' => 'stats']);
        $b->connect('/pages/audit_log', ['controller' => 'Pages', 'action' => 'auditLog']);

        // v10: Workflow, Tags, Quality
        $b->connect('/pages/set_workflow', ['controller' => 'Pages', 'action' => 'setWorkflowStatus'])->setMethods(['POST']);
        $b->connect('/pages/review_queue', ['controller' => 'Pages', 'action' => 'reviewQueue']);
        $b->connect('/pages/tags', ['controller' => 'Pages', 'action' => 'tags']);
        $b->connect('/pages/save_tags', ['controller' => 'Pages', 'action' => 'saveTags'])->setMethods(['POST']);
        $b->connect('/pages/related', ['controller' => 'Pages', 'action' => 'relatedPages']);
        $b->connect('/pages/quality', ['controller' => 'Pages', 'action' => 'qualityReport']);

        // v11: Subscriptions, Acknowledgements, Inline Comments, Analytics, Import, Links, Stale, Translation, Reviews
        $b->connect('/pages/subscribe', ['controller' => 'Pages', 'action' => 'subscribe'])->setMethods(['POST']);
        $b->connect('/pages/subscription_status', ['controller' => 'Pages', 'action' => 'subscriptionStatus']);
        $b->connect('/pages/acknowledge', ['controller' => 'Pages', 'action' => 'acknowledge'])->setMethods(['POST']);
        $b->connect('/pages/ack_status', ['controller' => 'Pages', 'action' => 'ackStatus']);
        $b->connect('/pages/ack_report', ['controller' => 'Pages', 'action' => 'ackReport']);
        $b->connect('/pages/inline_comments', ['controller' => 'Pages', 'action' => 'inlineComments']);
        $b->connect('/pages/add_inline_comment', ['controller' => 'Pages', 'action' => 'addInlineComment'])->setMethods(['POST']);
        $b->connect('/pages/resolve_inline_comment', ['controller' => 'Pages', 'action' => 'resolveInlineComment'])->setMethods(['POST']);
        $b->connect('/pages/analytics', ['controller' => 'Pages', 'action' => 'analytics']);
        $b->connect('/pages/import', ['controller' => 'Pages', 'action' => 'import'])->setMethods(['POST']);
        $b->connect('/pages/link_suggest', ['controller' => 'Pages', 'action' => 'linkSuggest']);
        $b->connect('/pages/stale_list', ['controller' => 'Pages', 'action' => 'staleList']);
        $b->connect('/pages/translation_status', ['controller' => 'Pages', 'action' => 'translationStatus']);
        $b->connect('/pages/assign_reviewer', ['controller' => 'Pages', 'action' => 'assignReviewer'])->setMethods(['POST']);
        $b->connect('/pages/review_decision', ['controller' => 'Pages', 'action' => 'reviewDecision'])->setMethods(['POST']);
        $b->connect('/pages/page_reviews', ['controller' => 'Pages', 'action' => 'pageReviews']);

        // Media Library
        $b->connect('/media', ['controller' => 'Media', 'action' => 'index']);
        $b->connect('/media/replace', ['controller' => 'Media', 'action' => 'replace'])->setMethods(['POST']);

        // Revisions
        $b->connect('/pages/revisions', ['controller' => 'Revisions', 'action' => 'index']);
        $b->connect('/pages/revision_show', ['controller' => 'Revisions', 'action' => 'show']);
        $b->connect('/pages/revision_restore', ['controller' => 'Revisions', 'action' => 'restore'])->setMethods(['POST']);

        // Feedback
        $b->connect('/pages/feedback', ['controller' => 'Feedback', 'action' => 'submit'])->setMethods(['POST']);
        $b->connect('/pages/feedback_moderate', ['controller' => 'Feedback', 'action' => 'moderate'])->setMethods(['POST']);
        $b->connect('/pages/feedback_list', ['controller' => 'Feedback', 'action' => 'pending']);

        // Comments
        $b->connect('/pages/comments', ['controller' => 'Comments', 'action' => 'index']);
        $b->connect('/pages/comment_add', ['controller' => 'Comments', 'action' => 'add'])->setMethods(['POST']);
        $b->connect('/pages/comment_delete', ['controller' => 'Comments', 'action' => 'delete'])->setMethods(['POST']);

        // Pages with ID (must be AFTER specific routes)
        $b->connect('/pages/{id}/*', ['controller' => 'Pages', 'action' => 'index'], ['id' => '\d+', 'pass' => ['id']]);
        $b->connect('/pages', ['controller' => 'Pages', 'action' => 'index']);

        // Service Worker

        // Files
        $b->connect('/file', ['controller' => 'Files', 'action' => 'index']);
        $b->connect('/file/list', ['controller' => 'Files', 'action' => 'listFiles']);
        $b->connect('/file/upload', ['controller' => 'Files', 'action' => 'upload'])->setMethods(['POST']);
        $b->connect('/file/delete', ['controller' => 'Files', 'action' => 'delete'])->setMethods(['POST']);
        $b->connect('/file/browse', ['controller' => 'Files', 'action' => 'browse']);
        $b->connect('/file/create_folder', ['controller' => 'Files', 'action' => 'createFolder'])->setMethods(['POST']);
        $b->connect('/file/rename_folder', ['controller' => 'Files', 'action' => 'renameFolder'])->setMethods(['POST']);
        $b->connect('/file/delete_folder', ['controller' => 'Files', 'action' => 'deleteFolder'])->setMethods(['POST']);
        $b->connect('/file/move_file', ['controller' => 'Files', 'action' => 'moveFile'])->setMethods(['POST']);
        $b->connect('/file/move_folder', ['controller' => 'Files', 'action' => 'moveFolder'])->setMethods(['POST']);
        $b->connect('/file/update_file', ['controller' => 'Files', 'action' => 'updateFile'])->setMethods(['POST']);

        // Users
        $b->connect('/user/login', ['controller' => 'Users', 'action' => 'login']);
        $b->connect('/user/logout', ['controller' => 'Users', 'action' => 'logout']);
        $b->connect('/user/relogin', ['controller' => 'Users', 'action' => 'relogin'])->setMethods(['POST']);
        $b->connect('/user/profil', ['controller' => 'Users', 'action' => 'profil']);
        $b->connect('/user/change-password', ['controller' => 'Users', 'action' => 'changePassword']);
        $b->connect('/user/save_page_tree', ['controller' => 'Users', 'action' => 'savePageTree'])->setMethods(['POST']);
        $b->connect('/user/search_users', ['controller' => 'Users', 'action' => 'searchUsers']);
        $b->connect('/user/save', ['controller' => 'Users', 'action' => 'save'])->setMethods(['POST']);
        $b->connect('/user/delete', ['controller' => 'Users', 'action' => 'deleteUser'])->setMethods(['POST']);
        $b->connect('/user', ['controller' => 'Users', 'action' => 'index']);
        $b->connect('/user/create', ['controller' => 'Users', 'action' => 'create']);

        // No fallbacks() — all routes are explicitly defined
    });
};
