<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * PageRevisions Table — version history for pages.
 *
 * Each row is an immutable snapshot. Revisions are created by
 * PagesController::save() before overwriting the current content.
 */
class PageRevisionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('page_revisions');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
        $this->belongsTo('CreatedByUsers', ['className' => 'Users', 'foreignKey' => 'created_by', 'propertyName' => 'creator']);
    }
}
