<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

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
        $this->belongsTo('CreatedByUsers', ['className' => 'Users', 'foreignKey' => 'created_by',
            'propertyName' => 'creator']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->integer('page_id')->requirePresence('page_id', 'create');
        $validator->scalar('title')->maxLength('title', 255)->allowEmptyString('title');
        $validator->scalar('description')->maxLength('description', 160)->allowEmptyString('description');
        $validator->scalar('content')->allowEmptyString('content');
        $validator->scalar('revision_note')->maxLength('revision_note', 255)->allowEmptyString('revision_note');
        $validator->integer('created_by');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('page_id', 'Pages'));
        $rules->add($rules->existsIn('created_by', 'CreatedByUsers'));
        return $rules;
    }
}
