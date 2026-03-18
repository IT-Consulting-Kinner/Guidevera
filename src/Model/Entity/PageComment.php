<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class PageComment extends Entity
{
    protected array $_accessible = [
        'page_id' => true,
        'comment' => true,
        // user_id, created: set server-side only
    ];
}
