<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class AuditLogTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('audit_log');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->scalar('action')->maxLength('action', 50)->requirePresence('action', 'create');
        $validator->scalar('entity_type')->maxLength('entity_type', 50)->requirePresence('entity_type', 'create');
        $validator->integer('entity_id')->requirePresence('entity_id', 'create');
        $validator->scalar('details')->maxLength('details', 2000)->allowEmptyString('details');
        $validator->scalar('ip_address')->maxLength('ip_address', 45)->allowEmptyString('ip_address');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('user_id', 'Users'));
        return $rules;
    }
}
