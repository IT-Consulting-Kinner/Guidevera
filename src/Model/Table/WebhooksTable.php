<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class WebhooksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('webhooks');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
    }
}
