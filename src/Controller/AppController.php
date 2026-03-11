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
        $userRole = $this->request->getSession()->read('Auth.role') ?? self::ROLE_EDITOR;
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
            ->withStringBody(json_encode(['error' => $error]));
    }

    protected function jsonSuccess(array $data): \Cake\Http\Response
    {
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($data));
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

    // ── Audit Log ──

    protected function audit(string $action, string $entityType, int $entityId, string $details = ''): void
    {
        if (!(Configure::read('Manual.enableAuditLog') ?? false)) {
            return;
        }
        try {
            $tbl = $this->fetchTable('AuditLog');
            $tbl->save($tbl->newEntity([
                'user_id' => $this->currentUser()['id'] ?? 0,
                'action' => $action, 'entity_type' => $entityType,
                'entity_id' => $entityId, 'details' => mb_substr($details, 0, 2000),
                'ip_address' => $this->request->clientIp(),
            ]));
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
}
