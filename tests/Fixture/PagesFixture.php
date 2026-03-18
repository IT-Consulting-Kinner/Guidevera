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
            'created' => '2025-01-01 10:00:00',
            'created_by' => 1,
            'modified' => '2025-01-05 14:00:00',
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
            'created' => '2025-01-02 11:00:00',
            'created_by' => 1,
            'modified' => '2025-01-06 09:00:00',
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
            'created' => '2025-01-03 08:00:00',
            'created_by' => 1,
            'modified' => '2025-01-03 08:00:00',
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
