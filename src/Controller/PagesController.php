<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PagesService;
use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Pages Controller — CRUD, tree operations, search, print.
 *
 * Revisions → RevisionsController
 * Feedback  → FeedbackController
 */
class PagesController extends AppController
{
    protected \App\Model\Table\PagesTable $Pages;

    public function initialize(): void
    {
        parent::initialize();
        $this->Pages = $this->fetchTable('Pages');
        $this->loadComponent('PageContent');
    }

    private function getShowNumbering(): bool
    {
        return Configure::read('Manual.showNavigationNumbering') ?? true;
    }

    private function getCurrentLocale(): string
    {
        return $this->request->getQuery('locale', Configure::read('Manual.defaultLocale') ?? 'en');
    }


    // ── SSR ────────────────────────────────────────────────────────

    public function index(?int $id = null): void
    {
        $locale = $this->getCurrentLocale();
        $numberedPages = PagesService::getNumberedPages($this->getShowNumbering());
        $showRoot = Configure::read('Manual.showNavigationRoot') ?? true;

        if (!$id && !empty($numberedPages)) {
            foreach ($numberedPages as $i => $p) {
                if ($i === 0 && !$showRoot && !$this->isLoggedIn()) {
                    continue;
                }
                if (($p->status ?? $p['status'] ?? '') === 'active') {
                    $id = $p->id ?? $p['id'];
                    break;
                }
            }
            if (!$id && !empty($numberedPages)) {
                $id = $numberedPages[0]->id ?? $numberedPages[0]['id'];
            }
        }

        $page = null;
        if ($id) {
            try {
                $page = $this->Pages->get($id, contain: ['CreatedByUsers', 'ModifiedByUsers', 'Pagesindex']);
                $page->keywords = PagesService::loadKeywords($id);
                $page = $this->applyTranslation($page, $locale);
                $page->content = PagesService::sanitizeHtml($page->content ?? '');
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }

        $openState = [];
        $authData = $this->currentUser();
        if (!empty($authData['page_tree'])) {
            $pt = json_decode($authData['page_tree'], true);
            if (isset($pt['open']) && is_array($pt['open'])) {
                $openState = $pt['open'];
            }
        }

        $ssrNavHtml = PagesService::buildNavigationHtml(
            $numberedPages,
            (int)$id,
            $showRoot || $this->isLoggedIn(),
            (Configure::read('Manual.showNavigationIcons') ?? true) || $this->isLoggedIn(),
            $this->isLoggedIn(),
            $openState
        );
        $nav = $id ? PagesService::calculateNavigation($id, $numberedPages) : [];

        $ssrTree = json_encode(array_map(fn($p) => [
            'id' => $p->id ?? $p['id'], 'parent_id' => $p->parent_id ?? $p['parent_id'] ?? 0,
            'title' => $p->title ?? $p['title'] ?? '', 'status' => $p->status ?? $p['status'] ?? 'inactive',
            'views' => $p->views ?? $p['views'] ?? 0, 'chapter' => $p->chapter ?? $p['chapter'] ?? '',
        ], $numberedPages));

        $feedbackSummary = null;
        if ($id && (Configure::read('Manual.enableFeedback') ?? false)) {
            $fb = $this->fetchTable('PageFeedback');
            $counts = $fb->find()->select(['rating', 'cnt' => $fb->find()->func()->count('*')])
                ->where(['page_id' => $id])->groupBy('rating')->all()->combine('rating', 'cnt')->toArray();
            $feedbackSummary = ['up' => (int)($counts[1] ?? 0), 'down' => (int)($counts[-1] ?? 0)];
        }

        $allPages = $this->Pages->find()
            ->where(['deleted_at IS' => null])
            ->orderBy(['position' => 'ASC'])->all()->toArray();
        $this->set(compact(
            'page',
            'allPages',
            'numberedPages',
            'nav',
            'id',
            'ssrNavHtml',
            'ssrTree',
            'feedbackSummary'
        ));
    }

    // ── JSON APIs ──────────────────────────────────────────────────

    public function getTree(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        $pages = PagesService::getNumberedPages($this->getShowNumbering());
        $arrTree = array_map(fn($p) => [
            'id' => $p->id ?? $p['id'], 'parent_id' => $p->parent_id ?? $p['parent_id'] ?? 0,
            'title' => $p->title ?? $p['title'], 'status' => $p->status ?? $p['status'],
            'views' => $p->views ?? $p['views'] ?? 0, 'chapter' => $p->chapter ?? $p['chapter'] ?? '',
        ], $pages);
        return $this->jsonSuccess(['arrTree' => $arrTree]);
    }

    public function show(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $id = (int)$this->request->getData('id');
        $locale = $this->request->getData('locale', $this->getCurrentLocale());
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        try {
            $page = $this->Pages->get($id, contain: ['CreatedByUsers', 'ModifiedByUsers']);
            $page->keywords = PagesService::loadKeywords($id);
            // Atomic increment — no race condition, no pre-read needed
            $this->Pages->getConnection()->execute('UPDATE pages SET views = views + 1 WHERE id = ?', [$id]);
            $page = $this->applyTranslation($page, $locale);

            // Chapter numbering from cache
            $numbered = PagesService::getNumberedPages($this->getShowNumbering());
            foreach ($numbered as $np) {
                if (($np->id ?? $np['id'] ?? 0) == $id) {
                    $page->title = $np->title ?? $np['title'] ?? $page->title;
                    break;
                }
            }

            // Feedback summary (single query)
            $feedback = null;
            if (Configure::read('Manual.enableFeedback') ?? false) {
                $fb = $this->fetchTable('PageFeedback');
                $counts = $fb->find()->select(['rating', 'cnt' => $fb->find()->func()->count('*')])
                    ->where(['page_id' => $id])->groupBy('rating')->all()->combine('rating', 'cnt')->toArray();
                $feedback = [
                    'up' => (int)($counts[1] ?? 0), 'down' => (int)($counts[-1] ?? 0),
                    'comments' => $fb->find()->where(['page_id' => $id, 'status' => 'approved'])
                        ->orderBy(['created' => 'DESC'])->limit(10)->all()->map(fn($f) => [
                            'rating' => $f->rating, 'comment' => $f->comment, 'created' => $f->created->format('d.m.Y'),
                        ])->toArray(),
                ];
            }

            return $this->jsonSuccess([
                'id' => $page->id, 'title' => $page->title, 'status' => $page->status,
                'content' => PagesService::sanitizeHtml($page->content ?? ''),
                'keywords' => $page->keywords ?? '',
                'created' => $page->created ? $page->created->format('d.m.Y H:i') : '',
                'createdBy' => $page->creator->fullname ?? '',
                'modified' => $page->modified ? $page->modified->format('d.m.Y H:i') : '',
                'modifiedBy' => $page->modifier->fullname ?? '',
                'feedback' => $feedback, 'locale' => $locale,
                'breadcrumbs' => (Configure::read('Manual.enableBreadcrumbs') ?? true) ?
                    PagesService::buildBreadcrumbs($id, $numbered) : [],
                'nav' => (Configure::read('Manual.enablePrevNext') ?? true) ? PagesService::calculateNavigation(
                    $id,
                    $numbered
                ) : [],
            ]);
        } catch (\Exception $e) {
            Log::error('Page show failed: ' . $e->getMessage());
            return $this->jsonError('page_not_found');
        }
    }

    public function edit(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_EDITOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id');
        $locale = $this->request->getData('locale', $this->getCurrentLocale());
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        try {
            $page = $this->Pages->get($id, contain: ['CreatedByUsers', 'ModifiedByUsers']);
            $page->keywords = PagesService::loadKeywords($id);
            $page = $this->applyTranslation($page, $locale);

            $locales = $translations = [];
            if (Configure::read('Manual.enableTranslations') ?? false) {
                $locales = Configure::read('Manual.contentLocales') ?? ['en'];
                $translations = $this->fetchTable('PageTranslations')->find()
                    ->where(['page_id' => $id])->all()->combine('locale', fn($t) => $t)->toArray();
            }

            return $this->jsonSuccess([
                'id' => $page->id, 'title' => $page->title ?? '', 'description' => $page->description ?? '',
                'keywords' => $page->keywords ?? '', 'content' => $page->content ?? '',
                'status' => $page->status, 'parentId' => $page->parent_id ?? 0,
                'created' => $page->created ? $page->created->format('d.m.Y H:i') : '',
                'createdBy' => $page->creator->fullname ?? '',
                'modified' => $page->modified ? $page->modified->format('d.m.Y H:i') : '',
                'modifiedBy' => $page->modifier->fullname ?? '',
                'locale' => $locale, 'availableLocales' => $locales,
                'translatedLocales' => array_keys($translations),
            ]);
        } catch (\Exception $e) {
            Log::error('Page edit failed: ' . $e->getMessage());
            return $this->jsonError('can_not_read_item');
        }
    }

    public function create(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $user = $this->currentUser();
        $count = $this->Pages->find()->where(['deleted_at IS' => null])->count();
        $page = $this->Pages->newEntity([
            'title' => '', 'description' => '', 'content' => '',
            'status' => $count === 0 ? 'active' : 'inactive',
            'views' => 0, 'position' => $count, 'parent_id' => null,
            'locale' => Configure::read('Manual.defaultLocale') ?? 'en',
            'created_by' => $user['id'] ?? 0, 'modified_by' => $user['id'] ?? 0,
        ]);
        try {
            if ($this->Pages->save($page)) {
                PagesService::invalidateCache();
                $this->audit('page_create', 'page', $page->id, 'Created by ' . ($user['fullname'] ?? ''));
                $this->sendNotification('Page created', "New page #{$page->id} created by " .
                    ($user['fullname'] ?? 'Unknown'));
                return $this->jsonSuccess(['intId' => $page->id]);
            }
        } catch (\Exception $e) {
            Log::error('Page create failed: ' . $e->getMessage());
        }
        return $this->jsonError('can_not_create_item');
    }

    public function save(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_EDITOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id');
        $locale = $this->request->getData('locale', Configure::read('Manual.defaultLocale') ?? 'en');
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        try {
            $page = $this->Pages->get($id);
            $user = $this->currentUser();
            $defaultLocale = Configure::read('Manual.defaultLocale') ?? 'en';
            $title = $this->request->getData('title', $page->title);
            $description = $this->request->getData('description', $page->description);
            $content = $this->request->getData('content', $page->content);

            // Create revision before saving
            if (Configure::read('Manual.enableRevisions') ?? true) {
                $rev = $this->fetchTable('PageRevisions');
                $rev->save($rev->newEntity([
                    'page_id' => $id, 'title' => $page->title, 'description' => $page->description,
                    'content' => $page->content, 'created_by' => $user['id'] ?? 0,
                    'revision_note' => $this->request->getData('revision_note', ''),
                ]));
            }

            if ($locale === $defaultLocale) {
                $page = $this->Pages->patchEntity($page, [
                    'title' => $title, 'description' => $description,
                    'content' => $content, 'modified_by' => $user['id'] ?? 0,
                ]);
                if (!$this->Pages->save($page)) {
                    return $this->jsonError('can_not_save_item');
                }
            } else {
                $trans = $this->fetchTable('PageTranslations');
                $existing = $trans->find()->where(['page_id' => $id, 'locale' => $locale])->first();
                $data = ['title' => $title, 'description' => $description, 'content' => $content,
                    'modified_by' => $user['id'] ?? 0];
                if ($existing) {
                    $trans->save($trans->patchEntity($existing, $data));
                } else {
                    $trans->save($trans->newEntity(array_merge($data, ['page_id' => $id, 'locale' => $locale])));
                }
                $this->Pages->updateAll(['modified_by' => $user['id'] ?? 0], ['id' => $id]);
            }

            PagesService::invalidateCache();
            $this->saveKeywords($id, $this->request->getData('keywords', ''));
            $this->audit('page_save', 'page', $id, "'{$title}' saved by " . ($user['fullname'] ?? ''));
            $this->sendNotification('Page updated', "Page '{$title}' (#{$id}) updated by " .
                ($user['fullname'] ?? 'Unknown'));
            \App\Service\WebhookService::fire('page.updated', ['page_id' => $id, 'title' => $title]);
            // Notify subscribers
            if (Configure::read('Manual.enableSubscriptions') ?? false) {
                $this->notifySubscribers($id, "Page '{$title}' has been updated.");
            }
            // Mark translations as potentially stale
            if ($locale === $defaultLocale && (Configure::read('Manual.enableTranslations') ?? false)) {
                $this->fetchTable('PageTranslations')->updateAll(['base_modified' =>
                    new \Cake\I18n\DateTime()], ['page_id' => $id]);
            }
            return $this->jsonSuccess(['intAffectedRows' => 1]);
        } catch (\Exception $e) {
            Log::error('Page save failed: ' . $e->getMessage());
        }
        return $this->jsonError('can_not_save_item');
    }

    public function delete(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id');
        if ($this->Pages->findChildrenOf($id)->count() > 0) {
            return $this->jsonError('has_child');
        }
        try {
            // Soft delete: set deleted_at timestamp instead of removing
            $this->Pages->updateAll(['deleted_at' => new \Cake\I18n\DateTime()], ['id' => $id]);
            PagesService::invalidateCache();
            $page = $this->Pages->get($id);
            $this->audit('page_delete', 'page', $id, "'{$page->title}' moved to trash");
            $this->sendNotification('Page deleted', "Page '{$page->title}' (#{$id}) moved to trash");
            return $this->jsonSuccess(['intAffectedRows' => 1]);
        } catch (\Exception $e) {
            Log::error('Page delete failed: ' . $e->getMessage());
        }
        return $this->jsonError('can_not_delete_item');
    }

    public function setStatus(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id');
        $status = $this->request->getData('status');
        if (!in_array($status, ['active', 'inactive'])) {
            return $this->jsonError('invalid_status');
        }
        $result = $this->Pages->updateAll(['status' => $status], ['id' => $id]);
        PagesService::invalidateCache();
        return $this->jsonSuccess(['intAffectedRows' => $result]);
    }

    public function updateOrder(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        parse_str($this->request->getData('strPages', ''), $arr);
        $count = 0;
        if (!empty($arr['list'])) {
            foreach ($arr['list'] as $pageId => $parentId) {
                $this->Pages->updateAll(['position' => $count, 'parent_id' =>
                    $parentId === 'null' ? null : $parentId], ['id' => (int)$pageId]);
                $count++;
            }
        }
        PagesService::invalidateCache();
        return $this->jsonSuccess(['intAffectedRows' => $count]);
    }

    public function updateParent(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id');
        $parentId = $this->request->getData('parent_id');
        $this->Pages->updateAll(
            ['parent_id' => ($parentId === 'null' || $parentId === '0') ? null : $parentId],
            ['id' => $id]
        );
        PagesService::invalidateCache();
        return $this->jsonSuccess(['intAffectedRows' => 1]);
    }

    public function browse(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->isLoggedIn()) {
            return $this->jsonError('not_authenticated');
        }
        $numbered = PagesService::getNumberedPages($this->getShowNumbering());
        $pages = [];
        foreach ($numbered as $p) {
            if (($p->status ?? $p['status'] ?? '') === 'active') {
                $pages[] = ['id' => $p->id ?? $p['id'], 'title' => $p->title ?? $p['title']];
            }
        }
        return $this->jsonSuccess(['pages' => $pages]);
    }

    public function search(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        $search = trim($this->request->getData('search', ''));
        if (empty($search)) {
            return $this->jsonSuccess(['results' => []]);
        }
        $filters = [
            'status' => $this->request->getData('filter_status', ''),
            'since' => $this->request->getData('filter_since', ''),
            'workflow' => $this->request->getData('filter_workflow', ''),
        ];
        $result = $this->PageContent->searchPages($search, $this->isLoggedIn(), $filters);
        if (empty($result['results'])) {
            $this->audit('search_no_results', 'search', 0, $search);
        }
        return $this->jsonSuccess($result);
    }

    public function buildIndex(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        return $this->jsonSuccess($this->PageContent->buildIndex($this->isLoggedIn()));
    }

    // ── Media Upload ───────────────────────────────────────────────

    public function uploadMedia(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_EDITOR)) {
            return $this->response;
        }
        $file = $this->request->getUploadedFile('file');
        if (!$file) {
            return $this->jsonError('upload_failed');
        }

        $error = \App\Service\UploadService::validate($file);
        if ($error) {
            return $this->jsonError($error);
        }

        $safeName = \App\Service\UploadService::timestampedName(basename($file->getClientFilename()));
        $mediaDir = ROOT . DS . 'storage' . DS . 'media' . DS;
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }
        $file->moveTo($mediaDir . $safeName);
        return $this->jsonSuccess(['url' => '/downloads/' . $safeName]);
    }

    // ── Print / Sitemap ────────────────────────────────────────────

    public function sitemap(): void
    {
        $this->set('pages', $this->Pages->find()->where(['status' => 'active', 'deleted_at IS' => null])
            ->orderBy(['position' => 'ASC'])->all());
    }

    public function printPage(?int $id = null): void
    {
        if (!(Configure::read('Manual.enablePrint') ?? false)) {
            $this->redirect('/');
            return;
        }
        $this->viewBuilder()->disableAutoLayout();
        if (!$id) {
            $id = (int)$this->request->getParam('id');
        }
        if (!$id) {
            $this->set('error', 'Page not found');
            return;
        }
        $result = $this->PageContent->loadPrintPage($id, $this->isLoggedIn());
        if ($result === null) {
            $this->set('error', 'Page not found');
            return;
        }
        $this->set($result);
    }

    public function printAll(): void
    {
        if (!(Configure::read('Manual.enablePrint') ?? false)) {
            $this->redirect('/');
            return;
        }
        $this->viewBuilder()->disableAutoLayout();
        $this->set('pages', $this->PageContent->loadPrintAll());
    }

    // ── Dashboard (shown after login) ──

    public function dashboard(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/user/login');
            return;
        }
        $userId = $this->currentUser()['id'] ?? 0;
        $recentlyEdited = $this->Pages->find()
            ->where(['modified_by' => $userId, 'deleted_at IS' => null])
            ->orderBy(['modified' => 'DESC'])->limit(10)->all()->toArray();
        $recentlyCreated = $this->Pages->find()
            ->where(['deleted_at IS' => null])->orderBy(['created' => 'DESC'])->limit(10)->all()->toArray();
        $trashCount = $this->Pages->find()->where(['deleted_at IS NOT' => null])->count();
        $totalPages = $this->Pages->find()->where(['deleted_at IS' => null])->count();
        $pendingFeedback = 0;
        if ($this->hasRole(self::ROLE_ADMIN) && (Configure::read('Manual.enableFeedback') ?? false)) {
            $pendingFeedback = $this->fetchTable('PageFeedback')->find()->where(['status' => 'pending'])->count();
        }

        // Quality metrics
        $stalePages = $this->Pages->find()->where(['deleted_at IS' => null, 'modified <' =>
            new \Cake\I18n\DateTime('-12 months')])
            ->count();
        $noDescription = $this->Pages->find()->where(['deleted_at IS' => null, 'OR' => ['description IS' =>
            null, 'description' => '']])
            ->count();
        $reviewQueue = $this->Pages->find()->where(['deleted_at IS' => null, 'workflow_status' => 'review'])->count();

        $searchMisses = [];
        if (Configure::read('Manual.enableAuditLog') ?? false) {
            $searchMisses = $this->fetchTable('AuditLog')->find()
                ->where(['action' => 'search_no_results'])
                ->orderBy(['created' => 'DESC'])->limit(10)->select(['details', 'created'])->all()->toArray();
        }

        // My drafts
        $myDrafts = $this->Pages->find()->where(['deleted_at IS' => null, 'workflow_status' => 'draft',
            'modified_by' => $userId])
            ->orderBy(['modified' => 'DESC'])->limit(10)->all()->toArray();

        // My assigned reviews
        $myReviews = [];
        if (Configure::read('Manual.enableReviewProcess') ?? false) {
            $myReviews = $this->fetchTable('PageReviews')->find()->contain(['Pages'])
                ->where(['reviewer_id' => $userId, 'PageReviews.status' => 'pending'])
                ->orderBy(['PageReviews.created' => 'DESC'])->limit(10)->all()->toArray();
        }

        $this->set(compact('recentlyEdited', 'recentlyCreated', 'trashCount', 'totalPages', 'pendingFeedback', '
            stalePages', 'noDescription', 'reviewQueue', 'searchMisses', 'myDrafts', 'myReviews'));
    }

    // ── Trash ──

    public function trash(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $items = $this->Pages->find()->where(['deleted_at IS NOT' => null])
            ->orderBy(['deleted_at' => 'DESC'])->limit(100)->all();
        $list = [];
        foreach ($items as $p) {
            $list[] = ['id' => $p->id, 'title' => $p->title, 'deletedAt' => $p->deleted_at->format('d.m.Y H:i')];
        }
        return $this->jsonSuccess(['trash' => $list]);
    }

    public function trashRestore(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id', 0);
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        $this->Pages->updateAll(['deleted_at' => null, 'status' => 'inactive'], ['id' => $id]);
        PagesService::invalidateCache();
        $this->audit('page_restore', 'page', $id, 'Restored from trash');
        return $this->jsonSuccess(['intAffectedRows' => 1]);
    }

    public function trashPurge(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_ADMIN)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id', 0);
        if ($id) {
            // Purge single item
            $page = $this->Pages->find()->where(['id' => $id, 'deleted_at IS NOT' => null])->first();
            if ($page) {
                $this->Pages->delete($page);
                $this->fetchTable('Pagesindex')->deleteAll(['page_id' => $id]);
                $this->fetchTable('PageTranslations')->deleteAll(['page_id' => $id]);
                $this->audit('page_purge', 'page', $id, 'Permanently deleted');
            }
        } else {
            // Purge all expired trash
            $days = Configure::read('Manual.trashRetentionDays') ?? 30;
            $cutoff = new \Cake\I18n\DateTime("-{$days} days");
            $expired = $this->Pages->find()->where(['deleted_at <' => $cutoff])->all();
            foreach ($expired as $p) {
                $this->Pages->delete($p);
                $this->fetchTable('Pagesindex')->deleteAll(['page_id' => $p->id]);
                $this->fetchTable('PageTranslations')->deleteAll(['page_id' => $p->id]);
            }
            $this->audit('trash_purge', 'system', 0, "Purged items older than {$days} days");
        }
        PagesService::invalidateCache();
        return $this->jsonSuccess(['success' => true]);
    }

    // ── Exports ──

    public function exportMarkdown(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableMarkdownExport') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        $id = (int)$this->request->getData('id', $this->request->getQuery('id', 0));
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        try {
            $page = $this->Pages->get($id);
            // Simple HTML-to-Markdown conversion
            $md = "# " . ($page->title ?? '') . "\n\n";
            $content = $page->content ?? '';
            $content = preg_replace('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/si', "\n" .
                str_repeat('#', 2) . " $2\n", $content);
            $content = preg_replace('/<p[^>]*>(.*?)<\/p>/si', "$1\n\n", $content);
            $content = preg_replace('/<br\s*\/?>/si', "\n", $content);
            $content = preg_replace('/<strong[^>]*>(.*?)<\/strong>/si', '**$1**', $content);
            $content = preg_replace('/<em[^>]*>(.*?)<\/em>/si', '*$1*', $content);
            $content = preg_replace('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/si', '[$2]($1)', $content);
            $content = preg_replace('/<code[^>]*>(.*?)<\/code>/si', '`$1`', $content);
            $content = preg_replace('/<li[^>]*>(.*?)<\/li>/si', "- $1\n", $content);
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
            $md .= trim($content);

            return $this->response->withType('text/markdown')
                ->withHeader('Content-Disposition', 'attachment; filename="' .
                    preg_replace('/[^a-zA-Z0-9_-]/', '_', $page->title) . '.md"')
                ->withStringBody($md);
        } catch (\Exception $e) {
            return $this->jsonError('export_failed');
        }
    }

    public function exportPdf(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!(Configure::read('Manual.enablePdfExport') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        $id = (int)$this->request->getQuery('id', 0);
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        try {
            $page = $this->Pages->get($id, contain: ['CreatedByUsers', 'ModifiedByUsers']);
            $html = '<!DOCTYPE html><html><head><meta
                charset="utf-8"><style>body{font-family:sans-serif;max-width:800px;
                margin:auto;padding:2rem}h1{border-bottom:2px solid #333}
                table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;
                padding:0.5rem}</style></head><body>';
            $html .= '<h1>' . htmlspecialchars($page->title) . '</h1>';
            $html .= PagesService::sanitizeHtml($page->content ?? '');
            $html .= '</body></html>';

            // Try wkhtmltopdf, fall back to HTML download
            $tmpHtml = tempnam(sys_get_temp_dir(), 'manual_') . '.html';
            $tmpPdf = str_replace('.html', '.pdf', $tmpHtml);
            file_put_contents($tmpHtml, $html);

            $wk = trim(shell_exec('which wkhtmltopdf 2>/dev/null') ?? '');
            if ($wk && file_exists($wk)) {
                exec(escapeshellarg($wk) .
                    ' --quiet ' . escapeshellarg($tmpHtml) . ' ' . escapeshellarg($tmpPdf) . ' 2>&1');
                if (file_exists($tmpPdf)) {
                    $pdf = file_get_contents($tmpPdf);
                    @unlink($tmpHtml);
                    @unlink($tmpPdf);
                    return $this->response->withType('application/pdf')
                        ->withHeader('Content-Disposition', 'attachment; filename="' .
                            preg_replace('/[^a-zA-Z0-9_-]/', '_', $page->title) . '.pdf"')
                        ->withStringBody($pdf);
                }
            }
            // Fallback: serve HTML
            @unlink($tmpHtml);
            return $this->response->withType('text/html')
                ->withHeader('Content-Disposition', 'attachment; filename="' .
                    preg_replace('/[^a-zA-Z0-9_-]/', '_', $page->title) . '.html"')
                ->withStringBody($html);
        } catch (\Exception $e) {
            return $this->jsonError('export_failed');
        }
    }

    // ── Stats (admin dashboard) ──

    public function stats(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_ADMIN)) {
            return $this->response;
        }
        $pages = $this->Pages->find()->where(['deleted_at IS' => null]);
        $topViewed = $this->Pages->find()->where(['deleted_at IS' => null])
            ->orderBy(['views' => 'DESC'])->limit(10)->select(['id', 'title', 'views'])->all()->toArray();
        $recentActivity = $this->Pages->find()->where(['deleted_at IS' => null])
            ->orderBy(['modified' => 'DESC'])->limit(10)->select(['id', 'title', 'modified',
                'modified_by'])->all()->toArray();

        // Feedback stats
        $fbStats = null;
        if (Configure::read('Manual.enableFeedback') ?? false) {
            $fb = $this->fetchTable('PageFeedback');
            $fbStats = [
                'total' => $fb->find()->count(),
                'pending' => $fb->find()->where(['status' => 'pending'])->count(),
                'positive' => $fb->find()->where(['rating' => 1])->count(),
                'negative' => $fb->find()->where(['rating' => -1])->count(),
            ];
        }

        // Search terms without results (from audit log)
        $searchMisses = [];
        if (Configure::read('Manual.enableAuditLog') ?? false) {
            $searchMisses = $this->fetchTable('AuditLog')->find()
                ->where(['action' => 'search_no_results'])
                ->orderBy(['created' => 'DESC'])->limit(20)
                ->select(['details', 'created'])->all()->toArray();
        }

        return $this->jsonSuccess([
            'totalPages' => $pages->count(),
            'activePages' => (clone $pages)->where(['status' => 'active'])->count(),
            'topViewed' => array_map(
                fn($p) => ['id' => $p->id, 'title' => $p->title, 'views' => $p->views],
                $topViewed
            ),
            'recentActivity' => array_map(fn($p) => ['id' => $p->id, 'title' => $p->title, 'modified' =>
                $p->modified->format('d.m.Y H:i')], $recentActivity),
            'feedback' => $fbStats,
            'searchMisses' => array_map(
                fn($l) => ['term' => $l->details, 'date' => $l->created->format('d.m.Y')],
                $searchMisses
            ),
        ]);
    }

    // ── Audit Log ──

    public function auditLog(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_ADMIN)) {
            return $this->response;
        }
        if (!(Configure::read('Manual.enableAuditLog') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        $entries = $this->fetchTable('AuditLog')->find()->contain(['Users'])
            ->orderBy(['AuditLog.created' => 'DESC'])->limit(200)->all();
        $list = [];
        foreach ($entries as $e) {
            $list[] = [
                'id' => $e->id, 'action' => $e->action, 'entityType' => $e->entity_type,
                'entityId' => $e->entity_id, 'details' => $e->details,
                'user' => $e->user->fullname ?? '', 'ip' => $e->ip_address,
                'created' => $e->created->format('d.m.Y H:i:s'),
            ];
        }
        return $this->jsonSuccess(['auditLog' => $list]);
    }

    // ── Workflow ──

    public function setWorkflowStatus(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $id = (int)$this->request->getData('id');
        $ws = $this->request->getData('workflow_status', '');
        $allowed = ['draft', 'review', 'published', 'archived'];
        if (!in_array($ws, $allowed)) {
            return $this->jsonError('invalid_workflow_status');
        }

        // Only admin/contributor can publish; editors can only submit for review
        if ($ws === 'published' && !$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('insufficient_permissions');
        }

        $this->Pages->updateAll(['workflow_status' => $ws], ['id' => $id, 'deleted_at IS' => null]);
        $this->audit('workflow_change', 'page', $id, "Status → {$ws}");

        // Notify on review submission
        if ($ws === 'review') {
            $page = $this->Pages->find()->select(['title'])->where(['id' => $id])->first();
            $this->sendNotification('Page submitted for review', "Page '{$page->title}' (#{$id}) needs review.");
        }

        PagesService::invalidateCache();
        return $this->jsonSuccess(['intAffectedRows' => 1]);
    }

    public function reviewQueue(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $items = $this->Pages->find()
            ->contain(['ModifiedByUsers'])
            ->where(['workflow_status' => 'review', 'deleted_at IS' => null])
            ->orderBy(['modified' => 'DESC'])->limit(50)->all();
        $list = [];
        foreach ($items as $p) {
            $list[] = [
                'id' => $p->id, 'title' => $p->title,
                'modifiedBy' => $p->modifier->fullname ?? '',
                'modified' => $p->modified->format('d.m.Y H:i'),
            ];
        }
        return $this->jsonSuccess(['queue' => $list]);
    }

    // ── Tags ──

    public function tags(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        $pageId = (int)$this->request->getData('page_id', 0);
        if ($pageId) {
            // Tags for specific page
            $tags = $this->fetchTable('PageTags')->find()
                ->where(['page_id' => $pageId])->all()->extract('tag')->toArray();
            return $this->jsonSuccess(['tags' => $tags]);
        }
        // All tags with counts
        $tags = $this->fetchTable('PageTags')->find()
            ->select(['tag', 'cnt' => $this->fetchTable('PageTags')->find()->func()->count('*')])
            ->groupBy('tag')->orderBy(['cnt' => 'DESC'])->all()->toArray();
        return $this->jsonSuccess(['tags' => array_map(fn($t) => ['tag' => $t->tag, 'count' => (int)$t->cnt], $tags)]);
    }

    public function saveTags(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_EDITOR)) {
            return $this->response;
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        $tagsRaw = $this->request->getData('tags', '');
        if (!$pageId) {
            return $this->jsonError('invalid_id');
        }

        $tbl = $this->fetchTable('PageTags');
        $tbl->deleteAll(['page_id' => $pageId]);

        $tags = array_unique(array_filter(array_map(function ($t) {
            $t = mb_strtolower(trim($t));
            return (empty($t) || mb_strlen($t) > 100) ? null : $t;
        }, explode(',', $tagsRaw))));

        foreach ($tags as $tag) {
            $tbl->save($tbl->newEntity(['page_id' => $pageId, 'tag' => $tag]));
        }
        $this->audit('tags_update', 'page', $pageId, implode(', ', $tags));
        return $this->jsonSuccess(['tags' => array_values($tags)]);
    }

    public function relatedPages(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        $pageId = (int)$this->request->getData('page_id', 0);
        if (!$pageId) {
            return $this->jsonError('invalid_id');
        }

        // Find pages that share tags with this page
        $myTags = $this->fetchTable('PageTags')->find()
            ->where(['page_id' => $pageId])->all()->extract('tag')->toArray();
        if (empty($myTags)) {
            return $this->jsonSuccess(['related' => []]);
        }

        $related = $this->fetchTable('PageTags')->find()
            ->contain(['Pages'])->where(['tag IN' => $myTags, 'page_id !=' => $pageId])
            ->where(['Pages.deleted_at IS' => null, 'Pages.status' => 'active'])
            ->groupBy('PageTags.page_id')
            ->orderBy([$this->fetchTable('PageTags')->find()->func()->count('*') => 'DESC'])
            ->limit(5)->all();

        $list = [];
        foreach ($related as $r) {
            $list[] = ['id' => $r->page_id, 'title' => $r->page->title ?? ''];
        }
        return $this->jsonSuccess(['related' => $list]);
    }

    // ── Quality (reads cached results from bin/cake quality-check) ──

    public function qualityReport(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_ADMIN)) {
            return $this->response;
        }
        try {
            $data = \Cake\Cache\Cache::read('quality_check_results');
        } catch (\Exception $e) {
            $data = null;
        }

        if (!$data) {
            // Generate on-the-fly if no cached results
            $pages = $this->Pages->find()->where(['deleted_at IS' => null])->all();
            $stale = $noDesc = $empty = 0;
            foreach ($pages as $p) {
                if (empty(trim($p->description ?? ''))) {
                    $noDesc++;
                }
                if ($p->modified && $p->modified->wasWithinLast('12 months') === false) {
                    $stale++;
                }
                if (strlen(trim(strip_tags($p->content ?? ''))) < 10) {
                    $empty++;
                }
            }
            $data = [
                'timestamp' => date('Y-m-d H:i:s'),
                'summary' => ['stale' => $stale, 'noDescription' => $noDesc, 'emptyContent' => $empty,
                    'totalIssues' => $stale + $noDesc + $empty],
                'issues' => [],
                'orphaned' => [],
                'pageCount' => $pages->count(),
            ];
        }
        return $this->jsonSuccess(['quality' => $data]);
    }

    // ── Subscriptions (page change notifications) ──

    public function subscribe(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableSubscriptions') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->isLoggedIn()) {
            return $this->jsonError('not_authenticated');
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        if (!$pageId) {
            return $this->jsonError('invalid_id');
        }
        $tbl = $this->fetchTable('PageSubscriptions');
        $userId = $this->currentUser()['id'] ?? 0;
        $existing = $tbl->find()->where(['page_id' => $pageId, 'user_id' => $userId])->first();
        if ($existing) {
            $tbl->delete($existing);
            return $this->jsonSuccess(['subscribed' => false]);
        }
        $tbl->save($tbl->newEntity(['page_id' => $pageId, 'user_id' => $userId]));
        return $this->jsonSuccess(['subscribed' => true]);
    }

    public function subscriptionStatus(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->isLoggedIn()) {
            return $this->jsonSuccess(['subscribed' => false]);
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        $subscribed = $this->fetchTable('PageSubscriptions')->find()
            ->where(['page_id' => $pageId, 'user_id' => $this->currentUser()['id'] ?? 0])->count() > 0;
        return $this->jsonSuccess(['subscribed' => $subscribed]);
    }

    // ── Acknowledgements (read confirmation) ──

    public function acknowledge(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableAcknowledgements') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->isLoggedIn()) {
            return $this->jsonError('not_authenticated');
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        if (!$pageId) {
            return $this->jsonError('invalid_id');
        }
        $tbl = $this->fetchTable('PageAcknowledgements');
        $userId = $this->currentUser()['id'] ?? 0;
        $existing = $tbl->find()->where(['page_id' => $pageId, 'user_id' => $userId])->first();
        if (!$existing) {
            $tbl->save($tbl->newEntity(['page_id' => $pageId, 'user_id' => $userId]));
        }
        return $this->jsonSuccess(['acknowledged' => true]);
    }

    public function ackStatus(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        $pageId = (int)$this->request->getData('page_id', 0);
        if (!$pageId) {
            return $this->jsonError('invalid_id');
        }
        $acked = false;
        if ($this->isLoggedIn()) {
            $acked = $this->fetchTable('PageAcknowledgements')->find()
                ->where(['page_id' => $pageId, 'user_id' => $this->currentUser()['id'] ?? 0])->count() > 0;
        }
        $total = $this->fetchTable('PageAcknowledgements')->find()->where(['page_id' => $pageId])->count();
        return $this->jsonSuccess(['acknowledged' => $acked, 'totalAcks' => $total]);
    }

    // ── Inline Comments (paragraph-level) ──

    public function inlineComments(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableInlineComments') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        $items = $this->fetchTable('InlineComments')->find()->contain(['Users'])
            ->where(['page_id' => $pageId])->orderBy(['created' => 'ASC'])->all();
        $list = [];
        foreach ($items as $c) {
            $list[] = ['id' => $c->id, 'anchor' => $c->anchor, 'comment' => $c->comment,
                'user' => $c->user->fullname ?? '', 'resolved' => (bool)$c->resolved,
                'created' => $c->created->format('d.m.Y H:i')];
        }
        return $this->jsonSuccess(['inlineComments' => $list]);
    }

    public function addInlineComment(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableInlineComments') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        }
        $tbl = $this->fetchTable('InlineComments');
        $entity = $tbl->newEntity([
            'page_id' => (int)$this->request->getData('page_id'), 'user_id' => $this->currentUser()['id'] ?? 0,
            'anchor' => $this->request->getData('anchor', '
                '), 'comment' => mb_substr($this->request->getData('comment', ''), 0, 2000),
        ]);
        if ($tbl->save($entity)) {
            return $this->jsonSuccess(['id' => $entity->id]);
        }
        return $this->jsonError('save_failed');
    }

    public function resolveInlineComment(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableInlineComments') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        }
        $id = (int)$this->request->getData('id', 0);
        if (!$id) {
            return $this->jsonError('invalid_id');
        }
        // Only author or contributor+ can resolve
        $comment = $this->fetchTable('InlineComments')->find()->where(['id' => $id])->first();
        if (!$comment) {
            return $this->jsonError('not_found');
        }
        $userId = $this->currentUser()['id'] ?? 0;
        if ($comment->user_id !== $userId && !$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->jsonError('insufficient_permissions');
        }
        $this->fetchTable('InlineComments')->updateAll(['resolved' => 1], ['id' => $id]);
        return $this->jsonSuccess(['resolved' => true]);
    }

    // ── Content Analytics ──

    public function analytics(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_ADMIN)) {
            return $this->response;
        }
        if (!(Configure::read('Manual.enableContentAnalytics') ?? false)) {
            return $this->jsonError('feature_disabled');
        }

        $pages = $this->Pages->find()->where(['deleted_at IS' => null]);
        $topViewed = $this->Pages->find()->where(['deleted_at IS' => null])
            ->orderBy(['views' => 'DESC'])->limit(20)->select(['id', 'title', 'views'])->all()->toArray();
        $leastViewed = $this->Pages->find()->where(['deleted_at IS' => null, 'status' => 'active'])
            ->orderBy(['views' => 'ASC'])->limit(10)->select(['id', 'title', 'views'])->all()->toArray();

        // Pages with bad feedback ratio
        $fb = $this->fetchTable('PageFeedback');
        $badFeedback = $fb->find()->select(['page_id', 'neg' => $fb->find()->func()->count('*')])
            ->where(['rating' => -1])->groupBy('page_id')->orderBy(['neg' => 'DESC'])->limit(10)->all();
        $badPages = [];
        foreach ($badFeedback as $f) {
            $page = $this->Pages->find()->select(['id', 'title'])->where(['id' => $f->page_id])->first();
            if ($page) {
                $badPages[] = ['id' => $page->id, 'title' => $page->title, 'negativeCount' => (int)$f->neg];
            }
        }

        // Frequently updated
        $freq = $this->fetchTable('PageRevisions')->find()
            ->select(['page_id', 'cnt' => $this->fetchTable('PageRevisions')->find()->func()->count('*')])
            ->groupBy('page_id')->orderBy(['cnt' => 'DESC'])->limit(10)->all();
        $freqPages = [];
        foreach ($freq as $f) {
            $page = $this->Pages->find()->select(['id', 'title'])->where(['id' => $f->page_id])->first();
            if ($page) {
                $freqPages[] = ['id' => $page->id, 'title' => $page->title, 'revisionCount' => (int)$f->cnt];
            }
        }

        return $this->jsonSuccess([
            'topViewed' => array_map(
                fn($p) => ['id' => $p->id, 'title' => $p->title, 'views' => $p->views],
                $topViewed
            ),
            'leastViewed' => array_map(
                fn($p) => ['id' => $p->id, 'title' => $p->title, 'views' => $p->views],
                $leastViewed
            ),
            'badFeedback' => $badPages,
            'frequentlyUpdated' => $freqPages,
        ]);
    }

    // ── Import (Markdown/HTML) ──

    public function import(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableImport') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }

        $file = $this->request->getUploadedFile('file');
        $content = $this->request->getData('content', '');
        $format = $this->request->getData('format', 'html');
        $title = $this->request->getData('title', '');

        // From file upload
        if ($file && $file->getError() === UPLOAD_ERR_OK) {
            $content = $file->getStream()->getContents();
            $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
            if ($ext === 'md' || $ext === 'markdown') {
                $format = 'markdown';
            }
            if (empty($title)) {
                $title = pathinfo($file->getClientFilename(), PATHINFO_FILENAME);
            }
        }

        if (empty($content)) {
            return $this->jsonError('no_content');
        }

        // Convert Markdown to HTML
        if ($format === 'markdown') {
            $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
            $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
            $content = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $content);
            $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
            $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
            $content = preg_replace('/`(.+?)`/', '<code>$1</code>', $content);
            $content = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $content);
            $content = preg_replace('/^- (.+)$/m', '<li>$1</li>', $content);
            $content = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $content);
            $content = preg_replace('/\n{2,}/', '</p><p>', $content);
            $content = '<p>' . $content . '</p>';
        }

        $user = $this->currentUser();
        $count = $this->Pages->find()->where(['deleted_at IS' => null])->count();
        $page = $this->Pages->newEntity([
            'title' => $title ?: 'Imported Page', 'content' => $content,
            'description' => '', 'status' => 'inactive', 'workflow_status' => 'draft',
            'views' => 0, 'position' => $count, 'parent_id' => null,
            'created_by' => $user['id'] ?? 0, 'modified_by' => $user['id'] ?? 0,
        ]);
        if ($this->Pages->save($page)) {
            PagesService::invalidateCache();
            $this->audit('page_import', 'page', $page->id, "Imported: {$title} ({$format})");
            return $this->jsonSuccess(['id' => $page->id, 'title' => $title]);
        }
        return $this->jsonError('import_failed');
    }

    // ── Smart Links (autocomplete for internal linking) ──

    public function linkSuggest(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->isLoggedIn()) {
            return $this->jsonError('not_authenticated');
        }
        $q = trim($this->request->getData('q', ''));
        if (strlen($q) < 2) {
            return $this->jsonSuccess(['pages' => []]);
        }
        $pages = $this->Pages->find()->select(['id', 'title'])
            ->where(['title LIKE' => '%' . $q . '%', 'deleted_at IS' => null, 'status' => 'active'])
            ->orderBy(['title' => 'ASC'])->limit(10)->all();
        $list = [];
        foreach ($pages as $p) {
            $list[] = ['id' => $p->id, 'title' => $p->title, 'url' => '/pages/' . $p->id];
        }
        return $this->jsonSuccess(['pages' => $list]);
    }

    // ── Stale Content Work List ──

    public function staleList(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_EDITOR)) {
            return $this->response;
        }
        $months = Configure::read('Manual.staleContentMonths') ?? 12;
        $cutoff = new \Cake\I18n\DateTime("-{$months} months");
        $pages = $this->Pages->find()->where(['deleted_at IS' => null, 'modified <' => $cutoff])
            ->orderBy(['modified' => 'ASC'])->limit(50)->all();
        $list = [];
        foreach ($pages as $p) {
            $list[] = ['id' => $p->id, 'title' => $p->title, 'modified' => $p->modified->format('d.m.Y'),
                'monthsStale' => (int)$p->modified->diffInMonths(new \Cake\I18n\DateTime())];
        }
        return $this->jsonSuccess(['stale' => $list, 'threshold' => $months]);
    }

    // ── Translation Status ──

    public function translationStatus(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableTranslations') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        $locales = Configure::read('Manual.contentLocales') ?? ['en'];
        $defaultLocale = Configure::read('Manual.defaultLocale') ?? 'en';
        $pages = $this->Pages->find()->select(['id', 'title', 'modified'])
            ->where(['deleted_at IS' => null])->orderBy(['position' => 'ASC'])->all();
        $trans = $this->fetchTable('PageTranslations');

        $result = [];
        foreach ($pages as $p) {
            $existing = $trans->find()->where(['page_id' => $p->id])->all()->combine('locale', fn($t) => [
                'modified' => $t->modified ? $t->modified->format('d.m.Y') : '',
                'stale' => $t->base_modified && $t->modified && $t->base_modified > $t->modified,
            ])->toArray();

            $missing = [];
            foreach ($locales as $loc) {
                if ($loc === $defaultLocale) {
                    continue;
                }
                if (!isset($existing[$loc])) {
                    $missing[] = $loc;
                }
            }

            $staleLocales = [];
            foreach ($existing as $loc => $info) {
                if (!empty($info['stale'])) {
                    $staleLocales[] = $loc;
                }
            }

            if (!empty($missing) || !empty($staleLocales)) {
                $result[] = ['id' => $p->id, 'title' => $p->title, 'missing' => $missing, 'stale' => $staleLocales];
            }
        }
        return $this->jsonSuccess(['translationStatus' => $result, 'locales' => $locales]);
    }

    // ── Review Process (assign reviewer, approve/reject with comment) ──

    public function assignReviewer(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableReviewProcess') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        $reviewerId = (int)$this->request->getData('reviewer_id', 0);
        if (!$pageId || !$reviewerId) {
            return $this->jsonError('invalid_data');
        }

        $tbl = $this->fetchTable('PageReviews');
        $review = $tbl->newEntity(['page_id' => $pageId, 'reviewer_id' => $reviewerId, 'status' => 'pending',
            'comment' => '']);
        if ($tbl->save($review)) {
            $this->Pages->updateAll(['workflow_status' => 'review'], ['id' => $pageId]);
            $reviewer = $this->fetchTable('Users')->find()->select(['fullname',
                'email'])->where(['id' => $reviewerId])->first();
            $page = $this->Pages->find()->select(['title'])->where(['id' => $pageId])->first();
            $this->sendNotification(
                "Review requested: {$page->title}",
                "You have been assigned to review '{$page->title}'."
            );
            $this->audit('review_assign', 'page', $pageId, "Reviewer: " . ($reviewer->fullname ?? ''));
            PagesService::invalidateCache();
            return $this->jsonSuccess(['review_id' => $review->id]);
        }
        return $this->jsonError('save_failed');
    }

    public function reviewDecision(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableReviewProcess') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->requireRole(self::ROLE_CONTRIBUTOR)) {
            return $this->response;
        }
        $reviewId = (int)$this->request->getData('review_id', 0);
        $decision = $this->request->getData('decision', '');
        $comment = $this->request->getData('comment', '');
        if (!$reviewId || !in_array($decision, ['approved', 'rejected', 'revision_needed'])) {
            return $this->jsonError('invalid_data');
        }
        if ($decision === 'rejected' && empty(trim($comment))) {
            return $this->jsonError('comment_required');
        }

        $tbl = $this->fetchTable('PageReviews');
        $review = $tbl->find()->where(['id' => $reviewId])->first();
        if (!$review) {
            return $this->jsonError('not_found');
        }

        // Only the assigned reviewer or an admin can decide
        $userId = $this->currentUser()['id'] ?? 0;
        if ($review->reviewer_id !== $userId && !$this->hasRole(self::ROLE_ADMIN)) {
            return $this->jsonError('insufficient_permissions');
        }

        // Validate state transition: only pending reviews can be decided
        if ($review->status !== 'pending') {
            return $this->jsonError('review_already_decided');
        }
        $tbl->updateAll(['status' => $decision, 'comment' => mb_substr($comment, 0, 2000)], ['id' => $reviewId]);

        $review = $tbl->get($reviewId);
        $ws = $decision === 'approved' ? 'published' : ($decision === 'rejected' ? 'draft' : 'review');
        $this->Pages->updateAll(['workflow_status' => $ws], ['id' => $review->page_id]);
        if ($decision === 'approved') {
            $this->Pages->updateAll(['status' => 'active'], ['id' => $review->page_id]);
        }

        $this->audit('review_decision', 'page', $review->page_id, "{$decision}: {$comment}");
        PagesService::invalidateCache();
        \App\Service\WebhookService::fire('page.reviewed', ['page_id' => $review->page_id, 'decision' => $decision]);
        return $this->jsonSuccess(['status' => $decision]);
    }

    public function pageReviews(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!$this->requireRole(self::ROLE_EDITOR)) {
            return $this->response;
        }
        $pageId = (int)$this->request->getData('page_id', 0);
        $reviews = $this->fetchTable('PageReviews')->find()->contain(['Users'])
            ->where(['page_id' => $pageId])->orderBy(['created' => 'DESC'])->limit(20)->all();
        $list = [];
        foreach ($reviews as $r) {
            $list[] = ['id' => $r->id, 'reviewer' => $r->user->fullname ?? '', 'status' => $r->status,
                'comment' => $r->comment, 'created' => $r->created->format('d.m.Y H:i')];
        }
        return $this->jsonSuccess(['reviews' => $list]);
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function notifySubscribers(int $pageId, string $message): void
    {
        try {
            $subs = $this->fetchTable('PageSubscriptions')->find()->contain(['Users'])
                ->where(['page_id' => $pageId])->all();
            $userId = $this->currentUser()['id'] ?? 0;
            foreach ($subs as $s) {
                if ($s->user_id === $userId) {
                    continue; // Don't notify self
                }
                if (!empty($s->user->email)) {
                    $this->sendNotification('Page update notification', $message);
                }
            }
        } catch (\Exception $e) {
            Log::error('Subscriber notification failed: ' . $e->getMessage());
        }
    }

    private function saveKeywords(int $pageId, string $keywords): void
    {
        $tbl = $this->fetchTable('Pagesindex');
        $tbl->deleteAll(['page_id' => $pageId]);
        if (empty(trim($keywords))) {
            return;
        }
        foreach (array_map('trim', explode(',', $keywords)) as $kw) {
            if (!empty($kw)) {
                $tbl->save($tbl->newEntity(['keyword' => $kw, 'page_id' => $pageId]));
            }
        }
    }

    private function applyTranslation($page, string $locale): object
    {
        $defaultLocale = Configure::read('Manual.defaultLocale') ?? 'en';
        if ($locale === $defaultLocale || !(Configure::read('Manual.enableTranslations') ?? false)) {
            return $page;
        }
        try {
            $trans = $this->fetchTable('PageTranslations')->find()
                ->where(['page_id' => $page->id, 'locale' => $locale])->first();
            if ($trans) {
                $page->title = $trans->title ?: $page->title;
                $page->description = $trans->description ?: $page->description;
                $page->content = $trans->content ?: $page->content;
            }
        } catch (\Exception $e) {
            Log::error('Translation load failed: ' . $e->getMessage());
        }
        return $page;
    }
}
