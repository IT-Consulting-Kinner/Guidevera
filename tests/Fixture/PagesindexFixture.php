<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PagesindexFixture extends TestFixture
{
    public string $table = 'pagesindex';

    public array $records = [
        ['id' => 1, 'keyword' => 'test', 'page_id' => 2],
        ['id' => 2, 'keyword' => 'example', 'page_id' => 2],
        ['id' => 3, 'keyword' => 'root', 'page_id' => 1],
    ];
}
