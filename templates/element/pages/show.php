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

<?php $nonce = $this->request->getAttribute('cspNonce') ?? ''; ?>
<script nonce="<?= $nonce ?>">
jQuery(document).ready(function(){
    jQuery('#modal_link').dialog({autoOpen:false, modal:true, closeOnEscape:true, resizable:false, width:600}).show();
    load_dialog(<?= json_encode($pageUrl, JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    <?php if ($dir === 'rtl'): ?>add_text_direction('rtl');<?php endif; ?>
    try { jQuery('[title]').not('[title=""]').not('.ui-dialog-titlebar *').tooltip(); } catch(e) {}
    resize_page_view();
});
function load_dialog(url) {
    jQuery('#modal_link').dialog({open: function(){ jQuery('#modal_link_input').val(url); }});
}
</script>

<div id="content_actions" style="background:var(--bg-toolbar)">
    <div class="col-auto">
        <?php if ($isAuth): ?>
            <span data-action="editPage" data-arg="<?= $id ?>" title="<?= __('Edit page') ?>" class="fas fa-edit border
                p-2 m-2" style="cursor:pointer;border-color:var(--border-color)!important"></span>
        <?php endif; ?>
        <?php if ($isAuth && in_array($auth['role'] ?? '', ['admin', 'contributor'])): ?>
            <span data-action="confirmDelete" data-arg="<?= $id ?>" data-arg2="<?= $page->parent_id ?? 0 ?>" title="<?= __('Delete') ?>" class="far fa-trash-alt border
                p-2 m-2" style="cursor:pointer;border-color:var(--border-color)!important"></span>
            <span data-action="pageStatus" data-arg="inactive" title="<?= __('Unpublish') ?>" class="page_active far fa-eye border p-2 m-2" style="<?= $status === 'active' ? '' : 'display:none;' ?>cursor:pointer;border-color:var(--border-color)!important"></span>
            <span data-action="pageStatus" data-arg="active" title="<?= __('Publish') ?>" class="page_inactive far fa-eye-slash border p-2 m-2" style="<?= $status !== 'active' ? '' : 'display:none;' ?>cursor:pointer;border-color:var(--border-color)!important"></span>
        <?php endif; ?>
        <?php if (!$isAuth && ($public['showLinkButton'] ?? true)): ?>
            <span data-action="openLinkDialog" title="<?= __('Copy link') ?>" class="fas fa-link
                border p-2 m-2" style="cursor:pointer;border-color:var(--border-color)!important"></span>
        <?php endif; ?>
        <?php if (($public['enablePrint'] ?? false) && ($status ?? '') === 'active'): ?>
            <span data-action="printPage" title="<?= __('Print page') ?>" class="fas fa-print border
                p-2 m-2" style="cursor:pointer;border-color:var(--border-color)!important"></span>
        <?php endif; ?>
    </div>
    <?php if ($isAuth || ($public['showAuthorDetails'] ?? true)): ?>
    <div id="page_info" style="padding:0.4rem 0.75rem;font-size:0.85rem">
        <table style="border-collapse:collapse">
        <tr><td style="padding:0 0.75rem 0 0;white-space:nowrap"><?= __('Created') ?>:</td><td style="padding:0 0.75rem 0 0;white-space:nowrap"><?= $created ?></td><td><?= $createdBy ?></td></tr>
        <tr><td style="padding:0 0.75rem 0 0;white-space:nowrap"><?= __('Modified') ?>:</td><td style="padding:0 0.75rem 0 0;white-space:nowrap"><?= $modified ?></td><td><?= $modifiedBy ?></td></tr>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($breadcrumbs) && count($breadcrumbs) > 1 && ($public['enableBreadcrumbs'] ?? true)): ?>
<div class="breadcrumbs" style="padding:0.5rem 1rem;font-size:0.8rem;color:var(--text-secondary);border-bottom:1px solid var(--border-light)">
    <?php foreach ($breadcrumbs as $i => $bc): ?>
        <?php if ($i < count($breadcrumbs) - 1): ?>
            <a href="/pages/<?= $bc['id'] ?>/<?= urlencode($bc['title']) ?>" style="color:var(--text-secondary);text-decoration:none"><?= h($bc['title']) ?></a> <span style="margin:0 0.3rem">/</span>
        <?php else: ?>
            <span style="color:var(--text-primary)"><?= h($bc['title']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div id="content_pane" class="h-100">
    <h3 id="page_title"<?= $status === 'inactive' ? ' class="inactive"' : '' ?>><?= $title ?></h3>
    <?php if ($status === 'active' || $isAuth): ?>
        <?= $pageContent ?>
    <?php else: ?>
        <p style="color:var(--color-error-text)"><?= __('This page is not published.') ?></p>
    <?php endif; ?>

    <?php
    $feedbackSummary = $feedbackSummary ?? null;
    $nav = $nav ?? [];
    ?>

    <?php if (!empty($nav) && ($public['enablePrevNext'] ?? true)): ?>
    <div style="display:flex;justify-content:space-between;margin-top:1.5rem;padding-top:0.75rem;border-top:1px solid var(--border-light)">
        <?php if (!empty($nav['previousId'])): ?>
            <a href="/pages/<?= $nav['previousId'] ?>/<?= urlencode($nav['previousTitle'] ?? '') ?>" data-action="post_page_show" data-arg="<?= $nav['previousId'] ?>" style="color:var(--text-link);text-decoration:none;font-size:0.9rem"><span class="fas fa-arrow-left"></span> <?= h($nav['previousTitle'] ?? '') ?></a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
        <?php if (!empty($nav['nextId'])): ?>
            <a href="/pages/<?= $nav['nextId'] ?>/<?= urlencode($nav['nextTitle'] ?? '') ?>" data-action="post_page_show" data-arg="<?= $nav['nextId'] ?>" style="color:var(--text-link);text-decoration:none;font-size:0.9rem"><?= h($nav['nextTitle'] ?? '') ?> <span class="fas fa-arrow-right"></span></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($isAuth && ($public['enableComments'] ?? false)): ?>
    <div id="pageComments" style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">
        <h4 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:0.75rem"><span class="fas fa-comments"></span> <?= __('Internal Comments') ?></h4>
        <div id="commentsList" style="margin-bottom:0.75rem"></div>
        <div style="display:flex;gap:0.5rem">
            <input type="text" id="commentInput" placeholder="<?= __('Add a comment... Use @username to mention') ?>" style="flex:1;padding:0.4rem 0.75rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem" data-action-enter="addComment" data-arg="<?= $id ?>">
            <button data-action="addComment" data-arg="<?= $id ?>" style="padding:0.4rem 0.75rem;background:var(--brand-primary);color:#fff;border:none;border-radius:var(--radius-sm);font-size:0.85rem;cursor:pointer"><?= __('Send') ?></button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($feedbackSummary && $status === 'active' && ($public['enableFeedback'] ?? false)): ?>
    <div class="feedback-section" style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-color)">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem">
            <span style="font-size:0.85rem;color:var(--text-secondary)"><?= __('Was this page helpful?') ?></span>
            <span class="fas fa-thumbs-up" style="color:var(--text-muted)"></span>&nbsp;<?= (int)($feedbackSummary['up'] ?? 0) ?>
            &nbsp;<span class="fas fa-thumbs-down" style="color:var(--text-muted)"></span>&nbsp;<?= (int)($feedbackSummary['down'] ?? 0) ?>
        </div>
        <div style="margin-bottom:0.75rem">
            <button data-action="submitFeedback" data-arg="<?= $id ?>" data-arg2="1" class="toolbar-btn feedback-btn" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-thumbs-up"></span>&nbsp;<?= __('Yes') ?></button>
            <button data-action="submitFeedback" data-arg="<?= $id ?>" data-arg2="-1" class="toolbar-btn feedback-btn" style="width:auto;padding:0.25rem 0.75rem;font-size:0.8rem"><span class="fas fa-thumbs-down"></span>&nbsp;<?= __('No') ?></button>
        </div>
        <div id="feedbackForm" style="display:none;margin-bottom:1rem">
            <textarea id="feedbackComment" style="width:100%;padding:0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);font-size:0.85rem" rows="3" placeholder="<?= __('Optional comment...') ?>"></textarea><br>
            <button data-action="sendFeedback" data-arg="<?= $id ?>" class="toolbar-btn" style="width:auto;padding:0.25rem 0.75rem;margin-top:0.5rem;background:var(--brand-primary);color:#fff;border-radius:var(--radius-sm)"><?= __('Submit') ?></button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($relatedPages ?? [])): ?>
    <div id="relatedPages" style="margin-top:1.5rem;padding-top:0.75rem;border-top:1px solid var(--border-light)">
        <span style="font-size:0.8rem;font-weight:600;color:var(--text-secondary)"><?= __('Related pages') ?>:</span>
        <?php foreach (($relatedPages ?? []) as $i => $r): ?>
            <?php if ($i > 0): ?>, <?php endif; ?>
            <a href="/pages/<?= (int)$r['id'] ?>/<?= urlencode($r['title'] ?? '') ?>" data-action="post_page_show" data-arg="<?= (int)$r['id'] ?>" style="font-size:0.8rem;color:var(--text-link)"><?= h($r['title'] ?? '') ?></a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div id="relatedPages" style="margin-top:1.5rem"></div>
    <?php endif; ?>

    <?php if (!empty($pageTags ?? [])): ?>
    <div id="pageTags" style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.3rem;padding-top:0.5rem;border-top:1px solid var(--border-light)">
        <?php foreach (($pageTags ?? []) as $tag): ?>
            <span style="padding:0.15rem 0.5rem;background:var(--bg-hover);border-radius:10px;font-size:0.75rem;color:var(--text-secondary)"><?= h($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div id="pageTags" style="margin-top:1rem"></div>
    <?php endif; ?>
</div>
