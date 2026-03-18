<?php

declare(strict_types=1);

namespace App\Controller\Component;

use App\Service\PagesService;
use Cake\Controller\Component;
use Cake\Core\Configure;

/**
 * Page Content Component (v6) — with MySQL Fulltext search.
 */
class PageContentComponent extends Component
{
    private function getShowNumbering(): bool
    {
        return Configure::read('Manual.showNavigationNumbering') ?? true;
    }

    /**
     * Search pages using MySQL FULLTEXT index in boolean mode.
     *
     * Falls back to LIKE if fulltext fails (e.g., word too short for ft_min_word_len).
     */
    public function searchPages(string $search, bool $isLoggedIn, array $filters = []): array
    {
        $Pages = $this->getController()->fetchTable('Pages');
        $clean = preg_replace('/[^\da-z ]/i', '', $search);
        $words = array_filter(explode(' ', $clean));
        if (empty($words)) {
            return ['results' => [], 'search' => $search];
        }

        // Build title lookup for chapter-numbered display
        $allPagesOrdered = $Pages->find()->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC'])->all()->toArray();
        $numbered = PagesService::calculateChapterNumbering($allPagesOrdered, $this->getShowNumbering());
        $lookup = PagesService::buildTitleLookup($numbered);
        $hideRoot = PagesService::shouldHideRoot();
        $rootId = $hideRoot ? PagesService::getRootPageId($allPagesOrdered) : 0;

        // Try FULLTEXT search first
        $ftQuery = '+' . implode('* +', $words) . '*';
        try {
            $query = $Pages->find()
                ->select(['id', 'title', 'description', 'content', 'status', 'position'])
                ->where(["MATCH(title, description, content) AGAINST(:ft IN BOOLEAN MODE)" => true,
                    'deleted_at IS' => null])
                ->bind(':ft', $ftQuery, 'string')
                ->orderBy(['position' => 'ASC']);
            if (!$isLoggedIn) {
                $query->where(['status' => 'active']);
            }
            $this->applySearchFilters($query, $filters);
            $results = [];
            foreach ($query->all() as $p) {
                if ($rootId && $p->id == $rootId) continue;
                $results[] = [
                    'id' => $p->id,
                    'title' => $lookup[$p->id] ?? $p->title ?: '(untitled)',
                    'status' => $p->status,
                    'snippet' => $this->extractSnippet($p->content ?? $p->description ?? '', $words),
                ];
            }
            if (!empty($results)) {
                return ['results' => $results, 'search' => $search, 'searchMode' => 'fulltext'];
            }
        } catch (\Exception $e) {
            // Fulltext failed — fall through to LIKE
        }

        // Fallback: LIKE search
        $query = $Pages->find()->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC']);
        foreach ($words as $w) {
            $like = '%' . $w . '%';
            $query->where(['OR' => ['title LIKE' => $like, 'description LIKE' => $like, 'content LIKE' => $like]]);
        }
        if (!$isLoggedIn) {
            $query->where(['status' => 'active']);
        }
        $this->applySearchFilters($query, $filters);

        $results = [];
        foreach ($query->all() as $p) {
            if ($rootId && $p->id == $rootId) continue;
            $results[] = [
                'id' => $p->id,
                'title' => $lookup[$p->id] ?? $p->title ?: '(untitled)',
                'status' => $p->status,
                'snippet' => $this->extractSnippet($p->content ?? $p->description ?? '', $words),
            ];
        }
        return ['results' => $results, 'search' => $search, 'searchMode' => 'like'];
    }


    /**
     * Apply optional filters (status, date) to search query.
     */
    private function applySearchFilters($query, array $filters): void
    {
        if (!empty($filters['status'])) {
            $query->where(['status' => $filters['status']]);
        }
        if (!empty($filters['since'])) {
            try {
                $query->where(['modified >=' => new \Cake\I18n\DateTime($filters['since'])]);
            } catch (\Exception $e) {
            }
        }
        if (!empty($filters['workflow'])) {
            $query->where(['workflow_status' => $filters['workflow']]);
        }
    }

    /**
     * Extract a text snippet around the first match, with <mark> highlighting.
     */
    private function extractSnippet(string $html, array $words, int $contextLen = 120): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (empty($text)) {
            return '';
        }

        $pos = 0;
        $lcText = mb_strtolower($text);
        foreach ($words as $w) {
            $p = mb_strpos($lcText, mb_strtolower($w));
            if ($p !== false) {
                $pos = $p;
                break;
            }
        }

        $start = max(0, $pos - $contextLen);
        $snippet = mb_substr($text, $start, $contextLen * 2 + 20);
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        if ($start + mb_strlen($snippet) < mb_strlen($text)) {
            $snippet .= '...';
        }

        // HTML-escape the snippet BEFORE adding <mark> tags to prevent XSS
        $snippet = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
        foreach ($words as $w) {
            $escaped = htmlspecialchars($w, ENT_QUOTES, 'UTF-8');
            $snippet = preg_replace(
                '/(' . preg_quote($escaped, '/') . ')/iu',
                '<mark>$1</mark>',
                $snippet
            );
        }
        return $snippet;
    }

    public function buildIndex(bool $isLoggedIn): array
    {
        $Pages = $this->getController()->fetchTable('Pages');
        $allPages = $Pages->find()->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC'])->all()->toArray();
        $numbered = PagesService::calculateChapterNumbering($allPages, $this->getShowNumbering());
        $lookup = PagesService::buildTitleLookup($numbered);
        $hideRoot = PagesService::shouldHideRoot();
        $rootId = $hideRoot ? PagesService::getRootPageId($allPages) : 0;

        $pagesindex = $this->getController()->fetchTable('Pagesindex');
        $query = $pagesindex->find()->contain(['Pages'])->orderBy(['Pagesindex.keyword' => 'ASC',
            'Pages.position' => 'ASC']);
        if (!$isLoggedIn) {
            $query->where(['Pages.status' => 'active']);
        }

        $indexes = [];
        foreach ($query->all() as $row) {
            if ($rootId && $row->page_id == $rootId) continue;
            $kw = $row->keyword;
            if (!isset($indexes[$kw])) {
                $indexes[$kw] = [];
            }
            $title = $lookup[$row->page_id] ?? ($row->page->title ?? '');
            $exists = false;
            foreach ($indexes[$kw] as $e) {
                if ($e['title'] === $title) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $indexes[$kw][] = ['page_id' => $row->page_id, 'title' => $title,
                    'status' => $row->page->status ?? 'inactive'];
            }
        }
        return ['indexes' => $indexes];
    }

    public function loadPrintPage(int $id, bool $isLoggedIn): ?array
    {
        $Pages = $this->getController()->fetchTable('Pages');
        try {
            $page = $Pages->get($id, contain: ['CreatedByUsers', 'ModifiedByUsers']);
            $page->keywords = PagesService::loadKeywords($id);
            $page->content = PagesService::sanitizeHtml($page->content ?? '');
            $allPages = $Pages->find()->select(['id', 'parent_id', 'position', 'title', 'status'])
                ->where(['deleted_at IS' => null]);
            if (!$isLoggedIn) {
                $allPages = $allPages->where(['status' => 'active']);
            }
            $allPages = $allPages->orderBy(['position' => 'ASC'])->all()->toArray();
            $numbered = PagesService::calculateChapterNumbering($allPages, $this->getShowNumbering());
            foreach ($numbered as $np) {
                if (($np->id ?? $np['id'] ?? 0) == $id) {
                    $page->title = $np->title ?? $np['title'] ?? $page->title;
                    break;
                }
            }
            $nav = PagesService::calculateNavigation($id, $numbered);
            return compact('page', 'nav');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function loadPrintAll(): array
    {
        $Pages = $this->getController()->fetchTable('Pages');
        $allPages = $Pages->find()->where(['status' => 'active', 'deleted_at IS' => null])
            ->orderBy(['position' => 'ASC'])->all()->toArray();
        $numbered = PagesService::calculateChapterNumbering($allPages, $this->getShowNumbering());
        $hideRoot = PagesService::shouldHideRoot();
        $rootId = $hideRoot ? PagesService::getRootPageId($allPages) : 0;
        $result = [];
        foreach ($numbered as $p) {
            $pid = is_object($p) ? $p->id : ($p['id'] ?? 0);
            if ($rootId && $pid == $rootId) continue;
            if (is_object($p)) {
                $p->content = PagesService::sanitizeHtml($p->content ?? '');
            }
            $result[] = $p;
        }
        return $result;
    }
}
