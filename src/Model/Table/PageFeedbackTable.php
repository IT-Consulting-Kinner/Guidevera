<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PageFeedback Table — user ratings and comments per page.
 *
 * Feedback statuses: pending (new), approved (visible), rejected (hidden).
 * Only admins can approve/reject feedback comments.
 */
class PageFeedbackTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('page_feedback');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
        $this->belongsTo('Pages', ['foreignKey' => 'page_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->integer('rating')->requirePresence('rating', 'create');
        $validator->scalar('comment')->maxLength('comment', 2000)->allowEmptyString('comment');
        $validator->inList('status', ['pending', 'approved', 'rejected']);
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->existsIn('page_id', 'Pages'));
        return $rules;
    }
}
