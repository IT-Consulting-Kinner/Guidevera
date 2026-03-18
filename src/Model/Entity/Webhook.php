<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Webhook extends Entity
{
    protected array $_accessible = [
        'url' => true,
        'events' => true,
        'active' => false,
        // secret, created: set server-side only
    ];

    protected array $_hidden = ['secret'];
}
