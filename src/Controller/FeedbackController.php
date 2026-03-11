<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * Feedback Controller — page ratings and comments.
 *
 * Extracted from PagesController. Handles public feedback submission
 * (thumbs up/down + optional comment) and admin moderation.
 */
class FeedbackController extends AppController
{
    /**
     * Submit feedback (public endpoint, no auth required).
     * Rate-limited to 1 per IP per page per hour.
     */
    public function submit(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableFeedback') ?? false)) {
            return $this->jsonError('feature_disabled');
        }

        $pageId = (int)$this->request->getData('page_id', 0);
        $rating = (int)$this->request->getData('rating', 0);
        $comment = trim($this->request->getData('comment', ''));
        if (!$pageId || !in_array($rating, [-1, 1])) {
            return $this->jsonError('invalid_feedback');
        }

        $clientIp = $this->request->clientIp();
        $fb = $this->fetchTable('PageFeedback');

        // Rate limit
        $recent = $fb->find()->where([
            'page_id' => $pageId, 'client_ip' => $clientIp,
            'created >=' => new \Cake\I18n\DateTime('-1 hour'),
        ])->count();
        if ($recent > 0) {
            return $this->jsonError('feedback_rate_limited');
        }

        $entity = $fb->newEntity([
            'page_id' => $pageId, 'rating' => $rating,
            'comment' => mb_substr($comment, 0, 2000),
            'client_ip' => $clientIp,
            'status' => empty($comment) ? 'approved' : 'pending',
        ]);

        if ($fb->save($entity)) {
            if (!empty($comment)) {
                $this->sendNotification(
                    'New feedback',
                    "Feedback on page #{$pageId}: " . ($rating > 0 ? 'thumbs up' : 'thumbs down') . "\n\n{$comment}"
                );
            }
            return $this->jsonSuccess(['success' => true]);
        }
        return $this->jsonError('feedback_save_failed');
    }

    /**
     * Moderate feedback: approve or reject (admin only).
     */
    public function moderate(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableFeedback') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            return $this->requireRole(self::ROLE_ADMIN) ? null : $this->response;
        }

        $feedbackId = (int)$this->request->getData('id', 0);
        $action = $this->request->getData('action', '');
        if (!$feedbackId || !in_array($action, ['approve', 'reject'])) {
            return $this->jsonError('invalid_action');
        }

        $this->fetchTable('PageFeedback')->updateAll(
            ['status' => $action === 'approve' ? 'approved' : 'rejected'],
            ['id' => $feedbackId]
        );
        return $this->jsonSuccess(['intAffectedRows' => 1]);
    }

    /**
     * List pending feedback for moderation (admin only).
     */
    public function pending(): ?\Cake\Http\Response
    {
        $this->autoRender = false;
        if (!(Configure::read('Manual.enableFeedback') ?? false)) {
            return $this->jsonError('feature_disabled');
        }
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            return $this->requireRole(self::ROLE_ADMIN) ? null : $this->response;
        }

        $items = $this->fetchTable('PageFeedback')->find()
            ->contain(['Pages'])
            ->where(['PageFeedback.status' => 'pending'])
            ->orderBy(['PageFeedback.created' => 'DESC'])->limit(100)->all();

        $list = [];
        foreach ($items as $f) {
            $list[] = [
                'id' => $f->id, 'page_id' => $f->page_id,
                'page_title' => $f->page->title ?? '',
                'rating' => $f->rating, 'comment' => $f->comment,
                'created' => $f->created->format('d.m.Y H:i'),
            ];
        }
        return $this->jsonSuccess(['feedback' => $list]);
    }
}
