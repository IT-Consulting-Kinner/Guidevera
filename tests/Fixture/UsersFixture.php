<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public string $table = 'users';

    public array $records = [
        [
            'id' => 1,
            'gender' => 'male',
            'username' => 'admin',
            'password' => '$2y$10$bZVwlBJJ3GTyi99TZMldUeZ5dmYICCN6ekzEI5b0dgns9BLzcjKBC', // password123 with app salt
            'fullname' => 'Test Admin',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'change_password' => 0,
            'page_tree' => '{"open":"","active_page":"1"}',
            'notify_mentions' => 1,
            'preferences' => '{}',
            'status' => 'active',
        ],
    ];
}
