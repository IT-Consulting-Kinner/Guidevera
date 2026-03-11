<?php
/**
 * @var array $pages
 * @var array $auth
 * @var array $public
 */
$textDir = $public['textDirection'] ?? 'ltr';
$showAuthor = $public['showAuthorDetails'] ?? true;
?>
<!DOCTYPE html>
<html lang="<?= $public['appLanguage'] ?? 'en' ?>" dir="<?= $textDir ?>">
<head>
    <title><?= h($public['appName'] ?? 'Manual') ?></title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        @media print { .pagebreak { page-break-before: always; } }
        body { font-family: "Times New Roman", Times, serif; }
    </style>
</head>
<body>
<div id="app" style="width:19cm;margin:0 auto;">
    <?php $first = true; $toc = []; ?>
    <?php foreach ($pages as $p):
        $title = is_object($p) ? ($p->title ?? '') : ($p['title'] ?? '');
        $content = is_object($p) ? ($p->content ?? '') : ($p['content'] ?? '');
        $pid = (is_object($p) ? ($p->parent_id ?? 0) : ($p['parent_id'] ?? 0));
        $level = 0; $tmp = $pid; // Simple level calc
        foreach ($pages as $pp) { if ((is_object($pp) ? $pp->id : $pp['id']) == $pid) { $level = 1; break; } }
        $toc[] = ['title' => $title, 'level' => $level];
    ?>
        <?php if ($first): ?>
            <h1><button onclick="window.print()">Print</button><br/><?= h($title) ?></h1>
            <?php if ($showAuthor): ?><h4><?= __('Created') ?>: <?= date('d.m.Y') ?><br/><?= h($auth['fullname'] ??
                '') ?></h4><?php endif; ?>
            <div class="pagebreak"> </div>
            <?php if (!empty($toc)): ?>
                <h3><?= __('Table of Contents') ?></h3>
                <p>
                <?php foreach ($pages as $tp):
                    $tt = is_object($tp) ? ($tp->title ?? '') : ($tp['title'] ?? '');
                    $tpid = is_object($tp) ? ($tp->parent_id ?? 0) : ($tp['parent_id'] ?? 0);
                    $tlv = $tpid ? 1 : 0;
                ?>
                    <span style="margin:0 <?= $tlv * 2 ?>em"><?= h($tt) ?></span><br/>
                <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <?php $first = false; ?>
        <?php else: ?>
            <h3><?= h($title) ?></h3>
        <?php endif; ?>
        <?php if (!empty(strip_tags($content))): ?>
            <div><?= $content ?></div>
            <div class="pagebreak"> </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
</body>
</html>
