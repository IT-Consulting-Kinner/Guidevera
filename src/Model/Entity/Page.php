<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Page Entity
 *
 * Represents a single page in the documentation tree.
 * Mass-assignable fields are whitelisted in $_accessible.
 *
 * ## Virtual Properties
 *
 * - `keywords`: Loaded dynamically by PagesService::loadKeywords()
 * - `chapter`: Set by PagesService::calculateChapterNumbering()
 * - `creator`: Loaded via CreatedByUsers association
 * - `modifier`: Loaded via ModifiedByUsers association
 *
 * @package App\Model\Entity
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $content
 * @property string $status active|inactive
 * @property int $position Sort order within siblings
 * @property int|null $parent_id Parent page ID (null = root level)
 * @property int $views Download/view counter
 * @property int $created_by User ID who created this page
 * @property int $modified_by User ID who last modified this page
 */
class Page extends Entity
{
    protected array $_accessible = [
        'parent_id' => true,
        'position' => true,
        'title' => true,
        'description' => true,
        'content' => true,
        'status' => false,
        'workflow_status' => false,
        'publish_at' => true,
        'expire_at' => true,
        'review_due_at' => true,
        'requires_ack' => true,
        'locale' => true,
        // created_by, modified_by, views, deleted_at: set server-side only
    ];
}
