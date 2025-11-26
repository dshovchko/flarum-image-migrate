<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('dshovchko_image_migrate')) {
            return;
        }

        $schema->create('dshovchko_image_migrate', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('discussion_id');
            $table->unsignedInteger('post_id');
            $table->text('original_url');
            $table->text('new_url');
            $table->timestamp('created_at')->useCurrent();

            $table->index('discussion_id');
            $table->index('post_id');
        });
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('dshovchko_image_migrate');
    },
];
