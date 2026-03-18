<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class MediaFilesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('media_files');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Users', ['foreignKey' => 'uploaded_by']);
        $this->belongsTo('MediaFolders', ['foreignKey' => 'folder_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->allowEmptyString('filename', null, 'create')
            ->notEmptyString('filename', null, 'update');
        $validator
            ->scalar('original_name')
            ->maxLength('original_name', 255);
        $validator
            ->scalar('mime_type')
            ->maxLength('mime_type', 100);
        $validator
            ->nonNegativeInteger('file_size');
        $validator
            ->scalar('display_mode')
            ->inList('display_mode', ['inline', 'attachment', 'download', 'gallery'])
            ->allowEmptyString('display_mode');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('folder_id', 'MediaFolders'));
        $rules->add($rules->existsIn('uploaded_by', 'Users'));
        return $rules;
    }
}
