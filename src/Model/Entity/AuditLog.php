<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class AuditLog extends Entity
{
    protected array $_accessible = [
        'action' => false,
        'entity_type' => false,
        'entity_id' => false,
        'details' => false,
        // All fields set server-side only via set()
    ];
}
