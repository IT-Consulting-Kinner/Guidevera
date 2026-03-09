<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Pagesindex Entity
 *
 * Represents a keyword-to-page mapping in the keyword index.
 *
 * @package App\Model\Entity
 * @property int $id
 * @property string $keyword
 * @property int $page_id
 */
class Pagesindex extends Entity
{
    protected array $_accessible = [
        'keyword' => true,
        'page_id' => true,
    ];
}
