<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Page Translation Entity — stores translated content for a page in a specific locale.
 */
class PageTranslation extends Entity
{
    protected array $_accessible = [
        'page_id' => true, 'locale' => true, 'title' => true,
        'description' => true, 'content' => true, 'modified_by' => true,
    ];
}
