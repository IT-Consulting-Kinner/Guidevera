<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class PageCommentsTable extends Table {
    public function initialize(array $config): void {
        parent::initialize($config);
        $this->setTable('page_comments');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
        $this->belongsTo('Users', ['foreignKey' => 'user_id']);
    }
}
