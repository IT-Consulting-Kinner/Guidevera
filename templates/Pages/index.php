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
        'pageContent' => $page->content ?? '',
        'auth' => $auth,
        'public' => $public,
    ]) ?>
<?php endif; ?>
</div>
<?php $nonce = $this->request->getAttribute('cspNonce') ?? ''; ?>
<script nonce="<?= $nonce ?>">
window.pageConfig = {
    hasSsrPage: <?= $page ? 'true' : 'false' ?>,
    hasSsrNav: <?= !empty($ssrNavHtml) ? 'true' : 'false' ?>,
    ssrTree: <?= $ssrTree ?? 'null' ?>,
    pageTree: JSON.parse('<?= addslashes($auth['page_tree'] ?? '{"open":"","active_page":"' . ($id ?? 0) . '"}') ?>'),
    showNavIcons: <?= $showNavIcons ? 'true' : 'false' ?>,
    showNavRoot: <?= $showNavRoot ? 'true' : 'false' ?>,
    currentId: <?= $id ?? 0 ?>,
    pageUrl: '<?= ($public['baseUri'] ?? '/') ?>pages/<?= $id ?? 0 ?>/<?= urlencode($page->title ?? '') ?>',
    textDir: '<?= $textDir ?>',
    isAuth: <?= $isAuth ? 'true' : 'false' ?>,
    baseUri: '<?= $public['baseUri'] ?? '/' ?>',
    editorLang: '<?= $public['editorLanguage'] ?? 'en-US' ?>',
    publicConfig: <?= json_encode([
        'showLinkButton' => $public['showLinkButton'] ?? true,
        'showLoginButton' => $public['showLoginButton'] ?? false,
        'showAuthorDetails' => $public['showAuthorDetails'] ?? true,
        'enablePrint' => $public['enablePrint'] ?? false,
        'enableFeedback' => $public['enableFeedback'] ?? false,
        'enableRevisions' => $public['enableRevisions'] ?? true,
        'enableTranslations' => $public['enableTranslations'] ?? false,
        'enableBreadcrumbs' => $public['enableBreadcrumbs'] ?? true,
        'enablePrevNext' => $public['enablePrevNext'] ?? true,
        'enableComments' => $public['enableComments'] ?? false,
        'enableMarkdownExport' => $public['enableMarkdownExport'] ?? false,
        'enablePdfExport' => $public['enablePdfExport'] ?? false,
        'contentLocales' => $public['contentLocales'] ?? ['en'],
    ]) ?>
};
</script>
<script nonce="<?= $nonce ?>" src="/js/pages<?= \Cake\Core\Configure::read('debug') ? '' : '.min' ?>.js"></script>
