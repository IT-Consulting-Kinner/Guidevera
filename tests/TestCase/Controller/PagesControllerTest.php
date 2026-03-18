<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PagesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->configRequest([
            'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
        ]);
    }

    protected array $fixtures = ['app.Pages', 'app.Users', 'app.Pagesindex'];

    // ── Auth guard tests ──

    public function testEditRequiresAuth(): void
    {
        $this->post('/pages/edit', ['id' => 1]);
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testCreateRequiresAuth(): void
    {
        $this->post('/pages/create');
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testSaveRequiresAuth(): void
    {
        $this->post('/pages/save', ['id' => 1, 'title' => 'Test']);
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testDeleteRequiresAuth(): void
    {
        $this->post('/pages/delete', ['id' => 1]);
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testSetStatusRequiresAuth(): void
    {
        $this->post('/pages/set_status', ['id' => 1, 'status' => 'active']);
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testUpdateOrderRequiresAuth(): void
    {
        $this->post('/pages/update_order', ['strPages' => '']);
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    public function testBrowseRequiresAuth(): void
    {
        $this->post('/pages/browse');
        $code = $this->_response->getStatusCode();
        $this->assertEquals(302, $code, "Expected redirect to login, got {$code}");
    }

    // ── Show endpoint (public) ──

    public function testShowReturnsJson(): void
    {
        $this->post('/pages/show', ['id' => 1]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        // Should contain page data fields
        $this->assertArrayHasKey('id', $body, 'Response should contain page id');
        $this->assertArrayHasKey('title', $body, 'Response should contain page title');
        $this->assertArrayHasKey('content', $body, 'Response should contain page content');
        $this->assertArrayHasKey('status', $body, 'Response should contain page status');
    }

    public function testShowInvalidId(): void
    {
        $this->post('/pages/show', ['id' => 0]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testShowNonexistentPage(): void
    {
        $this->post('/pages/show', ['id' => 99999]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    // ── GetTree (public) ──

    public function testGetTreeReturnsJson(): void
    {
        $this->post('/pages/get_tree');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('arrTree', $body);
        $this->assertIsArray($body['arrTree']);
    }

    // ── Search (public) ──

    public function testSearchReturnsResults(): void
    {
        $this->post('/pages/search', ['search' => 'test']);
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('results', $body);
    }

    public function testSearchEmptyQuery(): void
    {
        $this->post('/pages/search', ['search' => '']);
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('results', $body);
    }

    // ── Index endpoint ──

    public function testBuildIndexReturnsJson(): void
    {
        $this->get('/pages/index');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('indexes', $body, 'Response should contain indexes');
    }

    // ── HTTP method enforcement ──

    public function testShowRejectsGet(): void
    {
        $this->get('/pages/show');
        $this->assertResponseCode(405);
    }

    public function testEditRejectsGet(): void
    {
        $this->get('/pages/edit');
        $this->assertResponseCode(405);
    }

    // ── Index page (SSR) ──

    public function testIndexPageLoads(): void
    {
        $this->get('/pages');
        $this->assertResponseOk();
        $this->assertResponseContains('page_navigation');
    }

    public function testIndexPageWithId(): void
    {
        $this->get('/pages/1/test-page');
        $this->assertResponseOk();
    }
}
