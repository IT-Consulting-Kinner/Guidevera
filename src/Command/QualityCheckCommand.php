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
 * Checks: missing descriptions, stale content, broken internal links,
 * orphaned media, heading structure issues. Results can be viewed
 * in the admin dashboard via /pages/stats.
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

        $pages = $this->fetchTable('Pages')->find()
            ->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC'])->all();
        $issues = [];
        $pageCount = 0;

        foreach ($pages as $page) {
            $pageCount++;
            $pageIssues = [];

            // 1. Missing description
            if (empty(trim($page->description ?? ''))) {
                $pageIssues[] = 'No description';
            }

            // 2. Stale content (not modified in 12+ months)
            if ($page->modified && $page->modified->wasWithinLast('12 months') === false) {
                $months = (int)$page->modified->diffInMonths(new \Cake\I18n\DateTime());
                $pageIssues[] = "Not updated in {$months} months";
            }

            // 3. Empty content
            $plainContent = strip_tags($page->content ?? '');
            if (strlen(trim($plainContent)) < 10) {
                $pageIssues[] = 'Content is empty or very short';
            }

            // 4. Heading structure (h2/h3 before h1, skipped levels)
            if (!empty($page->content)) {
                preg_match_all('/<h([1-6])/i', $page->content, $headings);
                if (!empty($headings[1])) {
                    $levels = array_map('intval', $headings[1]);
                    // Content shouldn't start with h1 (page title is already h3)
                    if ($levels[0] === 1) {
                        $pageIssues[] = 'Content starts with <h1> (use h2+ instead)';
                    }
                    // Check for skipped levels (e.g. h2 → h4)
                    for ($i = 1; $i < count($levels); $i++) {
                        if ($levels[$i] > $levels[$i - 1] + 1) {
                            $pageIssues[] = "Heading level skipped: h{$levels[$i-1]} → h{$levels[$i]}";
                            break;
                        }
                    }
                }
            }

            // 5. Broken internal links
            if (!empty($page->content)) {
                preg_match_all('/href=["\']\/pages\/(\d+)/i', $page->content, $links);
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

            // 6. Missing images (referenced but file doesn't exist)
            if (!empty($page->content)) {
                preg_match_all('/src=["\']\/downloads\/([^"\']+)/i', $page->content, $imgs);
                if (!empty($imgs[1])) {
                    $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
                    foreach (array_unique($imgs[1]) as $imgFile) {
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

        // 7. Orphaned media files (in storage but not referenced)
        $io->out('');
        $io->out('Checking orphaned media...');
        $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        $orphaned = [];
        if (is_dir($mediaDir)) {
            $allContent = '';
            foreach ($pages as $p) {
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
                $stale = $noDesc = $empty = 0;
                foreach ($issues as $data) {
                    $issueCount += count($data['issues']);
                    foreach ($data['issues'] as $i) {
                        if (str_contains($i, 'Not updated')) {
                            $stale++;
                        }
                        if (str_contains($i, 'No description')) {
                            $noDesc++;
                        }
                        if (str_contains($i, 'empty or very short')) {
                            $empty++;
                        }
                    }
                }
                \Cake\Cache\Cache::write('quality_check_results', [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'summary' => ['stale' => $stale, 'noDescription' => $noDesc, 'emptyContent' => $empty,
                        'totalIssues' => $issueCount],
                    'issues' => $issues,
                    'orphaned' => $orphaned,
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
