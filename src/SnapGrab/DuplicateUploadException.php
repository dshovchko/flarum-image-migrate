<?php

namespace Dshovchko\ImageMigrate\SnapGrab;

class DuplicateUploadException extends SnapGrabException
{
    public function __construct(
        private readonly string $optimizedUrl,
        private readonly ?string $optimizedKey = null,
        private readonly ?string $originalUrl = null,
        \Throwable $previous = null
    ) {
        parent::__construct('SnapGrab already stores this image.', 0, $previous);
    }

    public function getOptimizedUrl(): string
    {
        return $this->optimizedUrl;
    }

    public function getOptimizedKey(): ?string
    {
        return $this->optimizedKey;
    }

    public function getOriginalUrl(): ?string
    {
        return $this->originalUrl;
    }
}
