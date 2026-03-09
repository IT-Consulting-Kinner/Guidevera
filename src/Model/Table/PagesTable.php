<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Pages Table
 *
 * Represents the `pages` table — the core content storage.
 * Pages form a hierarchical tree using `parent_id` (self-referential)
 * and are ordered by the `position` column.
 *
 * ## Associations
 *
 * - **CreatedByUsers** / **ModifiedByUsers**: BelongsTo Users (audit trail)
 * - **Pagesindex**: HasMany keywords (dependent delete)
 * - **ParentPages** / **ChildPages**: Self-referential tree structure
 *
 * ## Tree Management
 *
 * This table does NOT use CakePHP's TreeBehavior. The tree is managed
 * manually through `parent_id` and `position` columns. The helper method
 * `findChildrenOf()` replaces TreeBehavior's `find('children')`.
 *
 * @package App\Model\Table
 * @method \App\Model\Entity\Page newEmptyEntity()
 * @method \App\Model\Entity\Page get(mixed $primaryKey, array|string $finder = 'all', ...\Cake\ORM\Query\SelectQuery $options)
 */
class PagesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('pages');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                    'modified' => 'always',
                ],
            ],
        ]);

        $this->belongsTo('CreatedByUsers', [
            'className' => 'Users',
            'foreignKey' => 'created_by',
            'propertyName' => 'creator',
        ]);

        $this->belongsTo('ModifiedByUsers', [
            'className' => 'Users',
            'foreignKey' => 'modified_by',
            'propertyName' => 'modifier',
        ]);

        $this->hasMany('Pagesindex', [
            'foreignKey' => 'page_id',
            'dependent' => true,
        ]);

        // Self-referential: parent/children
        $this->belongsTo('ParentPages', [
            'className' => 'Pages',
            'foreignKey' => 'parent_id',
            'propertyName' => 'parent_page',
        ]);

        $this->hasMany('ChildPages', [
            'className' => 'Pages',
            'foreignKey' => 'parent_id',
            'propertyName' => 'child_pages',
        ]);
    }

    /**
     * Find all children of a page (replaces Tree-Behavior find('children')).
     */
    public function findChildrenOf(int $parentId): \Cake\ORM\Query\SelectQuery
    {
        return $this->find()->where(['parent_id' => $parentId]);
    }

    public function validationDefault(Validator $validator): Validator
    {
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
            ->scalar('status')
            ->inList('status', ['active', 'inactive']);

        $validator
            ->integer('position')
            ->allowEmptyString('position');

        return $validator;
    }
}

// Note: In PagesTable, add soft-delete awareness
// PagesController queries should add ->where(['deleted_at IS' => null])
// which is already done in the new v9 code.
