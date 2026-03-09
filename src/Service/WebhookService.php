<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Webhook Service — fires HTTP POST to registered webhook URLs.
 */
class WebhookService
{
    use LocatorAwareTrait;

    public static function fire(string $event, array $payload): void
    {
        if (!(Configure::read('Manual.enableWebhooks') ?? false)) return;
        try {
            $hooks = \Cake\ORM\TableRegistry::getTableLocator()->get('Webhooks')
                ->find()->where(['active' => 1])->all();

            foreach ($hooks as $hook) {
                $events = array_map('trim', explode(',', $hook->events));
                if (!in_array($event, $events) && !in_array('*', $events)) continue;

                $body = json_encode(['event' => $event, 'timestamp' => date('c'), 'data' => $payload]);
                $headers = ['Content-Type: application/json'];
                if (!empty($hook->secret)) {
                    $headers[] = 'X-Webhook-Signature: ' . hash_hmac('sha256', $body, $hook->secret);
                }

                // Non-blocking HTTP POST (fire and forget)
                $ch = curl_init($hook->url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (\Exception $e) { Log::error('Webhook fire failed: ' . $e->getMessage()); }
    }
}
