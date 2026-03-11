<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PagesFixture extends TestFixture
{
    public string $table = 'pages';

    public array $records = [
        [
            'id' => 1,
            'created_by' => 1,
            'modified_by' => 1,
            'parent_id' => null,
            'position' => 0,
            'title' => 'Root Page',
            'description' => 'Test root page',
            'content' => '<p>Root content</p>',
            'views' => 5,
            'status' => 'active',
            'workflow_status' => 'published',
            'locale' => 'en',
        ],
        [
            'id' => 2,
            'created_by' => 1,
            'modified_by' => 1,
            'parent_id' => 1,
            'position' => 1,
            'title' => 'Child Page',
            'description' => 'A child page',
            'content' => '<p>Child content with <strong>test</strong> keyword</p>',
            'views' => 3,
            'status' => 'active',
            'workflow_status' => 'published',
            'locale' => 'en',
        ],
        [
            'id' => 3,
            'created_by' => 1,
            'modified_by' => 1,
            'parent_id' => 1,
            'position' => 2,
            'title' => 'Inactive Page',
            'description' => 'Draft page',
            'content' => '<p>Draft content</p>',
            'views' => 0,
            'status' => 'inactive',
            'workflow_status' => 'draft',
            'locale' => 'en',
        ],
    ];
}
