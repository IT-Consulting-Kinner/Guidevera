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

    /**
     * Validate that a webhook URL is safe (no SSRF).
     * Only allows https:// to public, non-internal IP addresses.
     */
    public static function isUrlSafe(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }
        // Only allow https (or http for local dev when debug is on)
        $allowedSchemes = ['https'];
        if (Configure::read('debug')) {
            $allowedSchemes[] = 'http';
        }
        if (!in_array(strtolower($parsed['scheme']), $allowedSchemes)) {
            return false;
        }
        // Block internal/private IP ranges
        $host = strtolower($parsed['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return false;
        }
        // Resolve hostname and check for private IPs
        $ips = gethostbynamel($host);
        if (!$ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Enqueue webhook events for asynchronous delivery.
     *
     * Instead of blocking the HTTP request, events are written to
     * tmp/webhook_queue/ and processed by: bin/cake webhook_worker
     */
    public static function fire(string $event, array $payload): void
    {
        if (!(Configure::read('Manual.enableWebhooks') ?? false)) {
            return;
        }
        try {
            $queueDir = TMP . 'webhook_queue' . DS;
            if (!is_dir($queueDir)) {
                mkdir($queueDir, 0750, true);
            }
            $file = $queueDir . 'wh_' . bin2hex(random_bytes(16)) . '.json';
            $data = json_encode([
                'event' => $event,
                'payload' => $payload,
                'queued_at' => date('c'),
            ]);
            file_put_contents($file, $data, LOCK_EX);
        } catch (\Exception $e) {
            Log::error('Webhook enqueue failed: ' . $e->getMessage());
        }
    }

    /**
     * Process a single queued webhook event — called by WebhookWorkerCommand.
     */
    public static function processEvent(string $event, array $payload): void
    {
        try {
            $hooks = \Cake\ORM\TableRegistry::getTableLocator()->get('Webhooks')
                ->find()->where(['active' => 1])->all();

            foreach ($hooks as $hook) {
                $decoded = json_decode($hook->events, true);
                $events = is_array($decoded) ? $decoded : array_map('trim', explode(',', $hook->events));
                if (!in_array($event, $events) && !in_array('*', $events)) {
                    continue;
                }

                if (!self::isUrlSafe($hook->url)) {
                    Log::warning('Webhook blocked (SSRF protection): ' . $hook->url);
                    continue;
                }

                // Pin resolved IP to prevent DNS rebinding (TOCTOU)
                $parsed = parse_url($hook->url);
                $ips = gethostbynamel($parsed['host'] ?? '');
                $resolveEntry = [];
                if ($ips) {
                    $port = $parsed['port'] ?? (($parsed['scheme'] ?? '') === 'https' ? 443 : 80);
                    $resolveEntry = [CURLOPT_RESOLVE => ["{$parsed['host']}:{$port}:{$ips[0]}"]];
                }

                $body = json_encode([
                    'event' => $event,
                    'timestamp' => date('c'),
                    'data' => $payload,
                ]);
                $headers = ['Content-Type: application/json'];
                if (!empty($hook->secret)) {
                    $headers[] = 'X-Webhook-Signature: '
                        . hash_hmac('sha256', $body, $hook->secret);
                }

                $ch = curl_init($hook->url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_NOSIGNAL => true,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                    CURLOPT_FOLLOWLOCATION => false,
                ] + $resolveEntry);
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 400 || $result === false) {
                    Log::warning(
                        "Webhook delivery failed: {$hook->url} HTTP {$httpCode}"
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage());
        }
    }
}
