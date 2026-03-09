<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Users Table
 *
 * Represents the `users` table for authentication and user management.
 *
 * ## Soft Delete
 *
 * Users are never physically deleted. Instead, their status is set to
 * 'deleted'. This preserves referential integrity with `created_by`
 * and `modified_by` columns in the pages table.
 *
 * ## Roles
 *
 * Four roles exist: 'admin', 'contributor', 'editor' (stored in DB), and 'guest' (no account).
 *
 * @package App\Model\Table
 */
class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('users');
        $this->setDisplayField('fullname');
        $this->setPrimaryKey('id');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('username')->maxLength('username', 20)
            ->requirePresence('username', 'create')->notEmptyString('username');
        $validator
            ->scalar('fullname')->maxLength('fullname', 50)->notEmptyString('fullname');
        $validator
            ->email('email')->requirePresence('email', 'create');
        $validator
            ->scalar('gender')->inList('gender', ['male', 'female']);
        $validator
            ->scalar('role')->inList('role', ['admin', 'contributor', 'editor']);
        $validator
            ->scalar('status')->inList('status', ['active', 'inactive', 'deleted']);
        $validator
            ->integer('change_password')->allowEmptyString('change_password');

        return $validator;
    }
}
