<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class PageAcknowledgement extends Entity
{
    protected array $_accessible = [
        'page_id' => true,
        'locale' => true,
        'revision_id' => false,
        // user_id, confirmed_at: set server-side only
    ];
}
