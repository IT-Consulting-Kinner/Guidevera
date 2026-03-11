<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ControllersTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [];

    public function setUp(): void
    {
        parent::setUp();

        // Ensure schema
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $schemaFile = ROOT . DS . 'db' . DS . 'schema.sql';
        $statements = explode(';', file_get_contents($schemaFile));
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt)) {
                try {
                    $connection->execute($stmt);
                } catch (\Exception $e) {
                }
            }
        }

        // Create test user (password: password123, hashed with HMAC-SHA256 + bcrypt)
        $salt = \Cake\Utility\Security::getSalt();
        $this->assertNotSame('', $salt, 'Security salt must not be empty');
        $hashedPw = password_hash(
            hash_hmac('sha256', 'password123', $salt),
            PASSWORD_DEFAULT
        );
        $connection->execute(
            "INSERT OR IGNORE INTO users (id, gender, username, password, fullname, email, role,
                change_password, page_tree, status)
                VALUES (1, 'male', 'admin', '{$hashedPw}', 'Test Admin',
                'admin@test.com', 'admin', 0, '', 'active')"
        );

        // Create test pages with tree structure
        $connection->execute("INSERT OR IGNORE INTO pages (id, parent_id, lft, rght, position, title, content,
            status, views, created_by, modified_by) VALUES (1, NULL, 1, 6, 1, 'Manual', '<p>Root page</p>', '
                active', 5, 1, 1)");
        $connection->execute("INSERT OR IGNORE INTO pages (id, parent_id, lft, rght, position, title, content,
            status, views, created_by, modified_by) VALUES (2, 1, 2, 3, 1, 'Chapter 1', '<p>First chapter</p>', '
                active', 3, 1, 1)");
        $connection->execute("INSERT OR IGNORE INTO pages (id, parent_id, lft, rght, position, title, content,
            status, views, created_by, modified_by) VALUES (3, 1, 4, 5, 2, 'Chapter 2', '<p>Second chapter</p>', '
                inactive', 0, 1, 1)");

        // Keywords
        $connection->execute("INSERT OR IGNORE INTO pagesindex (id, keyword, page_id) VALUES (1, 'intro', 1)");
        $connection->execute("INSERT OR IGNORE INTO pagesindex (id, keyword, page_id) VALUES (2, 'start', 1)");

        // Template
        $connection->execute("INSERT OR IGNORE INTO templates (id, title, content, status) VALUES (1, 'Standard', '
            <h1>Title</h1>', 'active')");

        // Storage directory for files
        $dir = ROOT . DS . 'storage' . DS . 'media';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Enable CSRF token for all POST requests
        $this->enableCsrfToken();
    }

    public function tearDown(): void
    {
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $connection->execute('DELETE FROM pagesindex');
        $connection->execute('DELETE FROM pages');
        $connection->execute('DELETE FROM users');
        $connection->execute('DELETE FROM templates');

        parent::tearDown();
    }

    // ---- PAGES CONTROLLER ----

    public function testPagesIndex(): void
    {
        $this->get('/');
        $this->assertResponseOk();
    }

    public function testPagesGetTree(): void
    {
        $this->post('/pages/get_tree');
        $this->assertResponseOk();
        $this->assertContentType('application/json');

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('arrTree', $body);
        $this->assertCount(3, $body['arrTree']);
        $this->assertEquals('Manual', $body['arrTree'][0]['title']);
    }

    public function testPagesShow(): void
    {
        $this->post('/pages/show', ['id' => 1]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['id']);
        $this->assertStringContainsString('Root page', $body['content']);
        $this->assertStringContainsString('intro', $body['keywords']);
    }

    public function testPagesShowInvalid(): void
    {
        $this->post('/pages/show', ['id' => 999]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testPagesCreateRequiresAuth(): void
    {
        $this->post('/pages/create');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('not_authenticated', $body['error']);
    }

    public function testPagesCreateWithAuth(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Admin']]);
        $this->post('/pages/create');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
        $this->assertGreaterThan(0, $body['intId']);
    }

    public function testPagesSaveWithAuth(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin', 'fullname' => 'Admin']]);
        $this->post('/pages/save', [
            'id' => 2,
            'title' => 'Chapter 1 Updated',
            'content' => '<p>Updated content</p>',
            'description' => 'Updated desc',
            'keywords' => 'new, keywords',
        ]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows']);
    }

    public function testPagesSetStatus(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/pages/set_status', ['id' => 3, 'status' => 'active']);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intAffectedRows', $body);
    }

    public function testPagesSearch(): void
    {
        $this->post('/pages/search', ['search' => 'First']);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertStringContainsString('Chapter 1', $body['content']);
    }

    public function testPagesDeleteLeaf(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/pages/delete', ['id' => 2]); // Chapter 1 has no children
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intAffectedRows', $body);
    }

    public function testPagesDeleteParentFails(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/pages/delete', ['id' => 1]); // Root has children
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('page_has_children', $body['error']);
    }

    // ---- USERS CONTROLLER ----

    public function testLoginPage(): void
    {
        $this->get('/user/login');
        $this->assertResponseOk();
    }

    public function testLoginSuccess(): void
    {
        $this->enableCsrfToken();
        $this->post('/user/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);
        $this->assertResponseCode(302); // redirect after login
    }

    public function testLoginFailure(): void
    {
        $this->enableCsrfToken();
        $this->post('/user/login', [
            'username' => 'admin',
            'password' => 'wrongpassword',
        ]);
        $this->assertResponseOk(); // stays on login page
    }

    public function testLogout(): void
    {
        $this->session(['Auth' => ['id' => 1]]);
        $this->get('/user/logout');
        $this->assertResponseCode(302);
    }

    public function testSavePageTree(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/user/save_page_tree', ['strElements' => 'open[1]=1&open[2]=1']);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows']);
    }

    // ---- TEMPLATES CONTROLLER ----

    public function testTemplatesGetTree(): void
    {
        $this->post('/templates/get-tree');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('arrTree', $body);
        $this->assertCount(1, $body['arrTree']);
    }

    public function testTemplatesCreateWithAuth(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/templates/create');
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intId', $body);
    }

    public function testTemplatesShow(): void
    {
        $this->post('/templates/show', ['id' => 1]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals('Standard', $body['title']);
    }

    public function testTemplatesSave(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/templates/save', ['id' => 1, 'title' => 'Updated', 'content' => '<p>New</p>']);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertEquals(1, $body['intAffectedRows']);
    }

    public function testTemplatesDelete(): void
    {
        $this->session(['Auth' => ['id' => 1, 'role' => 'admin']]);
        $this->post('/templates/delete', ['id' => 1]);
        $this->assertResponseOk();

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('intAffectedRows', $body);
    }

    // ---- PAGES SERVICE ----

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
