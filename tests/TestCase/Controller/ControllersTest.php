<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;

class ControllersTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [];

    public function setUp(): void
    {
        parent::setUp();

        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $now = date('Y-m-d H:i:s');

        // Create test user (all NOT NULL fields)
        $salt = Security::getSalt();
        $this->assertNotSame('', $salt, 'Security salt must not be empty');
        $hashedPw = password_hash(
            hash_hmac('sha256', 'password123', $salt),
            PASSWORD_DEFAULT
        );
        $connection->execute(
            "INSERT IGNORE INTO users
                (id, gender, username, password, fullname, email, role,
                change_password, page_tree, notify_mentions, preferences, status)
            VALUES
                (1, 'male', 'admin', ?, 'Test Admin', 'admin@test.com', 'admin',
                0, '', 1, '{}', 'active')",
            [$hashedPw]
        );

        // Create test pages (datetime NOT NULL — no DEFAULT)
        $connection->execute(
            "INSERT IGNORE INTO pages
                (id, parent_id, position, title, content, status, workflow_status,
                views, created_by, modified_by, locale, created, modified)
            VALUES
                (1, NULL, 0, 'Manual', '<p>Root page</p>', 'active', 'published',
                5, 1, 1, 'en', ?, ?)",
            [$now, $now]
        );
        $connection->execute(
            "INSERT IGNORE INTO pages
                (id, parent_id, position, title, content, status, workflow_status,
                views, created_by, modified_by, locale, created, modified)
            VALUES
                (2, 1, 1, 'Chapter 1', '<p>First chapter</p>', 'active', 'published',
                3, 1, 1, 'en', ?, ?)",
            [$now, $now]
        );
        $connection->execute(
            "INSERT IGNORE INTO pages
                (id, parent_id, position, title, content, status, workflow_status,
                views, created_by, modified_by, locale, created, modified)
            VALUES
                (3, 1, 2, 'Chapter 2', '<p>Second chapter</p>', 'inactive', 'draft',
                0, 1, 1, 'en', ?, ?)",
            [$now, $now]
        );

        // Keywords
        $connection->execute(
            "INSERT IGNORE INTO pagesindex (id, keyword, page_id)
            VALUES (1, 'intro', 1)"
        );
        $connection->execute(
            "INSERT IGNORE INTO pagesindex (id, keyword, page_id)
            VALUES (2, 'start', 1)"
        );

        // Storage directory
        $dir = ROOT . DS . 'storage' . DS . 'media';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->enableCsrfToken();
        $this->configRequest([
            'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
        ]);
    }

    public function tearDown(): void
    {
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $connection->execute('DELETE FROM pagesindex');
        $connection->execute('DELETE FROM pages');
        $connection->execute('DELETE FROM users');

        parent::tearDown();
    }

    // ── Pages Controller ──

    public function testPagesIndex(): void
    {
        $this->get('/');
        $this->assertResponseOk();
    }

    public function testPagesGetTree(): void
    {
        $this->post('/pages/get_tree');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('arrTree', $body);
        $this->assertNotEmpty($body['arrTree']);
    }

    public function testPagesShow(): void
    {
        $this->post('/pages/show', ['id' => 1]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('Manual', $body['title'] ?? '');
    }

    public function testPagesShowInvalid(): void
    {
        $this->post('/pages/show', ['id' => 99999]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testPagesCreateRequiresAuth(): void
    {
        $this->post('/pages/create');
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testPagesCreateWithAuth(): void
    {
        $this->session([
            'Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test'],
        ]);
        $this->post('/pages/create');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
    }

    public function testPagesSaveWithAuth(): void
    {
        $this->session([
            'Auth' => ['id' => 1, 'role' => 'editor', 'fullname' => 'Test'],
        ]);
        $this->post('/pages/save', [
            'id' => 1,
            'title' => 'Updated Manual',
            'description' => 'Test desc',
            'content' => '<p>Updated content</p>',
            'keywords' => 'test',
        ]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows'] ?? 0);
    }

    public function testPagesSetStatus(): void
    {
        $this->session([
            'Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test'],
        ]);
        $this->post('/pages/set_status', [
            'id' => 3,
            'status' => 'active',
        ]);
        $this->assertResponseOk();
    }

    public function testPagesSearch(): void
    {
        $this->post('/pages/search', ['search' => 'Root']);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('results', $body);
    }

    public function testPagesDeleteLeaf(): void
    {
        $this->session([
            'Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test'],
        ]);
        $this->post('/pages/delete', ['id' => 3]);
        $this->assertResponseOk();
    }

    public function testPagesDeleteParentCascades(): void
    {
        $this->session([
            'Auth' => ['id' => 1, 'role' => 'contributor', 'fullname' => 'Test'],
        ]);
        $this->post('/pages/delete', ['id' => 1]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        // Cascade delete now succeeds — parent + children are soft-deleted
        $this->assertEquals(1, $body['intAffectedRows'] ?? 0);
    }

    // ── Users Controller ──

    public function testLoginPage(): void
    {
        $this->get('/user/login');
        $this->assertResponseOk();
    }

    public function testLoginSuccess(): void
    {
        $this->post('/user/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);
        $this->assertResponseSuccess();
    }

    public function testLoginFailure(): void
    {
        $this->post('/user/login', [
            'username' => 'admin',
            'password' => 'wrongpassword',
        ]);
        $code = $this->_response->getStatusCode();
        $this->assertEquals(200, $code, "Expected login form re-display (200), got {$code}");
        $this->assertNull(
            $this->_requestSession->read('Auth.id'),
            'User should not be authenticated after failed login'
        );
    }

    public function testLogout(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->get('/user/logout');
        $this->assertRedirect('/');
    }

    public function testSavePageTree(): void
    {
        $this->session([
            'Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Test'],
        ]);
        $tree = json_encode([
            ['id' => 1, 'children' => [
                ['id' => 2, 'children' => []],
                ['id' => 3, 'children' => []],
            ]],
        ]);
        $this->post('/user/save_page_tree', ['tree' => $tree]);
        $this->assertResponseOk();
    }

    // ── Pages Service ──

    public function testPagesServiceChapterNumbering(): void
    {
        $pages = [
            ['id' => 1, 'parent_id' => 0, 'title' => 'Root', 'status' => 'active'],
            ['id' => 2, 'parent_id' => 1, 'title' => 'Ch1', 'status' => 'active'],
            ['id' => 3, 'parent_id' => 1, 'title' => 'Ch2', 'status' => 'active'],
        ];

        $result = \App\Service\PagesService::calculateChapterNumbering($pages);
        $this->assertNotEmpty($result[1]['chapter']);
        $this->assertNotEmpty($result[2]['chapter']);
    }

    public function testPagesServiceNavigation(): void
    {
        $pages = [
            ['id' => 1, 'parent_id' => 0, 'title' => 'Root', 'status' => 'active'],
            ['id' => 2, 'parent_id' => 1, 'title' => 'Ch1', 'status' => 'active'],
            ['id' => 3, 'parent_id' => 1, 'title' => 'Ch2', 'status' => 'active'],
        ];

        $nav = \App\Service\PagesService::calculateNavigation(2, $pages);
        $this->assertEquals(3, $nav['nextId']);
        $this->assertEquals(1, $nav['previousId']);
    }
}
