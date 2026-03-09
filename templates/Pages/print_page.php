<?php
/**
 * @var \App\Model\Entity\Page|null $page
 * @var array $nav
 * @var string|null $error
 * @var array $public
 */
$textDir = $public['textDirection'] ?? 'ltr';
$showAuthor = $public['showAuthorDetails'] ?? true;
?>
<!DOCTYPE html>
<html lang="<?= $public['appLanguage'] ?? 'en' ?>" dir="<?= $textDir ?>">
<head>
    <title><?= h($page->title ?? '') ?></title>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link rel="stylesheet" href="/css/app.css">
    <script src="/js/jquery-3.5.1.js"></script>
    <style>
        body { background-color: #f5f5f5; margin: 0; overflow: scroll; }
        #app { font-size: 14pt; font-weight: normal; font-family: "Times New Roman", Times, serif; color: #222; margin: 0 auto; padding: 0; box-sizing: border-box; background-color: #fff; width: 19cm; }
        @media only screen and (max-width: 768px) { #app { width: 100%; } }
        #content_wrapper { margin: 0; }
        #boxes { margin: 0 auto; display: table; width: 100% }
        #box_previous { float: <?= $textDir === 'rtl' ? 'right' : 'left' ?>; }
        #box_next { float: <?= $textDir === 'rtl' ? 'left' : 'right' ?>; }
        #box_next div, #box_previous div { height: 3em; vertical-align: bottom; }
        #box_next a, #box_previous a { font-size: 2em; color: #222; text-decoration: none; }
        #page_header { position: sticky; background-color: #f7f7f7; top: 0; z-index: 5; text-align: center; border-bottom: 1px solid #ccc; height: 4em; }
        #page_title { padding-top: 1em; }
        @media print { #boxes { display: none; } }
    </style>
</head>
<body>
<div id="app">
    <?php if (isset($error)): ?>
        <span style="line-height:1.5em;text-align:center;display:block"><?= h($error) ?></span>
    <?php else: ?>
        <div id="page_header">
            <div id="boxes">
                <div id="box_previous" style="display:table">
                    <div style="display:table-cell"><?php if (!empty($nav['firstId'])): ?><a title="<?= h($nav['firstTitle'] ?: 'Untitled') ?>" href="/pages/<?= $nav['firstId'] ?>/print/<?= urlencode($nav['firstTitle'] ?? '') ?>">|&laquo;</a><?php endif; ?></div>
                    <div style="display:table-cell"><?php if (!empty($nav['previousId'])): ?><a title="<?= h($nav['previousTitle'] ?: 'Untitled') ?>" href="/pages/<?= $nav['previousId'] ?>/print/<?= urlencode($nav['previousTitle'] ?? '') ?>">&laquo;</a><?php endif; ?></div>
                </div>
                <div id="box_next" style="display:table">
                    <div style="display:table-cell"><?php if (!empty($nav['nextId'])): ?><a title="<?= h($nav['nextTitle'] ?: 'Untitled') ?>" href="/pages/<?= $nav['nextId'] ?>/print/<?= urlencode($nav['nextTitle'] ?? '') ?>">&raquo;</a><?php endif; ?></div>
                    <div style="display:table-cell"><?php if (!empty($nav['lastId'])): ?><a title="<?= h($nav['lastTitle'] ?: 'Untitled') ?>" href="/pages/<?= $nav['lastId'] ?>/print/<?= urlencode($nav['lastTitle'] ?? '') ?>">&raquo;|</a><?php endif; ?></div>
                </div>
            </div>
        </div>
        <div>
            <div id="content_wrapper" class="print_size">
                <h3 id="page_title" class="<?= ($page->status ?? '') === 'inactive' ? 'inactive' : '' ?>"><?= h($page->title ?: 'Untitled') ?></h3>
                <?= $page->content ?? '' ?>
            </div>
            <?php if ($showAuthor): ?>
                <span style="line-height:1.5em;display:block;padding:0 1em;border-top:1px solid #ccc;"><?= __('Last update') ?>: <?= $page->modified ? $page->modified->format('d.m.Y H:i') : '' ?> | <?= h($page->modifier->fullname ?? '') ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
