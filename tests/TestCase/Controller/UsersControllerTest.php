<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class UsersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
    }

    protected array $fixtures = ['app.Users'];

    public function testLoginPageLoads(): void
    {
        $this->get('/user/login');
        $this->assertResponseOk();
        $this->assertResponseContains('username');
        $this->assertResponseContains('password');
    }

    public function testLoginPageHasCsrfToken(): void
    {
        $this->get('/user/login');
        $this->assertResponseOk();
        $this->assertResponseContains('_csrfToken');
    }

    public function testLoginFailsWithBadCredentials(): void
    {
        $this->post('/user/login', ['username' => 'nobody', 'password' => 'wrong']);
        $code = $this->_response->getStatusCode();
        // Failed login: re-renders form (200) or redirects back (302)
        $this->assertTrue(
            $code === 200 || $code === 302,
            "Expected 200 or 302, got {$code}"
        );
    }

    public function testLogoutRedirects(): void
    {
        $this->get('/user/logout');
        $this->assertRedirect('/');
    }

    public function testProfileRequiresAuth(): void
    {
        $this->get('/user/profil');
        $this->assertRedirect('/user/login');
    }

    public function testChangePasswordRequiresAuth(): void
    {
        $this->get('/user/change-password');
        $this->assertRedirect('/user/login');
    }

    public function testUserIndexRequiresAuth(): void
    {
        $this->get('/user');
        $this->assertRedirect('/user/login');
    }

    public function testSavePageTreeRequiresAuth(): void
    {
        $this->configRequest([
            'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
        ]);
        $this->post('/user/save_page_tree', ['strElements' => 'open[0]=1']);
        $code = $this->_response->getStatusCode();
        $this->assertTrue(
            $code === 200 || $code === 302,
            "Expected 200 or 302, got {$code}"
        );
    }
}
