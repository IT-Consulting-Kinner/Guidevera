<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PageFeedbackFixture extends TestFixture
{
    public string $table = 'page_feedback';

    public array $records = [
        [
            'id' => 1,
            'page_id' => 1,
            'rating' => 1,
            'comment' => '',
            'client_ip' => '10.0.0.1',
            'status' => 'approved',
            'created' => '2025-01-10 09:00:00',
        ],
        [
            'id' => 2,
            'page_id' => 1,
            'rating' => -1,
            'comment' => 'Could be clearer',
            'client_ip' => '10.0.0.2',
            'status' => 'pending',
            'created' => '2025-01-11 15:00:00',
        ],
        [
            'id' => 3,
            'page_id' => 2,
            'rating' => 1,
            'comment' => 'Very helpful!',
            'client_ip' => '10.0.0.3',
            'status' => 'approved',
            'created' => '2025-01-12 11:00:00',
        ],
    ];
}
