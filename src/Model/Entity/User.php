<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * User Entity
 *
 * Represents a user account. The password field is hidden from
 * JSON/array serialization via $_hidden.
 *
 * @package App\Model\Entity
 * @property int $id
 * @property string $gender male|female
 * @property string $username
 * @property string $password Bcrypt hash (hidden from serialization)
 * @property string $fullname
 * @property string $email
 * @property string $role admin|user
 * @property int $change_password 1 = must change on next login
 * @property string $page_tree JSON string of tree open/closed state
 * @property string $status active|inactive|deleted
 */
class User extends Entity
{
    protected array $_accessible = [
        'gender' => true,
        'username' => true,
        'password' => false,
        'fullname' => true,
        'email' => true,
        'role' => false,
        'status' => false,
        'change_password' => false,
        'page_tree' => true,
        'notify_mentions' => true,
        'preferences' => true,
    ];

    protected array $_hidden = [
        'password',
    ];

}
