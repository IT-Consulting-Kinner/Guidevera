<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class InlineComment extends Entity
{
    protected array $_accessible = [
        'page_id' => true,
        'parent_id' => true,
        'anchor' => true,
        'comment' => true,
        'resolved' => false,
        // user_id, created: set server-side only
    ];
}
