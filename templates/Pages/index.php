<?php
/**
 * Pages main view
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Page|null $page
 * @var array $allPages
 * @var array $numberedPages
 * @var array $nav
 * @var int|null $id
 * @var string $ssrNavHtml
 * @var string $ssrTree
 * @var array $auth
 * @var array $public
 */
$isAuth = !empty($auth['id']);
$textDir = $public['textDirection'] ?? 'ltr';
$showNavIcons = ($public['showNavigationIcons'] ?? true) || $isAuth;
$showNavRoot = ($public['showNavigationRoot'] ?? true) || $isAuth;
$showTopNav = ($public['showTopNavigation'] ?? true) || $isAuth;
?>
<?php $this->Html->script(['jquery.mjs.nestedSortable', 'jquery.ui-contextmenu.min'], ['block' => true]); ?>

<?= $this->element('pages/sidebar', compact('isAuth', 'showTopNav', 'showNavIcons', 'showNavRoot', 'ssrNavHtml')) ?>

<div class="app-content" id="page">
<?php if ($page): ?>
    <?= $this->element('pages/show', [
        'page' => $page,
        'pageContent' => \App\Service\PagesService::sanitizeHtml($page->content ?? ''),
        'auth' => $auth,
        'public' => $public,
        'breadcrumbs' => $breadcrumbs ?? [],
        'nav' => $nav ?? [],
        'feedbackSummary' => $feedbackSummary ?? null,
        'pageTags' => $pageTags ?? [],
        'relatedPages' => $relatedPages ?? [],
    ]) ?>
<?php elseif (!$isAuth && ($public['showLoginButton'] ?? true)): ?>
    <div style="display:flex;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:1rem">
        <p style="color:var(--text-muted)"><?= __('Please log in to continue.') ?></p>
        <a href="/user/login" class="btn btn-primary"><span class="fas fa-sign-in-alt"></span> <?= __('Login') ?></a>
    </div>
<?php endif; ?>
</div>
<?php $nonce = $this->request->getAttribute('cspNonce') ?? ''; ?>
<script nonce="<?= $nonce ?>">
window.pageConfig = {
    hasSsrPage: <?= $page ? 'true' : 'false' ?>,
    hasSsrNav: <?= !empty($ssrNavHtml) ? 'true' : 'false' ?>,
    ssrTree: <?= $ssrTree ?? 'null' ?>,
    pageTree: <?= json_encode(json_decode(($auth['page_tree'] ?? '') ?: '{"open":"","active_page":"' . ($id ?? 0) . '"}'), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    showNavIcons: <?= $showNavIcons ? 'true' : 'false' ?>,
    showNavRoot: <?= $showNavRoot ? 'true' : 'false' ?>,
    currentId: <?= $id ?? 0 ?>,
    pageStatus: <?= json_encode($page->status ?? 'inactive', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    pageUrl: <?= json_encode(($public['baseUri'] ?? '/') . 'pages/' . ($id ?? 0) . '/' . urlencode($page->title ?? ''), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    textDir: <?= json_encode($textDir, JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    isAuth: <?= $isAuth ? 'true' : 'false' ?>,
    userRole: <?= json_encode($auth['role'] ?? 'guest', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    baseUri: <?= json_encode($public['baseUri'] ?? '/', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    editorLang: <?= json_encode($public['editorLanguage'] ?? 'en-US', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    defaultLocale: <?= json_encode($public['defaultLocale'] ?? 'en', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    currentLocale: <?= json_encode($locale ?? $public['defaultLocale'] ?? 'en', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    publicConfig: <?= json_encode([
        'showLinkButton' => $public['showLinkButton'] ?? true,
        'showLoginButton' => $public['showLoginButton'] ?? true,
        'showAuthorDetails' => $public['showAuthorDetails'] ?? true,
        'enablePrint' => $public['enablePrint'] ?? true,
        'enableFeedback' => $public['enableFeedback'] ?? true,
        'enableRevisions' => $public['enableRevisions'] ?? true,
        'enableTranslations' => $public['enableTranslations'] ?? false,
        'enableBreadcrumbs' => $public['enableBreadcrumbs'] ?? true,
        'enablePrevNext' => $public['enablePrevNext'] ?? true,
        'enableComments' => $public['enableComments'] ?? false,
        'enableMentions' => $public['enableMentions'] ?? false,
        'enableWorkflow' => $public['enableReviewProcess'] ?? false,
        'enableMarkdownExport' => $public['enableMarkdownExport'] ?? false,
        'enablePdfExport' => $public['enablePdfExport'] ?? false,
        'enableSubscriptions' => $public['enableSubscriptions'] ?? false,
        'enableAcknowledgements' => $public['enableAcknowledgements'] ?? false,
        'enableInlineComments' => $public['enableInlineComments'] ?? false,
        'enableMediaLibrary' => $public['enableMediaLibrary'] ?? false,
        'enableSmartLinks' => $public['enableSmartLinks'] ?? false,
        'enableScheduledPublishing' => $public['enableScheduledPublishing'] ?? false,
        'enableImport' => $public['enableImport'] ?? false,
        'contentLocales' => $public['contentLocales'] ?? ['en'],
    ], JSON_HEX_TAG | JSON_HEX_AMP) ?>
};
</script>
<script src="/js/pages<?= \Cake\Core\Configure::read('debug') ? '' : '.min' ?>.js?v=<?= filemtime(WWW_ROOT . 'js/pages.js') ?>" nonce="<?= $nonce ?>"></script>
