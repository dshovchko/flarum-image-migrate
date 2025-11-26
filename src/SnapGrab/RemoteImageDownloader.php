<?php

namespace Dshovchko\ImageMigrate\SnapGrab;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class RemoteImageDownloader
{
    private const CHUNK_SIZE = 8192;
    private const MAX_FILE_BYTES = 20_971_520; // 20 MB safety limit
    private const CONTENT_TYPE_EXTENSION_MAP = [
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
    ];

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

        $bytesWritten = 0;
        while (!$stream->eof()) {
            $chunk = $stream->read(self::CHUNK_SIZE);
            if ($chunk === '' || $chunk === false) {
                if ($chunk === false || !$stream->eof()) {
                    fclose($handle);
                    @unlink($tempFile);
                    throw new SnapGrabException('Failed to read from the remote stream.');
                }

                continue;
            }

            $written = fwrite($handle, $chunk);
            if ($written === false || $written !== strlen($chunk)) {
                fclose($handle);
                @unlink($tempFile);
                throw new SnapGrabException('Failed to write downloaded bytes to disk.');
            }

            $bytesWritten += $written;
            if ($bytesWritten > self::MAX_FILE_BYTES) {
                fclose($handle);
                @unlink($tempFile);
                throw new SnapGrabException('Downloaded file exceeds the allowed size limit.');
            }
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
            return $this->mapContentTypeToExtension($contentType);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            if ($detected) {
                return $this->mapContentTypeToExtension($detected);
            }
        } else {
            $this->logger->warning('Unable to create finfo resource to detect downloaded MIME type.', ['url' => $url]);
        }

        return null;
    }

    private function mapContentTypeToExtension(?string $contentType): ?string
    {
        if ($contentType === null) {
            return null;
        }

        $lower = strtolower(trim($contentType));

        return self::CONTENT_TYPE_EXTENSION_MAP[$lower] ?? null;
    }
}
