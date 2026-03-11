<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class UsersControllerTest extends TestCase
{
    use IntegrationTestTrait;

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
        $this->enableCsrfToken();
        $this->post('/user/login', ['username' => 'nobody', 'password' => 'wrong']);
        // Should redirect back to login or show error
        $this->assertResponseCode(302); // redirect
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
        $this->post('/user/save_page_tree', ['strElements' => 'open[0]=1']);
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }
}
