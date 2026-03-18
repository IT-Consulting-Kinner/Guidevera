<?php

/**
 * Model integration tests.
 *
 * Schema is loaded by tests/bootstrap.php via SchemaLoader.
 * No manual schema loading needed here.
 */

declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PagesindexTable;
use App\Model\Table\PagesTable;
use App\Model\Table\UsersTable;
use Cake\TestSuite\TestCase;

class ModelsTest extends TestCase
{
    protected array $fixtures = [];

    protected PagesTable $Pages;
    protected UsersTable $Users;
    protected PagesindexTable $Pagesindex;

    public function setUp(): void
    {
        parent::setUp();

        $this->Pages = $this->fetchTable('Pages');
        $this->Users = $this->fetchTable('Users');
        $this->Pagesindex = $this->fetchTable('Pagesindex');
    }

    public function tearDown(): void
    {
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $connection->execute('DELETE FROM pagesindex');
        $connection->execute('DELETE FROM pages');
        $connection->execute('DELETE FROM users');

        parent::tearDown();
    }

    // ── Users ──

    public function testCreateUser(): void
    {
        $user = $this->Users->newEntity([
            'gender' => 'male',
            'username' => 'testadmin',
            'password' => 'hashed_pw_123',
            'fullname' => 'Test Admin',
            'email' => 'admin@test.com',
            'role' => 'admin',
            'status' => 'active',
            'page_tree' => '',
            'preferences' => '{}',
        ]);

        $this->assertEmpty($user->getErrors());

        $result = $this->Users->save($user);
        $this->assertNotFalse($result);
        $this->assertNotEmpty($result->id);
    }

    public function testUserValidationFailsOnInvalidRole(): void
    {
        $user = $this->Users->newEntity([
            'gender' => 'male',
            'username' => 'bad',
            'password' => 'pw',
            'fullname' => 'Bad User',
            'email' => 'bad@test.com',
            'role' => 'superuser',
            'status' => 'active',
        ]);

        $this->assertNotEmpty($user->getErrors());
        $this->assertArrayHasKey('role', $user->getErrors());
    }

    public function testFindUsers(): void
    {
        $this->createTestUser('alice', 'Alice Admin', 'admin');
        $this->createTestUser('bob', 'Bob Editor', 'editor');

        $users = $this->Users->find()
            ->where(['status !=' => 'deleted'])
            ->orderBy(['fullname' => 'ASC'])
            ->all();

        $this->assertCount(2, $users);
        $this->assertEquals('Alice Admin', $users->first()->fullname);
    }

    // ── Pages ──

    public function testCreatePage(): void
    {
        $page = $this->Pages->newEntity([
            'title' => 'Getting Started',
            'description' => 'Introduction to the app',
            'content' => '<p>Welcome!</p>',
            'status' => 'active',
            'position' => 1,
        ]);

        $this->assertEmpty($page->getErrors());

        $result = $this->Pages->save($page);
        $this->assertNotFalse($result);
        $this->assertNotEmpty($result->id);
    }

    public function testTreeBehavior(): void
    {
        $root = $this->Pages->newEntity([
            'title' => 'Manual',
            'content' => '',
            'status' => 'active',
            'parent_id' => null,
        ]);
        $this->assertNotFalse($this->Pages->save($root));

        $child = $this->Pages->newEntity([
            'title' => 'Chapter 1',
            'content' => '',
            'status' => 'active',
            'parent_id' => $root->id,
        ]);
        $this->assertNotFalse($this->Pages->save($child));

        $grandchild = $this->Pages->newEntity([
            'title' => 'Section 1.1',
            'content' => '',
            'status' => 'active',
            'parent_id' => $child->id,
        ]);
        $this->assertNotFalse($this->Pages->save($grandchild));

        $tree = $this->Pages->find('threaded')
            ->orderBy(['position' => 'ASC'])
            ->all()
            ->toArray();

        $this->assertCount(1, $tree);
        $this->assertEquals('Manual', $tree[0]->title);
        $this->assertCount(1, $tree[0]->children);
        $this->assertEquals('Chapter 1', $tree[0]->children[0]->title);
        $this->assertCount(1, $tree[0]->children[0]->children);
        $this->assertEquals('Section 1.1', $tree[0]->children[0]->children[0]->title);

        $childPages = $this->Pages->find()
            ->where(['parent_id' => $root->id])
            ->all();

        $this->assertCount(1, $childPages);
        $this->assertEquals('Chapter 1', $childPages->first()->title);
    }

    public function testPageAssociations(): void
    {
        $user = $this->createTestUser('author', 'Author User', 'editor');

        $page = $this->Pages->newEntity([
            'title' => 'Test Page',
            'content' => '<p>Content</p>',
            'status' => 'active',
            'created_by' => $user->id,
            'modified_by' => $user->id,
        ]);

        $saved = $this->Pages->save($page);
        $this->assertNotFalse($saved);
        $this->assertNotEmpty($saved->id);

        $found = $this->Pages->find()
            ->contain(['CreatedByUsers', 'ModifiedByUsers'])
            ->where(['Pages.id' => $saved->id])
            ->firstOrFail();

        $this->assertNotNull($found->creator);
        $this->assertNotNull($found->modifier);
        $this->assertEquals('Author User', $found->creator->fullname);
        $this->assertEquals('Author User', $found->modifier->fullname);
    }

    public function testPageValidation(): void
    {
        $page = $this->Pages->newEntity([
            'title' => 'Valid Page',
            'status' => 'bogus',
        ]);

        $this->assertNotEmpty($page->getErrors());
        $this->assertArrayHasKey('status', $page->getErrors());
    }

    // ── Pagesindex ──

    public function testPagesindexKeywords(): void
    {
        $page = $this->Pages->newEntity([
            'title' => 'Keyword Test',
            'content' => '',
            'status' => 'active',
        ]);
        $this->assertNotFalse($this->Pages->save($page));

        $kw1 = $this->Pagesindex->newEntity([
            'keyword' => 'security',
            'page_id' => $page->id,
        ]);
        $kw2 = $this->Pagesindex->newEntity([
            'keyword' => 'authentication',
            'page_id' => $page->id,
        ]);

        $this->assertNotFalse($this->Pagesindex->save($kw1));
        $this->assertNotFalse($this->Pagesindex->save($kw2));

        $keywords = $this->Pagesindex->find()
            ->where(['page_id' => $page->id])
            ->all();

        $this->assertCount(2, $keywords);
    }

    private function createTestUser(string $username, string $fullname, string $role): object
    {
        $user = $this->Users->newEntity([
            'gender' => 'male',
            'username' => $username,
            'password' => 'hashed_pw_123',
            'fullname' => $fullname,
            'email' => $username . '@test.com',
            'role' => $role,
            'status' => 'active',
            'page_tree' => '',
            'preferences' => '{}',
        ]);

        $saved = $this->Users->save($user);
        $this->assertNotFalse($saved);

        return $saved;
    }
}