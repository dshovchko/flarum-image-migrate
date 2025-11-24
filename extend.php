<?php

namespace Dshovchko\ImageMigrate;

use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),



    (new Extend\Console())
        ->command(Console\CheckImagesCommand::class)
        ->command(Console\ScheduledCheckImagesCommand::class)
        ->schedule(Console\ScheduledCheckImagesCommand::class, function ($event) {
            $command = resolve(Console\ScheduledCheckImagesCommand::class);
            if ($command->isEnabled()) {
                $command->schedule($event);
            }
        }),

    (new Extend\Routes('api'))
        ->get('/image-migrate/check', 'image-migrate.check', Api\Controller\CheckImagesController::class)
        ->get('/image-migrate/check-discussion/{id}', 'image-migrate.check-discussion', Api\Controller\CheckDiscussionController::class)
        ->get('/image-migrate/check-post/{id}', 'image-migrate.check-post', Api\Controller\CheckPostController::class),
];
