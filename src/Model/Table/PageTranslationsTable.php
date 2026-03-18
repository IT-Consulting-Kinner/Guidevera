<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PageTranslations Table — stores page content in different locales.
 *
 * Each page has one row per locale. The 'default' locale content
 * lives in the pages table itself; translations override it.
 */
class PageTranslationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('page_translations');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['modified' => 'always']]]);
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('locale')
            ->maxLength('locale', 10)
            ->notEmptyString('locale');
        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->allowEmptyString('title');
        $validator
            ->scalar('description')
            ->maxLength('description', 160)
            ->allowEmptyString('description');
        $validator
            ->scalar('content')
            ->allowEmptyString('content');
        $validator
            ->integer('page_id')
            ->requirePresence('page_id', 'create');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('page_id', 'Pages'));
        $rules->add($rules->isUnique(['page_id', 'locale'], 'A translation for this locale already exists.'));
        return $rules;
    }
}
