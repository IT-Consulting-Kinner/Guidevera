<?php

declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Quality Check Command — content quality analysis.
 *
 * Checks: missing descriptions, missing keywords, missing tags (if enableSmartLinks),
 * stale content, broken internal links, orphaned media, heading structure issues.
 * Results can be viewed in the admin dashboard via /pages/stats.
 *
 * Usage: bin/cake quality-check
 */
class QualityCheckCommand extends Command
{
    public static function defaultName(): string
    {
        return 'quality-check';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Run content quality checks on all pages.');
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $io->out('<info>Content Quality Check</info>');
        $io->out(str_repeat('─', 50));

        $showRoot = \Cake\Core\Configure::read('Manual.showNavigationRoot') ?? true;
        $enableSmartLinks = \Cake\Core\Configure::read('Manual.enableSmartLinks') ?? false;
        $staleMonths = \Cake\Core\Configure::read('Manual.staleContentMonths') ?? 12;

        $allPages = $this->fetchTable('Pages')->find()
            ->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC'])->all()->toArray();

        // Determine root page ID
        $rootPageId = !empty($allPages) ? $allPages[0]->id : null;

        // Pre-load keywords for all pages in bulk
        $pageIds = array_map(fn($p) => $p->id, $allPages);
        $pagesWithKeywords = [];
        if (!empty($pageIds)) {
            $kwRows = $this->fetchTable('Pagesindex')->find()
                ->select(['page_id'])->where(['page_id IN' => $pageIds])->all();
            foreach ($kwRows as $r) {
                $pagesWithKeywords[$r->page_id] = true;
            }
        }

        // Pre-load tags for all pages in bulk (only if enableSmartLinks)
        $pagesWithTags = [];
        if ($enableSmartLinks && !empty($pageIds)) {
            $tagRows = $this->fetchTable('PageTags')->find()
                ->select(['page_id'])->where(['page_id IN' => $pageIds])->all();
            foreach ($tagRows as $r) {
                $pagesWithTags[$r->page_id] = true;
            }
        }

        $issues = [];
        $pageCount = 0;

        foreach ($allPages as $page) {
            // Skip root page if showNavigationRoot is false
            if (!$showRoot && $rootPageId !== null && $page->id === $rootPageId) {
                continue;
            }

            $pageCount++;
            $pageIssues = [];

            // 1. Missing description
            if (empty(trim($page->description ?? ''))) {
                $pageIssues[] = 'No description';
            }

            // 2. Stale content
            if ($page->modified && $page->modified->wasWithinLast($staleMonths . ' months') === false) {
                $months = (int)$page->modified->diffInMonths(new \Cake\I18n\DateTime());
                $pageIssues[] = "Not updated in {$months} months";
            }

            // 3. Empty content
            $plainContent = strip_tags($page->content ?? '');
            if (strlen(trim($plainContent)) < 10) {
                $pageIssues[] = 'Content is empty or very short';
            }

            // 4. Missing keywords
            if (empty($pagesWithKeywords[$page->id])) {
                $pageIssues[] = 'No keywords';
            }

            // 5. Missing tags (only if enableSmartLinks)
            if ($enableSmartLinks && empty($pagesWithTags[$page->id])) {
                $pageIssues[] = 'No tags';
            }

            // 6. Heading structure (h1 in content, skipped levels)
            if (!empty($page->content)) {
                preg_match_all('/<h([1-6])/i', $page->content, $headings);
                if (!empty($headings[1])) {
                    $levels = array_map('intval', $headings[1]);
                    if ($levels[0] === 1) {
                        $pageIssues[] = 'Content starts with <h1> (use h2+ instead)';
                    }
                    for ($i = 1; $i < count($levels); $i++) {
                        if ($levels[$i] > $levels[$i - 1] + 1) {
                            $pageIssues[] = "Heading level skipped: h{$levels[$i-1]} → h{$levels[$i]}";
                            break;
                        }
                    }
                }
            }

            // 7. Broken internal links
            if (!empty($page->content)) {
                preg_match_all('/href=[\"\']\/pages\/(\d+)/i', $page->content, $links);
                if (!empty($links[1])) {
                    foreach (array_unique($links[1]) as $linkedId) {
                        $exists = $this->fetchTable('Pages')->find()
                            ->where(['id' => (int)$linkedId, 'deleted_at IS' => null])->count();
                        if ($exists === 0) {
                            $pageIssues[] = "Broken link to page #{$linkedId}";
                        }
                    }
                }
            }

            // 8. Missing images
            if (!empty($page->content)) {
                preg_match_all('/src=[\"\']\/downloads\/([^\"\']+)/i', $page->content, $imgs);
                if (!empty($imgs[1])) {
                    $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
                    foreach (array_unique($imgs[1]) as $imgFile) {
                        $imgFile = basename($imgFile);
                        if (!file_exists($mediaDir . $imgFile)) {
                            $pageIssues[] = "Missing image: {$imgFile}";
                        }
                    }
                }
            }

            if (!empty($pageIssues)) {
                $issues[$page->id] = [
                    'title' => $page->title ?: '(untitled)',
                    'issues' => $pageIssues,
                ];
            }
        }

        // 9. Orphaned media files
        $io->out('');
        $io->out('Checking orphaned media...');
        $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        $orphaned = [];
        if (is_dir($mediaDir)) {
            $allContent = '';
            foreach ($allPages as $p) {
                $allContent .= $p->content ?? '';
            }
            foreach (scandir($mediaDir) as $file) {
                if ($file[0] === '.' || is_dir($mediaDir . $file)) {
                    continue;
                }
                if (strpos($allContent, $file) === false) {
                    $orphaned[] = $file;
                }
            }
        }

        // Output
        $io->out('');
        if (empty($issues) && empty($orphaned)) {
            $io->success("All {$pageCount} pages passed quality checks.");
        } else {
            $issueCount = 0;
            foreach ($issues as $id => $data) {
                $io->out("<warning>Page #{$id}: {$data['title']}</warning>");
                foreach ($data['issues'] as $issue) {
                    $io->out("  • {$issue}");
                    $issueCount++;
                }
            }
            if (!empty($orphaned)) {
                $io->out('');
                $io->out('<warning>Orphaned media files:</warning>');
                foreach ($orphaned as $f) {
                    $io->out("  • {$f}");
                    $issueCount++;
                }
            }
            $io->out('');
            $io->warning("{$issueCount} issues found across " . count($issues) . " pages.");
        }

        // Store results for dashboard
        try {
            $cache = \Cake\Cache\Cache::getConfig('default') ? true : false;
            if ($cache) {
                $issueCount = 0;
                $stale = $noDesc = $empty = $noKeywords = $noTags = 0;
                foreach ($issues as $data) {
                    $issueCount += count($data['issues']);
                    foreach ($data['issues'] as $i) {
                        if (str_contains($i, 'Not updated')) $stale++;
                        if (str_contains($i, 'No description')) $noDesc++;
                        if (str_contains($i, 'empty or very short')) $empty++;
                        if (str_contains($i, 'No keywords')) $noKeywords++;
                        if (str_contains($i, 'No tags')) $noTags++;
                    }
                }

                // Format issues for dashboard display
                $issueList = [];
                foreach ($issues as $id => $data) {
                    foreach ($data['issues'] as $issueText) {
                        $issueList[] = ['id' => $id, 'title' => $data['title'], 'type' => $issueText];
                    }
                }

                \Cake\Cache\Cache::write('quality_check_results', [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'summary' => [
                        'stale' => $stale,
                        'noDescription' => $noDesc,
                        'emptyContent' => $empty,
                        'noKeywords' => $noKeywords,
                        'noTags' => $noTags,
                        'totalIssues' => $issueCount,
                    ],
                    'issues' => $issueList,
                    'orphaned' => array_map(fn($f) => ['title' => $f], $orphaned),
                    'pageCount' => $pageCount,
                ]);
                $io->out('Results cached for dashboard display.');
            }
        } catch (\Exception $e) {
            /* Cache not available */
        }

        return self::CODE_SUCCESS;
    }
}
