<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class PageTagsTable extends Table {
    public function initialize(array $config): void {
        parent::initialize($config);
        $this->setTable('page_tags');
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
    }
}
