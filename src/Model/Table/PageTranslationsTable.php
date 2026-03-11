<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

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
}
