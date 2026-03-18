<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WebhookService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Webhook Worker — processes queued webhook events.
 *
 * Run via cron every minute: bin/cake webhook_worker
 * Or run as a daemon: while true; do bin/cake webhook_worker; sleep 5; done
 */
class WebhookWorkerCommand extends Command
{
    public static function defaultName(): string
    {
        return 'webhook_worker';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Process queued webhook events from tmp/webhook_queue/.');

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $queueDir = TMP . 'webhook_queue' . DS;
        if (!is_dir($queueDir)) {
            $io->verbose('No queue directory found. Nothing to process.');

            return self::CODE_SUCCESS;
        }

        $files = glob($queueDir . 'wh_*.json');
        if (empty($files)) {
            $io->verbose('Queue empty.');

            return self::CODE_SUCCESS;
        }

        $processed = 0;
        $failed = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                $failed++;
                continue;
            }
            $data = json_decode($content, true);
            if (!$data || empty($data['event'])) {
                @unlink($file);
                $failed++;
                continue;
            }

            // Delete BEFORE processing to prevent duplicate delivery on crash
            @unlink($file);

            WebhookService::processEvent(
                $data['event'],
                $data['payload'] ?? []
            );
            $processed++;
        }

        $io->out("Processed {$processed} webhooks, {$failed} failed.");

        return self::CODE_SUCCESS;
    }
}
