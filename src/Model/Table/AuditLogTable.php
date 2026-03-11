<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class AuditLogTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('audit_log');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }
}
