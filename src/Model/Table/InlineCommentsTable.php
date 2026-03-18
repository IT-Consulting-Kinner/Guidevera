<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class InlineCommentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('inline_comments');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('comment')
            ->maxLength('comment', 2000)
            ->notEmptyString('comment');
        $validator
            ->scalar('anchor')
            ->maxLength('anchor', 100)
            ->allowEmptyString('anchor');
        $validator
            ->integer('page_id')
            ->requirePresence('page_id', 'create');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('page_id', 'Pages'));
        $rules->add($rules->existsIn('user_id', 'Users'));
        return $rules;
    }
}
