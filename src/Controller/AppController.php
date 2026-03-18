<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Log\Log;

/**
 * Application Base Controller
 *
 * ## Role Hierarchy (guest < editor < contributor < admin)
 *
 * | Role        | Pages Read | Pages Edit | Tree Reorder | Users | Files | Feedback Moderate |
 * |-------------|-----------|------------|--------------|-------|-------|-------------------|
 * | guest       | active    | ✗          | ✗            | ✗     | ✗     | ✗                 |
 * | editor      | all       | ✓          | ✗            | ✗     | ✗     | ✗                 |
 * | contributor | all       | ✓          | ✓            | ✗     | ✓     | ✗                 |
 * | admin       | all       | ✓          | ✓            | ✓     | ✓     | ✓                 |
 */
class AppController extends Controller
{
    // ── Role Constants ──
    public const ROLE_GUEST = 'guest';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_CONTRIBUTOR = 'contributor';
    public const ROLE_ADMIN = 'admin';

    /** Roles ordered by ascending privilege level. */
    public const ROLES = [self::ROLE_GUEST, self::ROLE_EDITOR, self::ROLE_CONTRIBUTOR, self::ROLE_ADMIN];

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Flash');
    }

    /**
     * Inject auth, config, and CSRF token into all views.
     *
     * Config is read from `Manual` key in app.php. If the key is missing entirely,
     * the application will fail fast rather than silently using wrong defaults.
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        // Prevent browser from caching authenticated pages (fixes stale UI after logout)
        $this->response = $this->response
            ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');

        $this->set('auth', $this->request->getSession()->read('Auth') ?? []);
        $this->set('public', Configure::read('Manual') ?? []);

        $token = $this->request->getAttribute('csrfToken');
        if ($token) {
            $this->set('csrfToken', $token);
        }
    }

    // ── Auth Helpers ──

    protected function isLoggedIn(): bool
    {
        return !empty($this->request->getSession()->read('Auth.id'));
    }

    /**
     * Check if current user has at least the given role level.
     */
    protected function hasRole(string $minRole): bool
    {
        if (!$this->isLoggedIn()) {
            return $minRole === self::ROLE_GUEST;
        }
        $userRole = $this->request->getSession()->read('Auth.role') ?? self::ROLE_GUEST;
        $userLevel = array_search($userRole, self::ROLES);
        $minLevel = array_search($minRole, self::ROLES);
        if ($userLevel === false || $minLevel === false) {
            return false;
        }
        return $userLevel >= $minLevel;
    }

    protected function currentUser(): array
    {
        return $this->request->getSession()->read('Auth') ?? [];
    }

    // ── JSON Responses ──

    protected function jsonError(string $error): \Cake\Http\Response
    {
        return $this->response->withType('application/json')
            ->withStringBody(json_encode(['error' => $error], JSON_HEX_TAG | JSON_HEX_AMP));
    }

    protected function jsonSuccess(array $data): \Cake\Http\Response
    {
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP));
    }

    // ── Auth Guards ──

    /**
     * Require minimum role. Returns JSON error 'not_authenticated' for guests
     * or 'insufficient_permissions' for logged-in users with insufficient role.
     */
    protected function requireRole(string $role): bool
    {
        if ($this->hasRole($role)) {
            return true;
        }
        if (!$this->isLoggedIn()) {
            if ($this->request->is('ajax')) {
                $this->response = $this->jsonError('not_authenticated');
                return false;
            }
            $this->redirect('/user/login');
            return false;
        }
        // Logged in but insufficient role
        if ($this->request->is('ajax')) {
            $this->response = $this->jsonError('insufficient_permissions');
            return false;
        }
        $this->Flash->error(__('You do not have permission for this action.'));
        $this->redirect('/');
        return false;
    }

    // ── Rate-Limit Safe IP ──

    /**
     * Get client IP for rate limiting. Only trusts proxy headers (X-Forwarded-For)
     * when the request comes from a configured trusted proxy IP.
     * Configure via Manual.trustedProxies (array of IPs) in app.php.
     */
    protected function rateLimitIp(): string
    {
        $remoteAddr = $this->request->getEnv('REMOTE_ADDR') ?? '0.0.0.0';
        $trustedProxies = Configure::read('Manual.trustedProxies') ?? [];
        if (!empty($trustedProxies) && in_array($remoteAddr, $trustedProxies, true)) {
            return $this->request->clientIp();
        }
        return $remoteAddr;
    }

    // ── Audit Log ──

    protected function audit(string $action, string $entityType, int $entityId, string $details = ''): void
    {
        if (!(Configure::read('Manual.enableAuditLog') ?? false)) {
            return;
        }
        try {
            $tbl = $this->fetchTable('AuditLog');
            $entry = $tbl->newEmptyEntity();
            $entry->set('user_id', $this->currentUser()['id'] ?? 0);
            $entry->set('action', $action);
            $entry->set('entity_type', $entityType);
            $entry->set('entity_id', $entityId);
            $entry->set('details', mb_substr($details, 0, 2000));
            $entry->set('ip_address', $this->rateLimitIp());
            $tbl->save($entry);
        } catch (\Exception $e) {
            Log::error('Audit log failed: ' . $e->getMessage());
        }
    }


    // ── Notifications ──

    protected function sendNotification(string $subject, string $body): void
    {
        $email = Configure::read('Manual.notifyEmail');
        if (empty($email)) {
            return;
        }
        try {
            $mailer = new \Cake\Mailer\Mailer();
            $mailer->setTo($email)->setSubject('[Manual] ' . $subject)->deliver($body);
        } catch (\Exception $e) {
            Log::error('Notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Send a notification to a specific user email (for mentions, subscriptions, review assignments).
     */
    protected function sendUserNotification(string $toEmail, string $subject, string $body): void
    {
        if (empty($toEmail)) {
            return;
        }
        try {
            $mailer = new \Cake\Mailer\Mailer();
            $mailer->setTo($toEmail)->setSubject('[Manual] ' . $subject)->deliver($body);
        } catch (\Exception $e) {
            Log::error('User notification failed: ' . $e->getMessage());
        }
    }
}
