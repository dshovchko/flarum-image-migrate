<?php

namespace Dshovchko\ImageMigrate\Model;

use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    protected $table = 'dshovchko_image_migrate';

    public $timestamps = false;

    protected $fillable = [
        'discussion_id',
        'post_id',
        'original_url',
        'new_url',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
