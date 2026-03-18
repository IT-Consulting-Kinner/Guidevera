<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class MediaFoldersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('media_folders');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created' => 'new']],
        ]);
        $this->belongsTo('ParentFolders', [
            'className' => 'MediaFolders',
            'foreignKey' => 'parent_id',
        ]);
        $this->hasMany('ChildFolders', [
            'className' => 'MediaFolders',
            'foreignKey' => 'parent_id',
        ]);
        $this->hasMany('MediaFiles', ['foreignKey' => 'folder_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->notEmptyString('name', 'Folder name cannot be empty.');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('parent_id', 'ParentFolders'), ['errorField' => 'parent_id']);
        return $rules;
    }
}
