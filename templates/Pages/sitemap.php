<?php /** @var \App\Model\Entity\Page[] $pages */ ?>
<?php $this->disableAutoLayout(); header('Content-Type: application/xml'); ?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
    <url>
        <loc>/pages/<?= $page->id ?>/<?= urlencode($page->title) ?></loc>
        <lastmod><?= $page->modified ? $page->modified->format('Y-m-d') : date('Y-m-d') ?></lastmod>
    </url>
<?php endforeach; ?>
</urlset>
