<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PagesService;
use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Revisions Controller — page version history.
 *
 * Extracted from PagesController. Handles listing, viewing, and restoring
 * historical page snapshots stored in the page_revisions table.
 */
class RevisionsController extends AppController
{
    /**
     * List revisions for a page.
     */
    public function index(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableRevisions') ?? true)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        }
        $id = (int)$this->request->getData('id', 0);
        if (!$id) {
            return $this->jsonError('invalid_id');
        }

        $revisions = $this->fetchTable('PageRevisions')->find()
            ->contain(['CreatedByUsers'])->where(['page_id' => $id])
            ->orderBy(['PageRevisions.created' => 'DESC'])->limit(50)->all();

        $list = [];
        foreach ($revisions as $r) {
            $list[] = [
                'id' => $r->id, 'created' => $r->created->format('d.m.Y H:i'),
                'createdBy' => $r->creator->fullname ?? '', 'note' => $r->revision_note ?? '',
                'titlePreview' => mb_substr($r->title, 0, 60),
            ];
        }
        return $this->jsonSuccess(['revisions' => $list]);
    }

    /**
     * Load a specific revision for preview.
     */
    public function show(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableRevisions') ?? true)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_EDITOR)) {
            return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        }
        $revId = (int)$this->request->getData('revision_id', 0);
        if (!$revId) {
            return $this->jsonError('invalid_id');
        }
        try {
            $rev = $this->fetchTable('PageRevisions')->get($revId, contain: ['CreatedByUsers']);
            return $this->jsonSuccess([
                'id' => $rev->id, 'page_id' => $rev->page_id,
                'title' => $rev->title, 'description' => $rev->description,
                'content' => PagesService::sanitizeHtml($rev->content ?? ''),
                'created' => $rev->created->format('d.m.Y H:i'),
                'createdBy' => $rev->creator->fullname ?? '',
                'note' => $rev->revision_note ?? '',
            ]);
        } catch (\Exception $e) {
            Log::error('Revision show failed: ' . $e->getMessage());
            return $this->jsonError('revision_not_found');
        }
    }

    /**
     * Restore a revision — saves current content as backup first.
     */
    public function restore(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableRevisions') ?? true)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_CONTRIBUTOR)) {
            return $this->requireRole(self::ROLE_CONTRIBUTOR) ? null : $this->response;
        }
        $revId = (int)$this->request->getData('revision_id', 0);
        if (!$revId) {
            return $this->jsonError('invalid_id');
        }
        try {
            $revTable = $this->fetchTable('PageRevisions');
            $rev = $revTable->get($revId);
            $pages = $this->fetchTable('Pages');
            $page = $pages->get($rev->page_id);
            $user = $this->currentUser();

            // Backup current content before restore
            $revTable->save($revTable->newEntity([
                'page_id' => $page->id, 'title' => $page->title,
                'description' => $page->description, 'content' => $page->content,
                'created_by' => $user['id'] ?? 0, 'revision_note' => 'Auto-saved before restore',
            ]));

            $page = $pages->patchEntity($page, [
                'title' => $rev->title, 'description' => $rev->description,
                'content' => $rev->content, 'modified_by' => $user['id'] ?? 0,
            ]);
            if ($pages->save($page)) {
                return $this->jsonSuccess(['intAffectedRows' => 1]);
            }
        } catch (\Exception $e) {
            Log::error('Revision restore failed: ' . $e->getMessage());
        }
        return $this->jsonError('can_not_restore');
    }
}
