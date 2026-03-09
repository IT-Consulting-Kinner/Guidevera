<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Comments Controller — internal page comments for editors/contributors.
 */
class CommentsController extends AppController
{
    public function index(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableComments') ?? false)) return $this->jsonError('feature_disabled');
        if (!$this->hasRole(self::ROLE_EDITOR)) return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;
        $pageId = (int)$this->request->getData('page_id', 0);
        if (!$pageId) return $this->jsonError('invalid_id');

        $comments = $this->fetchTable('PageComments')->find()
            ->contain(['Users'])->where(['page_id' => $pageId])
            ->orderBy(['PageComments.created' => 'ASC'])->limit(200)->all();

        $list = [];
        foreach ($comments as $c) {
            $list[] = [
                'id' => $c->id, 'comment' => $c->comment,
                'user' => $c->user->fullname ?? '', 'userId' => $c->user_id,
                'created' => $c->created->format('d.m.Y H:i'),
            ];
        }
        return $this->jsonSuccess(['comments' => $list]);
    }

    public function add(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']); $this->autoRender = false;
        if (!(Configure::read('Manual.enableComments') ?? false)) return $this->jsonError('feature_disabled');
        if (!$this->hasRole(self::ROLE_EDITOR)) return $this->requireRole(self::ROLE_EDITOR) ? null : $this->response;

        $pageId = (int)$this->request->getData('page_id', 0);
        $comment = trim($this->request->getData('comment', ''));
        if (!$pageId || empty($comment)) return $this->jsonError('invalid_data');

        $user = $this->currentUser();
        $tbl = $this->fetchTable('PageComments');
        $entity = $tbl->newEntity([
            'page_id' => $pageId, 'user_id' => $user['id'] ?? 0,
            'comment' => mb_substr($comment, 0, 5000),
        ]);

        if ($tbl->save($entity)) {
            $this->audit('comment_add', 'page', $pageId, mb_substr($comment, 0, 200));

            // Process @mentions
            if (Configure::read('Manual.enableMentions') ?? false) {
                $this->_processMentions($comment, $pageId, $user);
            }

            return $this->jsonSuccess([
                'id' => $entity->id, 'comment' => $entity->comment,
                'user' => $user['fullname'] ?? '', 'userId' => $user['id'] ?? 0,
                'created' => $entity->created->format('d.m.Y H:i'),
            ]);
        }
        return $this->jsonError('save_failed');
    }

    public function delete(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']); $this->autoRender = false;
        if (!(Configure::read('Manual.enableComments') ?? false)) return $this->jsonError('feature_disabled');
        $id = (int)$this->request->getData('id', 0);
        if (!$id) return $this->jsonError('invalid_id');

        $tbl = $this->fetchTable('PageComments');
        try {
            $comment = $tbl->get($id);
            $user = $this->currentUser();
            // Only author or admin can delete
            if (($comment->user_id !== ($user['id'] ?? 0)) && !$this->hasRole(self::ROLE_ADMIN)) {
                return $this->jsonError('insufficient_permissions');
            }
            $tbl->delete($comment);
            $this->audit('comment_delete', 'page', $comment->page_id, "Comment #{$id}");
            return $this->jsonSuccess(['success' => true]);
        } catch (\Exception $e) { Log::error('Comment delete: ' . $e->getMessage()); }
        return $this->jsonError('delete_failed');
    }

    /**
     * Find @username mentions in comment text and notify mentioned users.
     */
    private function _processMentions(string $text, int $pageId, array $author): void
    {
        preg_match_all('/@(\w+)/', $text, $matches);
        if (empty($matches[1])) return;

        $users = $this->fetchTable('Users')->find()
            ->where(['username IN' => array_unique($matches[1]), 'status' => 'active', 'notify_mentions' => 1])
            ->all();

        $page = $this->fetchTable('Pages')->find()->select(['title'])->where(['id' => $pageId])->first();
        $pageTitle = $page->title ?? "Page #{$pageId}";

        foreach ($users as $u) {
            if ($u->id === ($author['id'] ?? 0)) continue; // Don't notify self
            $this->sendNotification(
                "Mention on '{$pageTitle}'",
                ($author['fullname'] ?? 'Someone') . " mentioned you in a comment on '{$pageTitle}':\n\n{$text}"
            );
        }
    }
}
