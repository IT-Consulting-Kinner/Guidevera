<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class MediaFilesTable extends Table {
    public function initialize(array $config): void {
        parent::initialize($config);
        $this->setTable('media_files');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Users', ['foreignKey' => 'uploaded_by']);
    }
}
