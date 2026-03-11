<?php
/**
 * Pages show element (AJAX fragment)
 * Rendered by PagesController::show() via viewBuilder()->render()
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Page $page
 * @var string $pageContent (sanitized HTML)
 * @var array $auth
 * @var array $public
 */
$id = $page->id;
$title = h($page->title ?: __('[New page]'));
$status = $page->status;
$created = $page->created ? $page->created->format('d.m.Y H:i') : '';
$modified = $page->modified ? $page->modified->format('d.m.Y H:i') : '';
$createdBy = h($page->creator->fullname ?? '');
$modifiedBy = h($page->modifier->fullname ?? '');
$isAuth = !empty($auth['id']);
$dir = ($public['textDirection'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
$baseUri = $public['baseUri'] ?? '/';
$pageUrl = $baseUri . 'pages/' . $id . '/' . urlencode($page->title ?? '');
?>
<form id="print_page" action="/pages/<?= $id ?>/print/<?= urlencode($page->title ?? '') ?>" method="get"
    target="_blank"></form>
<div id="modal_link" style="display:none" title="<?= __('Copy link') ?>">
    <p><input id="modal_link_input" style="width:100%;padding:0.5em" type="text" value=""></p>
</div>

<script>
jQuery(document).ready(function(){
    jQuery('#modal_link').dialog({autoOpen:false, modal:true, closeOnEscape:true, resizable:false, width:600}).show();
    load_dialog('<?= $pageUrl ?>');
    <?php if ($dir === 'rtl'): ?>add_text_direction('rtl');<?php endif; ?>
    try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {}
    resize_page();
    resize_page_view();
});
function load_dialog(url) {
    jQuery('#modal_link').dialog({open: function(){ jQuery('#modal_link_input').val(url); }});
}
</script>

<div id="content_actions" class="row border-bottom bg-light justify-content-between">
    <div class="col-auto py-2">
        <?php if ($isAuth): ?>
            <span onclick="post_page_edit(<?= $id ?>)" title="<?= __('Edit page') ?>" class="fas fa-edit border
                border-dark p-2 m-2"></span>
        <?php elseif ($public['showLoginButton'] ?? true): ?>
            <span onclick="window.location.href='/user/login?page_id=<?= $id ?>'" title="<?= __('Login') ?>"
                class="fas fa-sign-in-alt border border-dark p-2 m-2"></span>
        <?php endif; ?>
        <?php if ($public['showLinkButton'] ?? true): ?>
            <span onclick="jQuery('#modal_link').dialog('open')" title="<?= __('Copy link') ?>" class="fas fa-link
                border border-dark p-2 m-2"></span>
        <?php endif; ?>
        <?php if ($public['enablePrint'] ?? false): ?>
            <span onclick="jQuery('#print_page').submit()" title="<?= __('Print page') ?>" class="fas fa-print border
                border-dark p-2 m-2"></span>
        <?php endif; ?>
    </div>
    <?php if ($isAuth || ($public['showAuthorDetails'] ?? true)): ?>
    <div id="page_info" class="col-auto px-0">
        <div class="container py-2"><div class="row">
            <div class="col-auto">
                <span><?= __('Created') ?>:</span><br/>
                <span><?= __('Modified') ?>:</span>
            </div>
            <div class="col-auto">
                <span><?= $created ?> | <?= $createdBy ?></span><br/>
                <span><?= $modified ?> | <?= $modifiedBy ?></span>
            </div>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<div id="content_pane" class="p-2 pe-4 h-100">
    <h3 id="page_title"<?= $status === 'inactive' ? ' class="inactive"' : '' ?>><?= $title ?></h3>
    <?php if ($status === 'active' || $isAuth): ?>
        <?= $pageContent ?>
    <?php else: ?>
        <p style="color:red"><?= __('This page is not published.') ?></p>
    <?php endif; ?>
</div>
