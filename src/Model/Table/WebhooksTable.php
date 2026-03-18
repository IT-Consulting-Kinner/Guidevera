<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class WebhooksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('webhooks');
        $this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created' => 'new']]]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->url('url', 'Please enter a valid URL.')
            ->requirePresence('url', 'create')
            ->notEmptyString('url')
            ->add('url', 'httpsOnly', [
                'rule' => function ($value) {
                    if (!str_starts_with((string)$value, 'https://')) {
                        return false;
                    }
                    $host = parse_url((string)$value, PHP_URL_HOST);
                    if (!$host) {
                        return false;
                    }
                    // Block private/reserved IPs (SSRF protection)
                    $ip = gethostbyname($host);
                    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
                        return true; // DNS not resolved, allow — runtime will handle
                    }
                    return filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    ) !== false;
                },
                'message' => 'URL must use HTTPS and not point to private/reserved IP ranges.',
            ]);
        $validator
            ->scalar('url')
            ->maxLength('url', 500);
        $validator
            ->scalar('events')
            ->notEmptyString('events')
            ->add('events', 'validJson', [
                'rule' => function ($value) {
                    $decoded = json_decode((string)$value, true);
                    if (!is_array($decoded)) {
                        return false;
                    }
                    $allowed = ['page.created', 'page.updated', 'page.deleted', 'page.published',
                        'page.status_changed', 'comment.created', 'user.created'];
                    foreach ($decoded as $evt) {
                        if (!in_array($evt, $allowed, true)) {
                            return false;
                        }
                    }
                    return true;
                },
                'message' => 'Events must be a JSON array of valid event names.',
            ]);
        $validator
            ->scalar('events')
            ->maxLength('events', 255);
        $validator
            ->boolean('active');
        return $validator;
    }

    public function buildRules(\Cake\ORM\RulesChecker $rules): \Cake\ORM\RulesChecker
    {
        $rules->add($rules->isUnique(['url'], 'This webhook URL is already registered.'));
        return $rules;
    }
}
