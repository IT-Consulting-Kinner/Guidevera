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
 * @method \App\Model\Entity\Page get(mixed $primaryKey, array|string $finder = 'all',
 *     ...\Cake\ORM\Query\SelectQuery $options)
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
        return $this->find()->where([
            'parent_id' => $parentId,
            'deleted_at IS' => null,
        ]);
    }

    /**
     * Automatically exclude soft-deleted records from all queries.
     * Pass 'withDeleted' => true in finder options to include deleted records.
     */
    public function beforeFind(
        \Cake\Event\EventInterface $event,
        \Cake\ORM\Query\SelectQuery $query,
        \ArrayObject $options
    ): void {
        if (empty($options['withDeleted'])) {
            $query->where([$this->getAlias() . '.deleted_at IS' => null]);
        }
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->notEmptyString('title', 'Title cannot be empty.', 'update');

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

        $validator
            ->scalar('workflow_status')
            ->inList('workflow_status', ['draft', 'review', 'published', 'archived'])
            ->allowEmptyString('workflow_status');

        $validator
            ->dateTime('publish_at')
            ->allowEmptyDateTime('publish_at');

        $validator
            ->dateTime('expire_at')
            ->allowEmptyDateTime('expire_at');

        $validator
            ->dateTime('review_due_at')
            ->allowEmptyDateTime('review_due_at');

        $validator
            ->boolean('requires_ack')
            ->allowEmptyString('requires_ack');

        $validator
            ->scalar('locale')
            ->maxLength('locale', 10)
            ->add('locale', 'validLocale', [
                'rule' => function ($value) {
                    return (bool)preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', (string)$value);
                },
                'message' => 'Locale must be a valid format (e.g. en, de, en_US).',
            ])
            ->allowEmptyString('locale');

        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('parent_id', 'ParentPages'), ['errorField' => 'parent_id']);
        $rules->add($rules->existsIn('created_by', 'CreatedByUsers'), ['errorField' => 'created_by']);
        $rules->add($rules->existsIn('modified_by', 'ModifiedByUsers'), ['errorField' => 'modified_by']);

        return $rules;
    }
}