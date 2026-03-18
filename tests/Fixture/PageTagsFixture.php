<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PageTagsFixture extends TestFixture
{
    public string $table = 'page_tags';

    public array $records = [
        [
            'id' => 1,
            'page_id' => 1,
            'tag' => 'manual',
        ],
        [
            'id' => 2,
            'page_id' => 2,
            'tag' => 'test',
        ],
    ];
}
