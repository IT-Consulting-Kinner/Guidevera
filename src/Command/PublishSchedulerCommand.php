<?php
declare(strict_types=1);
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;

/**
 * Publish Scheduler — auto-publish and auto-expire pages.
 * Run via cron: bin/cake publish-scheduler (every 5 min or hourly)
 */
class PublishSchedulerCommand extends Command
{
    public static function defaultName(): string { return 'publish-scheduler'; }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription('Process scheduled publishing and expiration of pages.');
        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $pages = $this->fetchTable('Pages');
        $now = new DateTime();
        $published = $expired = $dueSent = 0;

        // Auto-publish: publish_at <= now AND status = inactive AND workflow_status = draft
        $toPublish = $pages->find()->where([
            'publish_at IS NOT' => null, 'publish_at <=' => $now,
            'status' => 'inactive', 'deleted_at IS' => null,
        ])->all();
        foreach ($toPublish as $p) {
            $pages->updateAll(['status' => 'active', 'workflow_status' => 'published', 'publish_at' => null], ['id' => $p->id]);
            $published++;
            $io->out("Published: #{$p->id} {$p->title}");
        }

        // Auto-expire: expire_at <= now AND status = active
        $toExpire = $pages->find()->where([
            'expire_at IS NOT' => null, 'expire_at <=' => $now,
            'status' => 'active', 'deleted_at IS' => null,
        ])->all();
        foreach ($toExpire as $p) {
            $pages->updateAll(['status' => 'inactive', 'workflow_status' => 'archived', 'expire_at' => null], ['id' => $p->id]);
            $expired++;
            $io->out("Expired: #{$p->id} {$p->title}");
        }

        // Review due reminders: review_due_at <= now (log, don't auto-change)
        $due = $pages->find()->where([
            'review_due_at IS NOT' => null, 'review_due_at <=' => $now, 'deleted_at IS' => null,
        ])->all();
        foreach ($due as $p) {
            $dueSent++;
            $io->out("Review due: #{$p->id} {$p->title}");
        }

        if ($published || $expired || $dueSent) {
            \App\Service\PagesService::invalidateCache();
        }

        $io->success("Done: {$published} published, {$expired} expired, {$dueSent} reviews due.");
        return self::CODE_SUCCESS;
    }
}
