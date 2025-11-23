<?php

namespace Dshovchko\ImageMigrate\Service;

use Flarum\Post\Post;
use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Collection;

class ImageChecker
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function checkAllPosts(): array
    {
        $posts = Post::where('type', 'comment')->get();
        return $this->checkPosts($posts);
    }

    public function checkDiscussion(Discussion $discussion): array
    {
        $posts = $discussion->posts()->where('type', 'comment')->get();
        return $this->checkPosts($posts);
    }

    public function checkPost(Post $post): array
    {
        return $this->checkPosts(collect([$post]));
    }

    protected function checkPosts(Collection $posts): array
    {
        $allowedOrigins = $this->getAllowedOrigins();
        $externalImages = [];

        foreach ($posts as $post) {
            $images = $this->extractImages($post->getParsedContentAttribute());
            
            foreach ($images as $imageUrl) {
                if (!$this->isAllowedOrigin($imageUrl, $allowedOrigins)) {
                    $externalImages[] = [
                        'post_id' => $post->id,
                        'discussion_id' => $post->discussion_id,
                        'image_url' => $imageUrl,
                        'post_number' => $post->number,
                    ];
                }
            }
        }

        return $externalImages;
    }

    protected function extractImages(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);

        $flags = LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $dom->loadHTML($this->prepareHtmlFragment($content), $flags);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (!$loaded) {
            return [];
        }

        $images = [];
        $nodes = $dom->getElementsByTagName('img');
        foreach ($nodes as $node) {
            if ($node->hasAttribute('src')) {
                $images[] = $node->getAttribute('src');
            }
        }

        return array_unique($images);
    }

    protected function prepareHtmlFragment(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '<?xml')) {
            $trimmed = (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $trimmed);
        }

        return '<?xml encoding="UTF-8">'.$trimmed;
    }

    protected function getAllowedOrigins(): array
    {
        $origins = $this->settings->get('dshovchko-image-migrate.allowed_origins', '');
        return array_filter(array_map('trim', explode(',', $origins)));
    }

    protected function isAllowedOrigin(string $url, array $allowedOrigins): bool
    {
        if (empty($allowedOrigins)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return true; // Relative URLs are considered internal
        }

        $host = strtolower($parsedUrl['host']);
        
        foreach ($allowedOrigins as $origin) {
            $origin = trim($origin);
            if (empty($origin)) continue;
            
            // Remove protocol if present
            $origin = preg_replace('#^https?://#', '', $origin);
            $origin = strtolower($origin);
            
            // Remove www. prefix from both for comparison
            $hostClean = preg_replace('/^www\./', '', $host);
            $originClean = preg_replace('/^www\./', '', $origin);
            
            // Check exact match or subdomain match
            if ($hostClean === $originClean || str_ends_with($hostClean, '.' . $originClean)) {
                return true;
            }
        }

        return false;
    }
}
