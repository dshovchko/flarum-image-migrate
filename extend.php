<?php

namespace Dshovchko\ImageMigrate;

use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Settings())
        ->serializeToForum('dshovchko-image-migrate.allowed_origins', 'dshovchko-image-migrate.allowed_origins')
        ->serializeToForum('dshovchko-image-migrate.scheduled_enabled', 'dshovchko-image-migrate.scheduled_enabled', 'boolval')
        ->serializeToForum('dshovchko-image-migrate.scheduled_frequency', 'dshovchko-image-migrate.scheduled_frequency')
        ->serializeToForum('dshovchko-image-migrate.scheduled_chunk', 'dshovchko-image-migrate.scheduled_chunk', 'intval')
        ->serializeToForum('dshovchko-image-migrate.scheduled_emails', 'dshovchko-image-migrate.scheduled_emails'),

    (new Extend\Console())
        ->command(Console\CheckImagesCommand::class)
        ->schedule(Console\CheckImagesCommand::class, Console\CheckImagesSchedule::class),

    (new Extend\Routes('api'))
        ->get('/image-migrate/check', 'image-migrate.check', Api\Controller\CheckImagesController::class)
        ->get('/image-migrate/check-discussion/{id}', 'image-migrate.check-discussion', Api\Controller\CheckDiscussionController::class)
        ->get('/image-migrate/check-post/{id}', 'image-migrate.check-post', Api\Controller\CheckPostController::class),
];
