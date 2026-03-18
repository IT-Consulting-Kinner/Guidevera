<?php
/** @var array $auth @var array $recentlyEdited @var array $recentlyCreated */
/** @var int $trashCount @var int $totalPages @var int $pendingFeedback */
/** @var int $stalePages @var int $noDescription @var int $reviewQueue @var array $searchMisses */
$isAdmin = ($auth['role'] ?? '') === 'admin';
$isContributor = in_array($auth['role'] ?? '', ['admin', 'contributor']);
?>
<div class="app-content" style="overflow-y:auto">
<div style="max-width:1200px;margin:2rem auto;padding:0 2rem">
    <h2 style="margin-bottom:1.5rem"><?= __('Dashboard') ?></h2>


    <!-- Main 3-Column Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem">

        <!-- Column 1: Recently Edited -->
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

        <!-- Column 2: Recently Created -->
        <div>
            <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-plus-circle" style="margin-right:0.3rem"></span><?= __('Recently Created') ?></h3>
            <?php if (empty($recentlyCreated)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem"><?= __('No pages created yet.') ?></p>
            <?php else: foreach ($recentlyCreated as $p): ?>
                <div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
                    <a href="/pages/<?= $p->id ?>" style="color:var(--text-link);text-decoration:none;font-size:0.85rem"><?= h($p->title ?: __('[Untitled]')) ?></a>
                    <span style="color:var(--text-muted);font-size:0.7rem;white-space:nowrap"><?= $p->created->format('d.m.') ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Column: My Drafts (only when Review Process enabled) -->
        <?php if ($enableReviewProcess ?? false): ?>
        <div>
            <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-file-alt" style="margin-right:0.3rem"></span><?= __('My Drafts') ?></h3>
            <?php if (empty($myDrafts)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem"><?= __('No drafts.') ?></p>
            <?php else: foreach ($myDrafts as $p): ?>
                <div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between">
                    <a href="/pages/<?= $p->id ?>" style="color:var(--text-link);text-decoration:none;font-size:0.85rem"><?= h($p->title ?: __('[Untitled]')) ?></a>
                    <span style="padding:0.1rem 0.4rem;background:var(--color-warning-bg);border-radius:8px;font-size:0.65rem;color:var(--color-warning-text)"><?= __('Draft') ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>

        <!-- Column: Trash -->
        <?php if ($trashCount > 0): ?>
        <div>
            <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-trash-alt" style="margin-right:0.3rem"></span><?= __('Trash') ?> (<span id="trashHeaderCount"><?= $trashCount ?></span>)</h3>
            <div id="trashList">
                <p style="color:var(--text-muted);font-size:0.85rem"><?= __('Loading...') ?></p>
            </div>
        </div>
        <?php endif; ?>

    </div>

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

    <?php if ($isAdmin && !empty($searchMisses)): ?>
    <div style="margin-top:1.5rem">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-search" style="margin-right:0.3rem"></span><?= __('Search Terms Without Results') ?></h3>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem">
            <?php foreach ($searchMisses as $m): ?>
                <span style="padding:0.2rem 0.6rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--text-secondary)"><?= h($m->details) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quality Report (Admin only, loaded via AJAX) -->
    <?php if ($isAdmin): ?>
    <div id="qualityReport" style="margin-top:2rem">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-heartbeat" style="margin-right:0.3rem"></span><?= __('Quality Report') ?></h3>
        <p style="color:var(--text-muted);font-size:0.85rem"><?= __('Loading...') ?></p>
    </div>
    <?php endif; ?>

    <!-- Page Overview Table -->
    <?php if (!empty($allOverview)): ?>
    <div style="margin-top:2rem">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem"><span class="fas fa-table" style="margin-right:0.3rem"></span><?= __('Page Overview') ?></h3>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:0.8rem">
            <thead>
                <tr style="background:var(--bg-hover);border-bottom:2px solid var(--border-color)">
                    <th style="padding:0.4rem 0.5rem;text-align:left;white-space:nowrap"><?= __('ID') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:left"><?= __('Title') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap"><?= __('Status') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap"><?= __('Created') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:left;white-space:nowrap"><?= __('Created by') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap"><?= __('Edited') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:left;white-space:nowrap"><?= __('Edited by') ?></th>
                    <?php if ($overviewConfig['enableReviewProcess']): ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap"><?= __('Workflow') ?></th>
                    <?php endif; ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Description') ?>"><?= __('Desc') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Keywords') ?>"><?= __('Kw') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Tags') ?>"><?= __('Tags') ?></th>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Content') ?>"><?= __('Content') ?></th>
                    <?php if ($overviewConfig['enableTranslations'] && count($overviewConfig['contentLocales']) > 1): ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap"><?= __('Translations') ?></th>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableFeedback']): ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Feedback') ?>"><span class="fas fa-thumbs-up"></span>/<span class="fas fa-thumbs-down"></span></th>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableSubscriptions']): ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Subscriptions') ?>"><span class="fas fa-bell"></span></th>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableAcknowledgements']): ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Acknowledgements') ?>"><span class="fas fa-check-circle"></span></th>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableComments']): ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Comments') ?>"><span class="fas fa-comments"></span></th>
                    <?php endif; ?>
                    <th style="padding:0.4rem 0.5rem;text-align:center;white-space:nowrap" title="<?= __('Views') ?>"><span class="fas fa-eye"></span></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allOverview as $p):
                $pid = $p->id;
                $isRoot = ($pid === ($rootPageId ?? 0)) && !($overviewConfig['showNavigationRoot'] ?? true);
                $hasDesc = !empty(trim($p->description ?? ''));
                $hasKw = !empty($keywordsMap[$pid]);
                $hasTags = !empty($tagsMap[$pid]);
                $hasContent = !empty(trim(strip_tags($p->content ?? '')));
                $fb = $feedbackMap[$pid] ?? ['up' => 0, 'down' => 0];
                $subs = $subsMap[$pid] ?? 0;
                $acks = $ackMap[$pid] ?? 0;
                $cmts = $commentMap[$pid] ?? 0;
                $pageTranslations = $translationsMap[$pid] ?? [];
                $statusColor = $p->status === 'active' ? 'var(--color-success-text)' : 'var(--color-error-text)';
                $wfColors = ['draft' => 'var(--color-warning-text)', 'review' => 'var(--brand-primary)', 'published' => 'var(--color-success-text)', 'archived' => 'var(--text-muted)'];
                $wfColor = $wfColors[$p->workflow_status ?? 'draft'] ?? 'var(--text-muted)';
                $dash = '<span style="color:var(--text-muted)">—</span>';
                $check = '<span class="fas fa-check" style="color:var(--color-success-text)"></span>';
                $cross = '<span class="fas fa-times" style="color:var(--color-error-text)"></span>';
            ?>
                <tr style="border-bottom:1px solid var(--border-light)">
                    <td style="padding:0.35rem 0.5rem;color:var(--text-muted);white-space:nowrap"><?= $pid ?><?= ($pid === ($rootPageId ?? 0)) ? ' <span style="font-size:0.65rem;color:var(--text-secondary)">(root)</span>' : '' ?></td>
                    <td style="padding:0.35rem 0.5rem"><a href="/pages/<?= $pid ?>" style="color:var(--text-link);text-decoration:none"><?= h($p->title ?: __('[Untitled]')) ?></a></td>
                    <td style="padding:0.35rem 0.5rem;text-align:center"><span style="color:<?= $statusColor ?>;font-size:0.75rem"><?= h(ucfirst($p->status)) ?></span></td>
                    <td style="padding:0.35rem 0.5rem;text-align:center;white-space:nowrap;font-size:0.75rem;color:var(--text-muted)"><?= $p->created ? $p->created->format('d.m.Y H:i') : '—' ?></td>
                    <td style="padding:0.35rem 0.5rem;font-size:0.75rem;color:var(--text-secondary)"><?= h($p->creator->fullname ?? '—') ?></td>
                    <td style="padding:0.35rem 0.5rem;text-align:center;white-space:nowrap;font-size:0.75rem;color:var(--text-muted)"><?= $p->modified ? $p->modified->format('d.m.Y H:i') : '—' ?></td>
                    <td style="padding:0.35rem 0.5rem;font-size:0.75rem;color:var(--text-secondary)"><?= h($p->modifier->fullname ?? '—') ?></td>
                    <?php if ($overviewConfig['enableReviewProcess']): ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center"><span style="color:<?= $wfColor ?>;font-size:0.75rem"><?= h(ucfirst($p->workflow_status ?? 'draft')) ?></span></td>
                    <?php endif; ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center"><?= $isRoot ? $dash : ($hasDesc ? $check : $cross) ?></td>
                    <td style="padding:0.35rem 0.5rem;text-align:center"><?= $isRoot ? $dash : ($hasKw ? $check : $cross) ?></td>
                    <td style="padding:0.35rem 0.5rem;text-align:center"><?= $isRoot ? $dash : ($hasTags ? $check : $cross) ?></td>
                    <td style="padding:0.35rem 0.5rem;text-align:center"><?= $isRoot ? $dash : ($hasContent ? $check : $cross) ?></td>
                    <?php if ($overviewConfig['enableTranslations'] && count($overviewConfig['contentLocales']) > 1): ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center;white-space:nowrap">
                        <?php foreach ($overviewConfig['contentLocales'] as $loc):
                            $isDefault = ($loc === $overviewConfig['defaultLocale']);
                            $hasTranslation = $isDefault || in_array($loc, $pageTranslations);
                            $style = $hasTranslation ? 'color:var(--text-primary);font-weight:600' : 'color:var(--text-muted);opacity:0.4';
                        ?>
                            <span style="<?= $style ?>;font-size:0.7rem;margin:0 0.1rem" title="<?= h(strtoupper($loc)) ?>"><?= h(strtoupper($loc)) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableFeedback']): ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center;white-space:nowrap;font-size:0.75rem">
                        <?php if ($fb['up'] || $fb['down']): ?>
                            <span style="color:var(--color-success-text)"><?= $fb['up'] ?></span>/<span style="color:var(--color-error-text)"><?= $fb['down'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted)">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableSubscriptions']): ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center;font-size:0.75rem"><?= $subs ?: '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableAcknowledgements']): ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center;font-size:0.75rem"><?= $acks ?: '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <?php endif; ?>
                    <?php if ($overviewConfig['enableComments']): ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center;font-size:0.75rem"><?= $cmts ?: '<span style="color:var(--text-muted)">—</span>' ?></td>
                    <?php endif; ?>
                    <td style="padding:0.35rem 0.5rem;text-align:center;font-size:0.75rem;color:var(--text-muted)"><?= (int)($p->views ?? 0) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php $nonce = $this->request->getAttribute('cspNonce') ?? ''; ?>
<script nonce="<?= $nonce ?>">
jQuery(document).ready(function() {
    var csrfToken = <?= json_encode($this->request->getAttribute('csrfToken') ?? '', JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    jQuery.ajaxSetup({ beforeSend: function(xhr, s) { if (s.type && s.type.toUpperCase() === 'POST') xhr.setRequestHeader('X-CSRF-Token', csrfToken); } });

    function loadTrash() {
        jQuery.post('/pages/trash', {}, function(d) {
            var $list = jQuery('#trashList').empty();
            var count = (d.trash && d.trash.length) ? d.trash.length : 0;
            jQuery('#statTrashCount').text(count);
            jQuery('#trashHeaderCount').text(count);
            if (!count) {
                $list.html('<p style="color:var(--text-muted);font-size:0.85rem"><?= __('Trash is empty.') ?></p>');
                return;
            }
            d.trash.forEach(function(p) {
                var html = '<div style="padding:0.35rem 0;border-bottom:1px solid var(--border-light);display:flex;justify-content:space-between;align-items:center">' +
                    '<div style="flex:1;min-width:0"><span style="font-size:0.85rem;color:var(--text-primary)">' + jQuery('<span>').text(p.title || '[Untitled]').html() + '</span>' +
                    '<br><span style="font-size:0.7rem;color:var(--text-muted)">' + jQuery('<span>').text(p.deletedAt).html() + '</span></div>' +
                    '<div style="display:flex;gap:0.3rem;flex-shrink:0">' +
                    '<button class="btn-trash-restore btn btn-sm btn-outline-success" data-id="' + p.id + '" title="<?= __('Restore') ?>"><span class="fas fa-undo-alt"></span></button>' +
                    '<button class="btn-trash-purge btn btn-sm btn-outline-danger" data-id="' + p.id + '" title="<?= __('Delete permanently') ?>"><span class="fas fa-times"></span></button>' +
                    '</div></div>';
                $list.append(html);
            });
        }, 'json');
    }

    function updateTotalPages(delta) {
        var el = jQuery('#statTotalPages');
        el.text(Math.max(0, parseInt(el.text() || 0) + delta));
    }

    if (<?= $trashCount ?> > 0) loadTrash();

    // ── Quality Report (Admin only) ──
    <?php if ($isAdmin): ?>
    (function loadQualityReport() {
        var $box = jQuery('#qualityReport');
        if (!$box.length) return;
        jQuery.post('/pages/quality', {}, function(d) {
            if (!d || !d.quality) {
                $box.find('p').text('<?= __('No data available. Run: bin/cake quality-check') ?>');
                return;
            }
            var q = d.quality, s = q.summary || {};
            var total = s.totalIssues || 0;
            var html = '<div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem">'
                + '<?= __('Last check') ?>: ' + jQuery('<span>').text(q.timestamp || '—').html()
                + ' · ' + (q.pageCount || 0) + ' <?= __('pages') ?></div>';

            // Summary badges
            html += '<div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1rem">';
            if (total === 0) {
                html += '<span style="padding:0.25rem 0.75rem;background:var(--color-success-bg);color:var(--color-success-text);border-radius:var(--radius-sm);font-size:0.8rem">'
                    + '<span class="fas fa-check" style="margin-right:0.3rem"></span><?= __('No issues found') ?></span>';
            } else {
                html += '<span style="padding:0.25rem 0.75rem;background:var(--color-error-bg);color:var(--color-error-text);border-radius:var(--radius-sm);font-size:0.8rem">'
                    + total + ' <?= __('issues') ?></span>';
                if (s.stale) html += '<span style="padding:0.25rem 0.75rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--color-error-text)">'
                    + s.stale + ' <?= __('stale') ?></span>';
                if (s.noDescription) html += '<span style="padding:0.25rem 0.75rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--text-muted)">'
                    + s.noDescription + ' <?= __('no description') ?></span>';
                if (s.emptyContent) html += '<span style="padding:0.25rem 0.75rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--color-warning-text)">'
                    + s.emptyContent + ' <?= __('empty') ?></span>';
                if (s.noKeywords) html += '<span style="padding:0.25rem 0.75rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--text-muted)">'
                    + s.noKeywords + ' <?= __('no keywords') ?></span>';
                if (s.noTags) html += '<span style="padding:0.25rem 0.75rem;background:var(--bg-hover);border-radius:var(--radius-sm);font-size:0.8rem;color:var(--text-muted)">'
                    + s.noTags + ' <?= __('no tags') ?></span>';
            }
            html += '</div>';

            // Issue list table
            if (q.issues && q.issues.length) {
                html += '<table style="width:100%;border-collapse:collapse;font-size:0.8rem">'
                    + '<thead><tr style="background:var(--bg-hover);border-bottom:2px solid var(--border-color)">'
                    + '<th style="padding:0.4rem 0.5rem;text-align:left"><?= __('Page') ?></th>'
                    + '<th style="padding:0.4rem 0.5rem;text-align:left"><?= __('Issue') ?></th>'
                    + '</tr></thead><tbody>';
                q.issues.forEach(function(i) {
                    html += '<tr style="border-bottom:1px solid var(--border-light)">'
                        + '<td style="padding:0.35rem 0.5rem"><a href="/pages/' + i.id + '" style="color:var(--text-link);text-decoration:none">'
                        + jQuery('<span>').text(i.title || '<?= __('[Untitled]') ?>').html() + '</a></td>'
                        + '<td style="padding:0.35rem 0.5rem;color:var(--color-error-text)">'
                        + jQuery('<span>').text(i.type || '').html() + '</td></tr>';
                });
                html += '</tbody></table>';
            }

            // Orphaned pages
            if (q.orphaned && q.orphaned.length) {
                html += '<div style="margin-top:0.75rem;font-size:0.8rem;color:var(--color-warning-text)">'
                    + '<span class="fas fa-unlink" style="margin-right:0.3rem"></span><?= __('Orphaned pages') ?>: ';
                q.orphaned.forEach(function(o, idx) {
                    if (idx > 0) html += ', ';
                    html += '<a href="/pages/' + o.id + '" style="color:var(--text-link);text-decoration:none">'
                        + jQuery('<span>').text(o.title || '#' + o.id).html() + '</a>';
                });
                html += '</div>';
            }

            $box.html('<h3 style="font-size:0.95rem;margin-bottom:0.75rem">'
                + '<span class="fas fa-heartbeat" style="margin-right:0.3rem"></span><?= __('Quality Report') ?></h3>' + html);
        }, 'json').fail(function() {
            $box.find('p').text('<?= __('Could not load quality report.') ?>');
        });
    })();
    <?php endif; ?>

    jQuery(document).on('click', '.btn-trash-restore', function() {
        var id = jQuery(this).data('id');
        jQuery.post('/pages/trash_restore', { id: id }, function(d) {
            if (d && !d.error) { window.location.reload(); }
            else alert(d ? d.error : 'Failed');
        }, 'json');
    });

    jQuery(document).on('click', '.btn-trash-purge', function() {
        var id = jQuery(this).data('id');
        if (!confirm('<?= __('Permanently delete this page and all its data? This cannot be undone.') ?>')) return;
        jQuery.post('/pages/trash_purge', { id: id }, function(d) {
            if (d && !d.error) loadTrash();
            else alert(d ? d.error : 'Failed');
        }, 'json');
    });
});
</script>
