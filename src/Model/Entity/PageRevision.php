<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Page Revision Entity — stores a historical snapshot of a page.
 */
class PageRevision extends Entity
{
    protected array $_accessible = [
        'page_id' => true, 'title' => true, 'description' => true,
        'content' => true, 'created_by' => true, 'revision_note' => true,
    ];
}
