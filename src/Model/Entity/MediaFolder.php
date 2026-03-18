<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class MediaFolder extends Entity
{
    protected array $_accessible = [
        'parent_id' => true,
        'name' => true,
        // created_by, created: set server-side only
    ];
}
