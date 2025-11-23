<?php

namespace Dshovchko\ImageMigrate\Console;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Console\Scheduling\Event;

class CheckImagesSchedule
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function __invoke(Event $event): void
    {
        $enabled = $this->settings->get('dshovchko-image-migrate.scheduled_enabled', false);
        
        if (!$enabled) {
            return;
        }

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
