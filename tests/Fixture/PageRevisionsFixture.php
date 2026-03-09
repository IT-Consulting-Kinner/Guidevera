<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PageRevisionsFixture extends TestFixture
{
    public string $table = 'page_revisions';

    public array $records = [
        [
            'id' => 1,
            'page_id' => 1,
            'title' => 'Root Page (old version)',
            'description' => 'Old description',
            'content' => '<p>Old content before edit</p>',
            'created_by' => 1,
            'created' => '2025-01-01 10:00:00',
            'revision_note' => '',
        ],
        [
            'id' => 2,
            'page_id' => 2,
            'title' => 'Child Page (draft)',
            'description' => '',
            'content' => '<p>Draft content</p>',
            'created_by' => 1,
            'created' => '2025-01-02 14:30:00',
            'revision_note' => 'Before major rewrite',
        ],
    ];
}
