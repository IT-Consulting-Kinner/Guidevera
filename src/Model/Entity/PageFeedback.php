<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Page Feedback Entity — stores user feedback (thumbs up/down + optional comment).
 */
class PageFeedback extends Entity
{
    protected array $_accessible = [
        'page_id' => true, 'rating' => true, 'comment' => true,
        'client_ip' => false, 'status' => false,
    ];
}
