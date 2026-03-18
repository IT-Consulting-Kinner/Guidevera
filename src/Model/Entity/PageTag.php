<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class PageTag extends Entity
{
    protected array $_accessible = [
        'page_id' => true,
        'tag' => true,
    ];
}
