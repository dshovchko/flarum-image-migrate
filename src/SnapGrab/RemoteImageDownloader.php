<?php

namespace Dshovchko\ImageMigrate\SnapGrab;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class RemoteImageDownloader
{
    private Client $http;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->http = new Client([
            'timeout' => 60,
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => true,
            'verify' => true,
        ]);
    }

    /**
     * @throws SnapGrabException
     */
    public function download(string $url): DownloadedImage
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'img-migrate-');
        if ($tempFile === false) {
            throw new SnapGrabException('Unable to create temporary file for download.');
        }

        try {
            $response = $this->http->request('GET', $url, ['stream' => true]);
        } catch (GuzzleException $e) {
            @unlink($tempFile);
            $this->logger->error('Failed to download image', ['url' => $url, 'error' => $e->getMessage()]);
            throw new SnapGrabException('Failed to download image: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() >= 400) {
            @unlink($tempFile);
            $message = sprintf('Download failed with status %s', $response->getStatusCode());
            $this->logger->warning($message, ['url' => $url]);
            throw new SnapGrabException($message);
        }

        $stream = $response->getBody();
        $handle = fopen($tempFile, 'w+b');
        if ($handle === false) {
            @unlink($tempFile);
            throw new SnapGrabException('Unable to open temp file for writing.');
        }

        while (!$stream->eof()) {
            fwrite($handle, $stream->read(8192));
        }

        fclose($handle);

        $contentType = $response->getHeaderLine('Content-Type') ?: null;
        $extension = $this->guessExtension($url, $contentType, $tempFile);

        return new DownloadedImage($tempFile, $contentType, $extension);
    }

    private function guessExtension(string $url, ?string $contentType, string $filePath): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = $path ? pathinfo($path, PATHINFO_EXTENSION) : null;

        if ($extension) {
            return strtolower($extension);
        }

        if ($contentType) {
            return match (strtolower($contentType)) {
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                default => null,
            };
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($detected) {
                return match ($detected) {
                    'image/webp' => 'webp',
                    'image/avif' => 'avif',
                    'image/png' => 'png',
                    'image/jpeg' => 'jpg',
                    default => null,
                };
            }
        }

        return null;
    }
}
