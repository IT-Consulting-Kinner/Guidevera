<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PageSubscriptionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('page_subscriptions');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->integer('page_id')->requirePresence('page_id', 'create');
        $validator->integer('user_id');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('page_id', 'Pages'));
        $rules->add($rules->existsIn('user_id', 'Users'));
        $rules->add($rules->isUnique(['page_id', 'user_id'], 'User is already subscribed to this page.'));
        return $rules;
    }
}
