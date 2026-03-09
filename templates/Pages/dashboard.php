<?php
/** @var array $auth @var array $recentlyEdited @var array $recentlyCreated */
/** @var int $trashCount @var int $totalPages @var int $pendingFeedback */
/** @var int $stalePages @var int $noDescription @var int $reviewQueue @var array $searchMisses */
$isAdmin = ($auth['role'] ?? '') === 'admin';
$isContributor = in_array($auth['role'] ?? '', ['admin', 'contributor']);
?>
<div style="max-width:960px;margin:2rem auto;padding:0 1rem">
    <h2 style="margin-bottom:1.5rem"><?= __('Dashboard') ?></h2>

    <!-- Stats Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.75rem;margin-bottom:2rem">
        <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:1.75rem;font-weight:700;color:var(--brand-primary)"><?= $totalPages ?></div>
            <div style="font-size:0.75rem;color:var(--text-secondary)"><?= __('Pages') ?></div>
        </div>
        <?php if ($reviewQueue > 0 && $isContributor): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:1.75rem;font-weight:700;color:#856404"><?= $reviewQueue ?></div>
            <div style="font-size:0.75rem;color:#856404"><?= __('Awaiting Review') ?></div>
        </div>
        <?php endif; ?>
        <?php if ($stalePages > 0): ?>
        <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:1.75rem;font-weight:700;color:var(--color-error-text)"><?= $stalePages ?></div>
            <div style="font-size:0.75rem;color:var(--text-secondary)"><?= __('Stale (12+ months)') ?></div>
        </div>
        <?php endif; ?>
        <?php if ($noDescription > 0): ?>
        <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:1.75rem;font-weight:700;color:var(--text-muted)"><?= $noDescription ?></div>
            <div style="font-size:0.75rem;color:var(--text-secondary)"><?= __('No Description') ?></div>
        </div>
        <?php endif; ?>
        <div style="background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:1.75rem;font-weight:700;color:var(--text-muted)"><?= $trashCount ?></div>
            <div style="font-size:0.75rem;color:var(--text-secondary)"><?= __('In Trash') ?></div>
        </div>
        <?php if ($isAdmin && $pendingFeedback > 0): ?>
        <div style="background:var(--color-error-bg);border:1px solid var(--border-color);border-radius:var(--radius);padding:1rem;text-align:center">
            <div style="font-size:1.75rem;font-weight:700;color:var(--color-error-text)"><?= $pendingFeedback ?></div>
            <div style="font-size:0.75rem;color:var(--text-secondary)"><?= __('Pending Feedback') ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
        <div>
            <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-edit" style="margin-right:0.3rem"></span><?= __('Recently Edited by You') ?></h3>
            <?php if (empty($recentlyEdited)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem"><?= __('No pages edited yet.') ?></p>
            <?php else: foreach ($recentlyEdited as $p): ?>
                <div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
                    <a href="/pages/<?= $p->id ?>" style="color:var(--text-link);text-decoration:none;font-size:0.85rem"><?= h($p->title ?: __('[Untitled]')) ?></a>
                    <span style="color:var(--text-muted);font-size:0.7rem;white-space:nowrap"><?= $p->modified->format('d.m.') ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <div>
            <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-plus-circle" style="margin-right:0.3rem"></span><?= __('Recently Created') ?></h3>
            <?php foreach ($recentlyCreated as $p): ?>
                <div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
                    <a href="/pages/<?= $p->id ?>" style="color:var(--text-link);text-decoration:none;font-size:0.85rem"><?= h($p->title ?: __('[Untitled]')) ?></a>
                    <span style="color:var(--text-muted);font-size:0.7rem;white-space:nowrap"><?= $p->created->format('d.m.') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($isAdmin && !empty($searchMisses)): ?>
    <!-- Search Terms Without Results -->
    <div style="margin-top:2rem">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-search" style="margin-right:0.3rem"></span><?= __('Search Terms Without Results') ?></h3>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem">
            <?php foreach ($searchMisses as $m): ?>
                <span style="padding:0.2rem 0.6rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--text-secondary)"><?= h($m->details) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($myDrafts)): ?>
    <div style="margin-top:1.5rem">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-file-alt" style="margin-right:0.3rem"></span><?= __('My Drafts') ?></h3>
        <?php foreach ($myDrafts as $p): ?>
            <div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
                <a href="/pages/<?= $p->id ?>" style="color:var(--text-link);text-decoration:none;font-size:0.85rem"><?= h($p->title ?: __('[Untitled]')) ?></a>
                <span style="padding:0.1rem 0.4rem;background:#fff3cd;border-radius:8px;font-size:0.65rem;color:#856404"><?= __('Draft') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($myReviews)): ?>
    <div style="margin-top:1.5rem">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-clipboard-check" style="margin-right:0.3rem"></span><?= __('My Pending Reviews') ?></h3>
        <?php foreach ($myReviews as $r): ?>
            <div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
                <a href="/pages/<?= $r->page->id ?? 0 ?>" style="color:var(--text-link);text-decoration:none;font-size:0.85rem"><?= h($r->page->title ?? __('[Unknown]')) ?></a>
                <span style="color:var(--text-muted);font-size:0.7rem"><?= $r->created->format('d.m.') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:2rem;text-align:center">
        <a href="/pages" style="padding:0.4rem 1.5rem;background:var(--brand-primary);color:#fff;border-radius:var(--radius-sm);text-decoration:none;font-size:0.9rem"><?= __('Go to Pages') ?></a>
    </div>
</div>
