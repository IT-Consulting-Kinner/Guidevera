<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class MediaFile extends Entity
{
    protected array $_accessible = [
        'folder_id' => true,
        'filename' => false,
        'original_name' => false,
        'mime_type' => false,
        'file_size' => false,
        'display_mode' => false,
        'visible_guest' => false,
        'visible_editor' => false,
        'visible_contributor' => false,
        'visible_admin' => false,
        // All fields except folder_id are set server-side only
    ];
}
