<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Pagesindex Table
 *
 * Stores keyword-to-page associations for the keyword index feature.
 * Each row maps one keyword to one page. A page can have multiple keywords,
 * and a keyword can reference multiple pages.
 *
 * Keywords are managed by PagesController::_saveKeywords() which does
 * a delete-all + re-insert on each page save.
 *
 * @package App\Model\Table
 */
class PagesindexTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('pagesindex');
        $this->setDisplayField('keyword');
        $this->setPrimaryKey('id');

        $this->belongsTo('Pages', [
            'foreignKey' => 'page_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('keyword')
            ->notEmptyString('keyword');

        $validator
            ->integer('page_id')
            ->requirePresence('page_id', 'create');

        return $validator;
    }
}
