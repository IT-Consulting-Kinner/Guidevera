<?php
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->scope('/', function (RouteBuilder $b): void {
        $b->connect('/', ['controller' => 'Pages', 'action' => 'index']);
        $b->connect('/sitemap.xml', ['controller' => 'Pages', 'action' => 'sitemap']);
        $b->connect('/downloads/*', ['controller' => 'Files', 'action' => 'download']);

        // Pages: Core AJAX
        $b->connect('/pages/get_tree', ['controller' => 'Pages', 'action' => 'getTree']);
        $b->connect('/pages/show', ['controller' => 'Pages', 'action' => 'show']);
        $b->connect('/pages/edit', ['controller' => 'Pages', 'action' => 'edit']);
        $b->connect('/pages/save', ['controller' => 'Pages', 'action' => 'save']);
        $b->connect('/pages/create', ['controller' => 'Pages', 'action' => 'create']);
        $b->connect('/pages/delete', ['controller' => 'Pages', 'action' => 'delete']);
        $b->connect('/pages/set_status', ['controller' => 'Pages', 'action' => 'setStatus']);
        $b->connect('/pages/update_order', ['controller' => 'Pages', 'action' => 'updateOrder']);
        $b->connect('/pages/update_parent', ['controller' => 'Pages', 'action' => 'updateParent']);
        $b->connect('/pages/search', ['controller' => 'Pages', 'action' => 'search']);
        $b->connect('/pages/index', ['controller' => 'Pages', 'action' => 'buildIndex']);
        $b->connect('/pages/browse', ['controller' => 'Pages', 'action' => 'browse']);
        $b->connect('/pages/upload_media', ['controller' => 'Pages', 'action' => 'uploadMedia']);

        // Pages: Print
        $b->connect('/pages/print_all', ['controller' => 'Pages', 'action' => 'printAll']);
        $b->connect('/pages/{id}/print/*', ['controller' => 'Pages', 'action' => 'printPage'], ['id' => '\d+', 'pass' => ['id']]);

        // Pages: Dashboard, Trash, Export, Stats, Audit
        $b->connect('/pages/dashboard', ['controller' => 'Pages', 'action' => 'dashboard']);
        $b->connect('/pages/trash', ['controller' => 'Pages', 'action' => 'trash']);
        $b->connect('/pages/trash_restore', ['controller' => 'Pages', 'action' => 'trashRestore']);
        $b->connect('/pages/trash_purge', ['controller' => 'Pages', 'action' => 'trashPurge']);
        $b->connect('/pages/export_md', ['controller' => 'Pages', 'action' => 'exportMarkdown']);
        $b->connect('/pages/export_pdf', ['controller' => 'Pages', 'action' => 'exportPdf']);
        $b->connect('/pages/stats', ['controller' => 'Pages', 'action' => 'stats']);
        $b->connect('/pages/audit_log', ['controller' => 'Pages', 'action' => 'auditLog']);

        // v10: Workflow, Tags, Quality
        $b->connect('/pages/set_workflow', ['controller' => 'Pages', 'action' => 'setWorkflowStatus']);
        $b->connect('/pages/review_queue', ['controller' => 'Pages', 'action' => 'reviewQueue']);
        $b->connect('/pages/tags', ['controller' => 'Pages', 'action' => 'tags']);
        $b->connect('/pages/save_tags', ['controller' => 'Pages', 'action' => 'saveTags']);
        $b->connect('/pages/related', ['controller' => 'Pages', 'action' => 'relatedPages']);
        $b->connect('/pages/quality', ['controller' => 'Pages', 'action' => 'qualityReport']);

        // v11: Subscriptions, Acknowledgements, Inline Comments, Analytics, Import, Links, Stale, Translation, Reviews
        $b->connect('/pages/subscribe', ['controller' => 'Pages', 'action' => 'subscribe']);
        $b->connect('/pages/subscription_status', ['controller' => 'Pages', 'action' => 'subscriptionStatus']);
        $b->connect('/pages/acknowledge', ['controller' => 'Pages', 'action' => 'acknowledge']);
        $b->connect('/pages/ack_status', ['controller' => 'Pages', 'action' => 'ackStatus']);
        $b->connect('/pages/inline_comments', ['controller' => 'Pages', 'action' => 'inlineComments']);
        $b->connect('/pages/add_inline_comment', ['controller' => 'Pages', 'action' => 'addInlineComment']);
        $b->connect('/pages/resolve_inline_comment', ['controller' => 'Pages', 'action' => 'resolveInlineComment']);
        $b->connect('/pages/analytics', ['controller' => 'Pages', 'action' => 'analytics']);
        $b->connect('/pages/import', ['controller' => 'Pages', 'action' => 'import']);
        $b->connect('/pages/link_suggest', ['controller' => 'Pages', 'action' => 'linkSuggest']);
        $b->connect('/pages/stale_list', ['controller' => 'Pages', 'action' => 'staleList']);
        $b->connect('/pages/translation_status', ['controller' => 'Pages', 'action' => 'translationStatus']);
        $b->connect('/pages/assign_reviewer', ['controller' => 'Pages', 'action' => 'assignReviewer']);
        $b->connect('/pages/review_decision', ['controller' => 'Pages', 'action' => 'reviewDecision']);
        $b->connect('/pages/page_reviews', ['controller' => 'Pages', 'action' => 'pageReviews']);

        // Media Library
        $b->connect('/media', ['controller' => 'Media', 'action' => 'index']);
        $b->connect('/media/replace', ['controller' => 'Media', 'action' => 'replace']);

        // Revisions
        $b->connect('/pages/revisions', ['controller' => 'Revisions', 'action' => 'index']);
        $b->connect('/pages/revision_show', ['controller' => 'Revisions', 'action' => 'show']);
        $b->connect('/pages/revision_restore', ['controller' => 'Revisions', 'action' => 'restore']);

        // Feedback
        $b->connect('/pages/feedback', ['controller' => 'Feedback', 'action' => 'submit']);
        $b->connect('/pages/feedback_moderate', ['controller' => 'Feedback', 'action' => 'moderate']);
        $b->connect('/pages/feedback_list', ['controller' => 'Feedback', 'action' => 'pending']);

        // Comments
        $b->connect('/pages/comments', ['controller' => 'Comments', 'action' => 'index']);
        $b->connect('/pages/comment_add', ['controller' => 'Comments', 'action' => 'add']);
        $b->connect('/pages/comment_delete', ['controller' => 'Comments', 'action' => 'delete']);

        // Pages with ID (must be AFTER specific routes)
        $b->connect('/pages/{id}/*', ['controller' => 'Pages', 'action' => 'index'], ['id' => '\d+', 'pass' => ['id']]);
        $b->connect('/pages', ['controller' => 'Pages', 'action' => 'index']);

        // Service Worker

        // Files
        $b->connect('/file', ['controller' => 'Files', 'action' => 'index']);
        $b->connect('/file/{action}', ['controller' => 'Files']);

        // Users
        $b->connect('/user/login', ['controller' => 'Users', 'action' => 'login']);
        $b->connect('/user/logout', ['controller' => 'Users', 'action' => 'logout']);
        $b->connect('/user/relogin', ['controller' => 'Users', 'action' => 'relogin']);
        $b->connect('/user/profil', ['controller' => 'Users', 'action' => 'profil']);
        $b->connect('/user/change-password', ['controller' => 'Users', 'action' => 'changePassword']);
        $b->connect('/user/save_page_tree', ['controller' => 'Users', 'action' => 'savePageTree']);
        $b->connect('/user/save', ['controller' => 'Users', 'action' => 'save']);
        $b->connect('/user/delete', ['controller' => 'Users', 'action' => 'deleteUser']);
        $b->connect('/user', ['controller' => 'Users', 'action' => 'index']);
        $b->connect('/user/{action}', ['controller' => 'Users']);

        $b->fallbacks();
    });
};
