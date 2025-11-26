<?php

namespace Dshovchko\ImageMigrate\SnapGrab;

class DownloadedImage
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $mime,
        public readonly ?string $extension
    ) {
    }
}
