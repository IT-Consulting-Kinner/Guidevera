<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class PageReview extends Entity
{
    protected array $_accessible = [
        'page_id' => true,
        'status' => false,
        'comment' => true,
        // reviewer_id, status, created: set server-side only
    ];
}
