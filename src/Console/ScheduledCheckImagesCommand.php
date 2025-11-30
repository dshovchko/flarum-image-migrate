<?php

namespace Dshovchko\ImageMigrate\Console;

use Dshovchko\ImageMigrate\Service\ImageChecker;
use Dshovchko\ImageMigrate\Service\ImageMigrator;
use Dshovchko\ImageMigrate\Service\ReportMailer;
use Dshovchko\ImageMigrate\SnapGrab\SnapGrabClient;
use Dshovchko\ImageMigrate\SnapGrab\RemoteImageDownloader;
use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Console\Scheduling\Event;

class ScheduledCheckImagesCommand extends CheckImagesCommand
{
    protected $settings;

    public function __construct(
        ImageChecker $checker,
        ReportMailer $mailer,
        ImageMigrator $migrator,
        SnapGrabClient $snapGrabClient,
        RemoteImageDownloader $downloader,
        Config $config,
        SettingsRepositoryInterface $settings
    ) {
        parent::__construct($checker, $mailer, $migrator, $snapGrabClient, $downloader, $config);
        $this->settings = $settings;
    }

    protected function configure()
    {
        parent::configure();
        $this->setName('image-migrate:scheduled-check')
             ->setDescription('Scheduled automatic check for external images (configured via admin panel)');
    }

    protected function fire()
    {
        if (!$this->isEnabled()) {
            $this->info('Scheduled external images checks are disabled.');
            return;
        }

        $this->info('Running scheduled external images check...');
        
        $this->input->setOption('all', true);
        
        $emails = $this->getEmailRecipients();
        if ($emails) {
            $this->input->setOption('mailto', $emails);
        }

        parent::fire();
        $this->info('Scheduled check completed.');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('dshovchko-image-migrate.scheduled_enabled', false);
    }

    public function getEmailRecipients(): string
    {
        return $this->settings->get('dshovchko-image-migrate.scheduled_emails', '');
    }

    public function schedule(Event $event): void
    {
        $frequency = $this->settings->get('dshovchko-image-migrate.scheduled_frequency', 'weekly');
        
        switch ($frequency) {
            case 'daily':
                $event->daily();
                break;
            case 'weekly':
                $event->weekly();
                break;
            case 'monthly':
                $event->monthly();
                break;
            default:
                $event->weekly();
                break;
        }
    }
}
