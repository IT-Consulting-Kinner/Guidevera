<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PageTagsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('page_tags');
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('tag')
            ->maxLength('tag', 100)
            ->notEmptyString('tag');
        $validator
            ->integer('page_id')
            ->requirePresence('page_id', 'create');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('page_id', 'Pages'));
        $rules->add($rules->isUnique(['page_id', 'tag'], 'This tag already exists for this page.'));
        return $rules;
    }
}
