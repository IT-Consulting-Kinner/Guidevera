<?php

/**
 * CakePHP Manual Application
 */

declare(strict_types=1);

namespace App\Controller;

/**
 * Users Controller
 *
 * Manages user authentication, accounts, and profile management.
 *
 * ## Authentication
 *
 * Uses HMAC-SHA256 + bcrypt password hashing (two-layer defense).
 * Passwords are first hashed with HMAC-SHA256 using Security.salt,
 * then bcrypt-hashed.
 *
 * ## Rate Limiting
 *
 * Login attempts are rate-limited per IP using filesystem-based
 * counters in storage/ratelimit/. After 5 failed attempts within
 * 5 minutes, the IP is temporarily blocked.
 *
 * ## Initial Setup
 *
 * When no users exist, the login page shows a setup instruction
 * directing the administrator to run `bin/cake install`, which
 * creates the initial admin account in the CLI.
 *
 * @package App\Controller
 */

use Cake\Log\Log;

class UsersController extends AppController
{
    protected \App\Model\Table\UsersTable $Users;

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300;

    public function initialize(): void
    {
        parent::initialize();
        $this->Users = $this->fetchTable('Users');
    }

    public function index(): void
    {
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            $this->redirect('/user/login');
            return;
        }
        $users = $this->Users->find()->where(['status !=' => 'deleted'])->orderBy(['fullname' => 'ASC'])->all();
        $this->set(compact('users'));
    }

    public function login(): ?\Cake\Http\Response
    {
        if ($this->isLoggedIn()) {
            // Verify session user still exists in DB
            $sessionUser = $this->currentUser();
            $dbUser = $this->Users->find()->where(['id' => $sessionUser['id'] ?? 0, 'status' => 'active'])->first();
            if (!$dbUser) {
                $this->request->getSession()->destroy();
            } else {
                return $this->redirect('/');
            }
        }

        $userCount = $this->Users->find()->count();
        if ($userCount === 0) {
            // No users exist — show setup instructions instead of auto-creating
            $this->set('needs_setup', true);
            $this->Flash->error(__('No users found. Run "bin/cake install" to create the initial admin account.'));
            return null;
        }

        if ($this->request->is('post')) {
            $username = $this->request->getData('username', '');
            $password = $this->request->getData('password', '');
            $pageId = (int)$this->request->getData('page_id', 0);
            $clientIp = $this->rateLimitIp();
            $rlKey = 'login_' . md5($clientIp);

            if ($this->isRateLimited($rlKey)) {
                $this->Flash->error(__('Too many failed login attempts. Please try again in a few minutes.'));
                $this->set('page_id', $pageId);
                return null;
            }

            $user = $this->Users->find()->where(['username' => $username, 'status' => 'active'])->first();
            if ($user && $this->verifyPassword($password, $user->password)) {
                $this->clearRateLimit($rlKey);
                $session = $this->request->getSession();
                $session->renew();
                $session->write('Auth', [
                    'id' => $user->id, 'gender' => $user->gender, 'username' => $user->username,
                    'fullname' => $user->fullname, 'email' => $user->email, 'role' => $user->role,
                    'page_tree' => $user->page_tree, 'status' => $user->status,
                    'change_password' => $user->change_password,
                ]);
                if ($user->change_password) {
                    return $this->redirect('/user/change-password');
                }
                return $this->redirect($pageId ? '/pages/' . $pageId : '/pages/dashboard');
            }
            if (!empty($username)) {
                $this->recordFailedAttempt($rlKey);
            }
            $this->Flash->error(__('You could not be logged in. Please check your details and try again.'));
            $this->set('page_id', $pageId);
        }
        return null;
    }

    public function logout(): ?\Cake\Http\Response
    {
        $this->request->getSession()->destroy();
        return $this->redirect('/');
    }

    /**
     * Admin re-login: destroy current session and redirect to login page.
     * Port of UserController::relogin() - allows admin to switch user context.
     */
    public function relogin(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->request->getSession()->destroy();
        return $this->redirect('/user/login');
    }

    public function profil(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/user/login');
            return;
        }
        $userId = $this->request->getSession()->read('Auth.id');
        $user = $this->Users->get($userId);

        if ($this->request->is('post')) {
            $subaction = $this->request->getData('subaction', 'change_user');
            if ($subaction === 'change_password') {
                $oldPw = $this->request->getData('current_password', '');
                $pw = $this->request->getData('password', '');
                $pv = $this->request->getData('passwordverify', '');
                if (empty($oldPw) || empty($pw)) {
                    $this->Flash->error(__('Please fill in all fields.'));
                    $this->set(compact('user'));
                    return;
                }
                if (!$this->verifyPassword($oldPw, $user->password)) {
                    $this->Flash->error(__('Current password is incorrect.'));
                    $this->set(compact('user'));
                    return;
                }
                if ($pw !== $pv) {
                    $this->Flash->error(__('Password and password confirmation must match.'));
                    $this->set(compact('user'));
                    return;
                }
                if ($oldPw === $pw) {
                    $this->Flash->error(__('New password must differ from current password.'));
                    $this->set(compact('user'));
                    return;
                }
                $pwError = $this->validatePasswordStrength($pw);
                if ($pwError) {
                    $this->Flash->error($pwError);
                    $this->set(compact('user'));
                    return;
                }
                $user->set('password', $this->hashPassword($pw));
                if ($this->Users->save($user)) {
                    $this->Flash->success(__('Your password has been changed.'));
                } else {
                    $this->Flash->error(__('Error saving change!'));
                }
            } else {
                $data = $this->request->getData();
                // Checkbox: if not in POST data, default to 0
                if (!isset($data['notify_mentions'])) {
                    $data['notify_mentions'] = 0;
                }
                $user = $this->Users->patchEntity($user, $data, ['fields' => ['fullname', 'email', 'gender',
                    'notify_mentions']]);
                $dup = $this->Users->find()->where([
                    'email' => $user->email,
                    'id !=' => $userId,
                    'status !=' => 'deleted',
                ])->first();
                if ($dup) {
                    $this->Flash->error(__('A user with this username or email already exists.'));
                    $this->set(compact('user'));
                    return;
                }
                if ($this->Users->save($user)) {
                    $s = $this->request->getSession();
                    $a = $s->read('Auth');
                    $a['fullname'] = $user->fullname;
                    $a['email'] = $user->email;
                    $a['gender'] = $user->gender;
                    $s->write('Auth', $a);
                    // Update view variable so current page shows new name
                    $this->set('auth', $a);
                    $this->Flash->success(__('Your user data has been updated.'));
                } else {
                    $this->Flash->error(__('Error saving change!'));
                }
            }
        }
        $this->set(compact('user'));
    }

    public function create(): ?\Cake\Http\Response
    {
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            return $this->redirect('/user/login');
        }
        $user = $this->Users->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $pw = $data['password'] ?? '';
            $pv = $data['passwordverify'] ?? '';
            if ($pw !== $pv) {
                $this->Flash->error(__('Password and password confirmation must match.'));
                $this->set(compact('user'));
                return null;
            }
            $pwError = $this->validatePasswordStrength($pw);
            if ($pwError) {
                $this->Flash->error($pwError);
                $this->set(compact('user'));
                return null;
            }
            $data['password'] = $this->hashPassword($pw);
            $user = $this->Users->newEntity($data, [
                'fields' => ['username', 'fullname', 'email', 'gender',
                    'page_tree', 'preferences', 'notify_mentions'],
            ]);
            $user->set('password', $data['password']);
            $user->set('change_password', 1);
            $role = $data['role'] ?? 'contributor';
            if (!in_array($role, ['admin', 'contributor', 'editor'], true)) {
                $role = 'contributor';
            }
            $status = $data['status'] ?? 'active';
            if (!in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }
            $user->set('role', $role);
            $user->set('status', $status);
            $dup = $this->Users->find()->where([
                'OR' => ['username' => $data['username'] ?? '', 'email' => $data['email'] ?? ''],
                'status !=' => 'deleted',
            ])->first();
            if ($dup) {
                $this->Flash->error(__('A user with this username or email already exists.'));
                $this->set(compact('user'));
                return null;
            }
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The change has been saved.'));
                return $this->redirect('/user');
            }
            $this->Flash->error(__('The user could not be created!'));
        }
        $this->set(compact('user'));
        return null;
    }

    public function changePassword(): ?\Cake\Http\Response
    {
        if (!$this->isLoggedIn()) {
            return $this->redirect('/user/login');
        }
        if ($this->request->is('post')) {
            $userId = $this->request->getSession()->read('Auth.id');
            $user = $this->Users->get($userId);
            $old = $this->request->getData('oldpassword', '');
            $new = $this->request->getData('newpassword', '');
            $confirm = $this->request->getData('newpasswordverify', '');
            if (empty($old) || empty($new) || empty($confirm)) {
                $this->Flash->error(__('Please fill in all fields.'));
                return null;
            }
            if ($new !== $confirm) {
                $this->Flash->error(__('Password and password confirmation must match.'));
                return null;
            }
            if ($old === $new) {
                $this->Flash->error(__('Please check all fields.'));
                return null;
            }
            if (!$this->verifyPassword($old, $user->password)) {
                $this->Flash->error(__('Please check all fields.'));
                return null;
            }
            $pwError = $this->validatePasswordStrength($new);
            if ($pwError) {
                $this->Flash->error($pwError);
                return null;
            }
            $user->set('password', $this->hashPassword($new));
            $user->set('change_password', 0);
            if ($this->Users->save($user)) {
                $s = $this->request->getSession();
                $a = $s->read('Auth');
                $a['change_password'] = 0;
                $s->write('Auth', $a);
                $this->Flash->success(__('Your password has been changed.'));
                return $this->redirect('/pages/dashboard');
            } else {
                $this->Flash->error(__('Error saving change!'));
            }
        }
        return null;
    }

    public function save(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            return $this->jsonError('not_authenticated');
        }
        $id = (int)$this->request->getData('id');
        $field = $this->request->getData('field', '');
        $value = $this->request->getData('value', '');
        $allowed = ['username', 'password', 'gender', 'fullname', 'email', 'role', 'status'];
        if (!in_array($field, $allowed, true)) {
            return $this->jsonError('can_not_update_field');
        }
        // Validate field values
        $validRoles = ['admin', 'contributor', 'editor'];
        $validStatuses = ['active', 'inactive', 'deleted'];
        $validGenders = ['male', 'female', ''];
        if ($field === 'role' && !in_array($value, $validRoles, true)) {
            return $this->jsonError('invalid_role');
        }
        if ($field === 'status' && !in_array($value, $validStatuses, true)) {
            return $this->jsonError('invalid_status');
        }
        if ($field === 'gender' && !in_array($value, $validGenders, true)) {
            return $this->jsonError('invalid_gender');
        }
        if ($field === 'username' && (empty($value) || mb_strlen($value) > 20)) {
            return $this->jsonError('invalid_username');
        }
        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonError('invalid_email');
        }
        // Prevent admin from changing own role
        $currentUserId = $this->currentUser()['id'] ?? 0;
        if ($id === $currentUserId && in_array($field, ['role', 'status'], true)) {
            return $this->jsonError('cannot_change_own_' . $field);
        }
        try {
            $user = $this->Users->get($id);
            $chPw = false;
            if ($field === 'password') {
                $pwError = $this->validatePasswordStrength($value);
                if ($pwError) {
                    return $this->jsonError('weak_password');
                }
                if ($this->currentUser()['id'] !== $id) {
                    $chPw = true;
                }
                $value = $this->hashPassword($value);
            }
            $user->set($field, $value);
            if ($chPw) {
                $user->change_password = 1;
            }
            if ($this->Users->save($user)) {
                if ($this->currentUser()['id'] === $id && $field !== 'password') {
                    $s = $this->request->getSession();
                    $a = $s->read('Auth');
                    $a[$field] = $value;
                    $s->write('Auth', $a);
                }
                return $this->jsonSuccess(['intAffectedRows' => 1, 'gender' => $user->gender,
                    'fullname' => $user->fullname]);
            }
        } catch (\Exception $e) {
            Log::error('User save failed: ' . $e->getMessage());
        }
        return $this->jsonError('can_not_update_field');
    }

    public function savePageTree(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->isLoggedIn()) {
            return $this->jsonError('not_authenticated');
        }
        $userId = $this->request->getSession()->read('Auth.id');
        $elements = $this->request->getData('strElements', '');
        if (strlen($elements) > 10000) {
            return $this->jsonError('input_too_large');
        }
        // Safe parsing without parse_str (prevents memory exhaustion from nested keys)
        $pairs = explode('&', $elements);
        if (count($pairs) > 500) {
            return $this->jsonError('input_too_large');
        }
        $parsed = [];
        foreach ($pairs as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) !== 2) {
                continue;
            }
            $key = urldecode($kv[0]);
            $val = urldecode($kv[1]);
            // Only allow simple keys like "item[]" or "item[123]" — no deep nesting
            if (preg_match('/^([a-zA-Z_]\w*)\[(\d*)\]$/', $key, $m)) {
                $parsed[$m[1]][] = $val;
            } elseif (preg_match('/^[a-zA-Z_]\w*$/', $key)) {
                $parsed[$key] = $val;
            }
            // Silently skip keys with deep nesting like a[b][c][d]
        }
        $decoded = $parsed;
        $user = $this->Users->get($userId);
        $user->page_tree = json_encode($decoded);
        if ($this->Users->save($user)) {
            $s = $this->request->getSession();
            $a = $s->read('Auth');
            $a['page_tree'] = $user->page_tree;
            $s->write('Auth', $a);
            return $this->jsonSuccess(['intAffectedRows' => 1]);
        }
        return $this->jsonError('can_not_save');
    }

    public function searchUsers(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->isLoggedIn()) {
            return $this->jsonError('not_authenticated');
        }
        $q = trim($this->request->getData('q', ''));
        $q = str_replace(['%', '_'], ['\\%', '\\_'], $q);
        $conditions = ['status' => 'active'];
        if (strlen($q) >= 1) {
            $conditions['OR'] = ['username LIKE' => '%' . $q . '%', 'fullname LIKE' => '%' . $q . '%'];
        }
        $users = $this->Users->find()
            ->select(['id', 'username', 'fullname'])
            ->where($conditions)->limit(10)->all();
        $list = [];
        foreach ($users as $u) {
            $list[] = ['id' => $u->id, 'username' => $u->username, 'fullname' => $u->fullname];
        }
        return $this->jsonSuccess(['users' => $list]);
    }

    public function deleteUser(): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->autoRender = false;
        if (!$this->hasRole(self::ROLE_ADMIN)) {
            return $this->jsonError('not_authenticated');
        }
        $id = (int)$this->request->getData('id');
        $currentUser = $this->currentUser();
        if ($id === (int)($currentUser['id'] ?? 0)) {
            return $this->jsonError('cannot_delete_self');
        }
        return $this->jsonSuccess(['intAffectedRows' => $this->Users->updateAll(
            ['status' => 'deleted'],
            ['id' => $id]
        )]);
    }

    private function validatePasswordStrength(string $pw): ?string
    {
        if (mb_strlen($pw) < 8) {
            return __('Password must be at least 8 characters long.');
        }
        if (!preg_match('/[a-z]/', $pw)) {
            return __('Password must contain at least one lowercase letter.');
        }
        if (!preg_match('/[A-Z]/', $pw)) {
            return __('Password must contain at least one uppercase letter.');
        }
        if (!preg_match('/\d/', $pw)) {
            return __('Password must contain at least one digit.');
        }
        return null;
    }

    private function hashPassword(string $pw): string
    {
        return password_hash(hash_hmac('sha256', $pw, \Cake\Utility\Security::getSalt()), PASSWORD_DEFAULT);
    }
    private function verifyPassword(string $pw, string $hash): bool
    {
        return password_verify(hash_hmac('sha256', $pw, \Cake\Utility\Security::getSalt()), $hash);
    }
    private function getRateLimitDir(): string
    {
        $d = ROOT . DS . 'storage' . DS . 'ratelimit';
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        } return $d;
    }
    private function isRateLimited(string $k): bool
    {
        $f = $this->getRateLimitDir() . DS . md5($k) . '.json';
        if (!file_exists($f)) {
            return false;
        }
        $handle = fopen($f, 'r');
        if (!$handle) {
            return false;
        }
        flock($handle, LOCK_SH);
        $d = json_decode(stream_get_contents($handle), true);
        flock($handle, LOCK_UN);
        fclose($handle);
        if (!$d) {
            return false;
        }
        if (time() - ($d['first_attempt'] ?? 0) > self::LOCKOUT_SECONDS) {
            @unlink($f);
            return false;
        }
        return ($d['attempts'] ?? 0) >= self::MAX_LOGIN_ATTEMPTS;
    }
    private function recordFailedAttempt(string $k): void
    {
        $f = $this->getRateLimitDir() . DS . md5($k) . '.json';
        // Atomic read-modify-write with exclusive lock over entire cycle
        $handle = fopen($f, 'c+');
        if (!$handle) {
            return;
        }
        flock($handle, LOCK_EX);
        $content = stream_get_contents($handle);
        $d = ['attempts' => 0, 'first_attempt' => time()];
        if ($content) {
            $e = json_decode($content, true);
            if ($e && (time() - ($e['first_attempt'] ?? 0)) < self::LOCKOUT_SECONDS) {
                $d = $e;
            }
        }
        $d['attempts'] = ($d['attempts'] ?? 0) + 1;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($d));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    private function clearRateLimit(string $k): void
    {
        $f = $this->getRateLimitDir() . DS . md5($k) . '.json';
        if (file_exists($f)) {
            @unlink($f);
        }
    }
}
