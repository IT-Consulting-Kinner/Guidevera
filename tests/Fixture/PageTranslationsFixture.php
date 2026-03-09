<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PageTranslationsFixture extends TestFixture
{
    public string $table = 'page_translations';

    public array $records = [
        [
            'id' => 1,
            'page_id' => 1,
            'locale' => 'de',
            'title' => 'Startseite',
            'description' => 'Beschreibung auf Deutsch',
            'content' => '<p>Deutscher Inhalt</p>',
            'modified_by' => 1,
            'modified' => '2025-01-15 12:00:00',
        ],
    ];
}
