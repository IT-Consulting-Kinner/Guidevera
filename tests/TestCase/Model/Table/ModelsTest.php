<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\PagesTable;
use App\Model\Table\UsersTable;
use App\Model\Table\TemplatesTable;
use App\Model\Table\PagesindexTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class ModelsTest extends TestCase
{
    protected array $fixtures = [];

    protected PagesTable $Pages;
    protected UsersTable $Users;
    protected TemplatesTable $Templates;
    protected PagesindexTable $Pagesindex;

    public function setUp(): void
    {
        parent::setUp();

        // Ensure tables exist in test DB
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $schemaFile = ROOT . DS . 'db' . DS . 'schema.sql';
        if (file_exists($schemaFile)) {
            $statements = explode(';', file_get_contents($schemaFile));
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    $connection->execute($stmt);
                }
            }
        }

        $this->Pages = $this->fetchTable('Pages');
        $this->Users = $this->fetchTable('Users');
        $this->Templates = $this->fetchTable('Templates');
        $this->Pagesindex = $this->fetchTable('Pagesindex');
    }

    public function tearDown(): void
    {
        // Clean up tables
        $connection = \Cake\Datasource\ConnectionManager::get('test');
        $connection->execute('DELETE FROM pagesindex');
        $connection->execute('DELETE FROM pages');
        $connection->execute('DELETE FROM users');
        $connection->execute('DELETE FROM templates');

        parent::tearDown();
    }

    // ---- USERS ----

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
            'role' => 'superuser',  // invalid
            'status' => 'active',
        ]);
        $this->assertNotEmpty($user->getErrors());
        $this->assertArrayHasKey('role', $user->getErrors());
    }

    public function testFindUsers(): void
    {
        $this->_createTestUser('alice', 'Alice Admin', 'admin');
        $this->_createTestUser('bob', 'Bob Editor', 'editor');

        $users = $this->Users->find()
            ->where(['status !=' => 'deleted'])
            ->orderBy(['fullname' => 'ASC'])
            ->all();

        $this->assertCount(2, $users);
        $this->assertEquals('Alice Admin', $users->first()->fullname);
    }

    // ---- PAGES ----

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
        // Tree columns should be set
        $this->assertNotNull($result->lft);
        $this->assertNotNull($result->rght);
    }

    public function testTreeBehavior(): void
    {
        // Create root page
        $root = $this->Pages->newEntity([
            'title' => 'Manual',
            'status' => 'active',
            'parent_id' => null,
        ]);
        $this->Pages->save($root);

        // Create child
        $child = $this->Pages->newEntity([
            'title' => 'Chapter 1',
            'status' => 'active',
            'parent_id' => $root->id,
        ]);
        $this->Pages->save($child);

        // Create grandchild
        $grandchild = $this->Pages->newEntity([
            'title' => 'Section 1.1',
            'status' => 'active',
            'parent_id' => $child->id,
        ]);
        $this->Pages->save($grandchild);

        // Test find('threaded')
        $tree = $this->Pages->find('threaded')
            ->orderBy(['lft' => 'ASC'])
            ->all()
            ->toArray();

        $this->assertCount(1, $tree); // Only root at top level
        $this->assertEquals('Manual', $tree[0]->title);
        $this->assertCount(1, $tree[0]->children);
        $this->assertEquals('Chapter 1', $tree[0]->children[0]->title);
        $this->assertCount(1, $tree[0]->children[0]->children);
        $this->assertEquals('Section 1.1', $tree[0]->children[0]->children[0]->title);

        // Test find('path')
        $path = $this->Pages->find('path', for: $grandchild->id)->all()->toArray();
        $this->assertCount(3, $path);
        $this->assertEquals('Manual', $path[0]->title);
        $this->assertEquals('Chapter 1', $path[1]->title);
        $this->assertEquals('Section 1.1', $path[2]->title);

        // Test find('children')
        $children = $this->Pages->find('children', for: $root->id)->all();
        $this->assertCount(2, $children); // child + grandchild
    }

    public function testPageAssociations(): void
    {
        $user = $this->_createTestUser('author', 'Author User', 'editor');

        $page = $this->Pages->newEntity([
            'title' => 'Test Page',
            'content' => '<p>Content</p>',
            'status' => 'active',
            'created_by' => $user->id,
            'modified_by' => $user->id,
        ]);
        $this->Pages->save($page);

        // Fetch with associations
        $found = $this->Pages->get($page->id, contain: ['CreatedByUsers', 'ModifiedByUsers']);
        $this->assertEquals('Author User', $found->creator->fullname);
        $this->assertEquals('Author User', $found->modifier->fullname);
    }

    public function testPageValidation(): void
    {
        $page = $this->Pages->newEntity([
            'title' => 'Valid Page',
            'status' => 'bogus',  // invalid
        ]);
        $this->assertNotEmpty($page->getErrors());
        $this->assertArrayHasKey('status', $page->getErrors());
    }

    // ---- TEMPLATES ----

    public function testCreateTemplate(): void
    {
        $tpl = $this->Templates->newEntity([
            'title' => 'Standard Template',
            'content' => '<h1>{title}</h1><p>{content}</p>',
            'status' => 'active',
        ]);
        $this->assertEmpty($tpl->getErrors());
        $result = $this->Templates->save($tpl);
        $this->assertNotFalse($result);
        $this->assertNotEmpty($result->id);
    }

    public function testUpdateTemplate(): void
    {
        $tpl = $this->_createTestTemplate('Original', 'active');
        $tpl = $this->Templates->patchEntity($tpl, ['title' => 'Updated Title']);
        $result = $this->Templates->save($tpl);
        $this->assertNotFalse($result);

        $found = $this->Templates->get($tpl->id);
        $this->assertEquals('Updated Title', $found->title);
    }

    public function testDeleteTemplate(): void
    {
        $tpl = $this->_createTestTemplate('ToDelete', 'inactive');
        $result = $this->Templates->delete($tpl);
        $this->assertTrue($result);
        $this->assertNull($this->Templates->find()->where(['id' => $tpl->id])->first());
    }

    // ---- PAGESINDEX ----

    public function testPagesindexKeywords(): void
    {
        $page = $this->Pages->newEntity([
            'title' => 'Keyword Test',
            'status' => 'active',
        ]);
        $this->Pages->save($page);

        $kw1 = $this->Pagesindex->newEntity(['keyword' => 'security', 'page_id' => $page->id]);
        $kw2 = $this->Pagesindex->newEntity(['keyword' => 'authentication', 'page_id' => $page->id]);
        $this->Pagesindex->save($kw1);
        $this->Pagesindex->save($kw2);

        $keywords = $this->Pagesindex->find()
            ->where(['page_id' => $page->id])
            ->all();

        $this->assertCount(2, $keywords);
    }

    public function testPagesindexBelongsToPages(): void
    {
        $page = $this->Pages->newEntity(['title' => 'KW Page', 'status' => 'active']);
        $this->Pages->save($page);

        $kw = $this->Pagesindex->newEntity(['keyword' => 'test', 'page_id' => $page->id]);
        $this->Pagesindex->save($kw);

        $found = $this->Pagesindex->get($kw->id, contain: ['Pages']);
        $this->assertEquals('KW Page', $found->page->title);
    }

    // ---- Helpers ----

    private function _createTestUser(string $username, string $fullname, string $role): \App\Model\Entity\User
    {
        $user = $this->Users->newEntity([
            'gender' => 'male',
            'username' => $username,
            'password' => 'hashed_' . $username,
            'fullname' => $fullname,
            'email' => $username . '@test.com',
            'role' => $role,
            'status' => 'active',
        ]);
        $this->Users->save($user);
        return $user;
    }

    private function _createTestTemplate(string $title, string $status): \App\Model\Entity\Template
    {
        $tpl = $this->Templates->newEntity([
            'title' => $title,
            'content' => '<p>Template content</p>',
            'status' => $status,
        ]);
        $this->Templates->save($tpl);
        return $tpl;
    }
}
