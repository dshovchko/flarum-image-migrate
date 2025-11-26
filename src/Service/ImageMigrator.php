<?php

namespace Dshovchko\ImageMigrate\Service;

use Carbon\Carbon;
use Dshovchko\ImageMigrate\Model\MigrationLog;
use Dshovchko\ImageMigrate\SnapGrab\RemoteImageDownloader;
use Dshovchko\ImageMigrate\SnapGrab\SnapGrabClient;
use Dshovchko\ImageMigrate\SnapGrab\SnapGrabException;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\Config;
use Flarum\Post\CommentPost;

class ImageMigrator
{
    private const SCALE_FACTOR = 1.01;
    private const QUALITY = [
        'webp' => 75,
        'avif' => 50,
    ];
    private const EFFORT = [
        'webp' => 6,
        'avif' => 8,
    ];

    public function __construct(
        private readonly SnapGrabClient $client,
        private readonly RemoteImageDownloader $downloader,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<int, array{image_url:string, post_number?:int, discussion_id:int, post_id:int}> $images
     *
     * @throws SnapGrabException
     */
    public function migrate(CommentPost $post, array $images): void
    {
        if ($post->type !== 'comment') {
            throw new SnapGrabException('Only comment posts can be migrated.');
        }

        $content = $post->content;
        $changes = [];

        foreach ($images as $image) {
            $downloaded = $this->downloader->download($image['image_url']);

            try {
                $format = $this->determineTargetFormat($downloaded->extension);
                $options = $this->buildOptions();
                $sourceUrl = $this->buildSourceUrl($post->discussion, $image['post_number'] ?? null);

                $response = $this->client->upload($downloaded->path, $sourceUrl, $format, $options);
                $newUrl = $response['url'];
                $content = $this->replaceFirst($post, $content, $image['image_url'], $newUrl);

                $changes[] = [
                    'original_url' => $image['image_url'],
                    'new_url' => $newUrl,
                ];
            } finally {
                @unlink($downloaded->path);
            }
        }

        if ($content !== $post->content) {
            $post->content = $content;
            $post->save();
        }

        foreach ($changes as $change) {
            MigrationLog::create([
                'discussion_id' => $post->discussion_id,
                'post_id' => $post->id,
                'original_url' => $change['original_url'],
                'new_url' => $change['new_url'],
                'created_at' => Carbon::now(),
            ]);
        }
    }

    private function buildOptions(): array
    {
        return [
            'lossless' => false,
            'scaleFactor' => self::SCALE_FACTOR,
            'quality' => self::QUALITY,
            'effort' => self::EFFORT,
        ];
    }

    private function determineTargetFormat(?string $extension): string
    {
        $extension = strtolower($extension ?? '');

        return match ($extension) {
            'webp' => 'webp',
            'avif' => 'avif',
            'png' => 'webp',
            default => 'avif',
        };
    }

    private function replaceFirst(CommentPost $post, string $content, string $search, string $replacement): string
    {
        $pos = strpos($content, $search);

        if ($pos === false) {
            throw new SnapGrabException(sprintf(
                'Original image URL (%s) was not found inside post #%d.',
                $search,
                $post->id ?? 0
            ));
        }

        return substr($content, 0, $pos).$replacement.substr($content, $pos + strlen($search));
    }

    private function buildSourceUrl(?Discussion $discussion, ?int $postNumber): string
    {
        $baseUrl = rtrim((string) $this->config->url(), '/');

        if (!$discussion) {
            return $baseUrl;
        }

        return $postNumber === null
            ? sprintf('%s/d/%d', $baseUrl, $discussion->id)
            : sprintf('%s/d/%d/%d', $baseUrl, $discussion->id, $postNumber);
    }
}
