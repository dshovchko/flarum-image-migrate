<?php

namespace Dshovchko\ImageMigrate\SnapGrab;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use JsonException;
use Psr\Log\LoggerInterface;

class SnapGrabClient
{
    private Client $http;

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly LoggerInterface $logger
    ) {
        $this->http = new Client([
            'timeout' => 30,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    public function normalizeBaseUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = rtrim(trim($value), '/');

        return $trimmed !== '' ? $trimmed : null;
    }

    public function getBaseUrl(): ?string
    {
        return $this->normalizeBaseUrl($this->settings->get('dshovchko-image-migrate.snapgrab_base_url'));
    }

    public function getApiKey(): ?string
    {
        $key = trim((string) $this->settings->get('dshovchko-image-migrate.snapgrab_api_key'));

        return $key !== '' ? $key : null;
    }

    public function getEnvironment(): string
    {
        $env = $this->settings->get('dshovchko-image-migrate.snapgrab_env', 'production');

        return in_array($env, ['integration', 'production'], true) ? $env : 'production';
    }

    public function isConfigured(): bool
    {
        return $this->getBaseUrl() && $this->getApiKey();
    }

    /**
     * @throws SnapGrabException
     */
    public function ensureConfigured(): void
    {
        if (!$this->getBaseUrl() || !$this->getApiKey()) {
            throw new SnapGrabException('SnapGrab backend is not configured. Set Base URL and API Key first.');
        }
    }

    /**
     * @throws HealthCheckException
     */
    public function healthCheck(?string $overrideBaseUrl = null): void
    {
        $baseUrl = $this->normalizeBaseUrl($overrideBaseUrl ?? $this->getBaseUrl());

        if (!$baseUrl) {
            throw new HealthCheckException('SnapGrab base URL is not configured.');
        }

        $url = $baseUrl.'/health';

        try {
            $response = $this->http->request('GET', $url);
        } catch (GuzzleException $e) {
            $this->logger->error('SnapGrab health check failed', ['url' => $url, 'error' => $e->getMessage()]);
            throw new HealthCheckException('Unable to reach SnapGrab backend.', 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new HealthCheckException('SnapGrab backend responded with HTTP '.$response->getStatusCode());
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload) || Arr::get($payload, 'status') !== 'ok') {
            throw new HealthCheckException('SnapGrab backend reported non-ok status.');
        }
    }

    /**
     * @throws SnapGrabException
     */
    public function upload(string $filePath, string $sourceUrl, string $format, array $options): array
    {
        $this->ensureConfigured();

        $endpoint = $this->getBaseUrl().'/upload';
        $fileResource = fopen($filePath, 'r');
        if ($fileResource === false) {
            throw new SnapGrabException('Unable to open downloaded file for upload.');
        }

        try {
            $optionsJson = json_encode($options, JSON_THROW_ON_ERROR);

            $body = [
                [
                    'name' => 'file',
                    'contents' => $fileResource,
                    'filename' => basename($filePath),
                ],
                [
                    'name' => 'sourceUrl',
                    'contents' => $sourceUrl,
                ],
                [
                    'name' => 'targetEnv',
                    'contents' => $this->getEnvironment(),
                ],
                [
                    'name' => 'format',
                    'contents' => $format,
                ],
                [
                    'name' => 'options',
                    'contents' => $optionsJson,
                ],
            ];

            $response = $this->http->request('POST', $endpoint, [
                'headers' => [
                    'x-snapgrab-key' => $this->getApiKey(),
                    'Accept' => 'application/json',
                ],
                'multipart' => $body,
            ]);

            if (!in_array($response->getStatusCode(), [200, 201], true)) {
                $message = sprintf('Upload failed with HTTP %s', $response->getStatusCode());
                $this->logger->warning($message, ['endpoint' => $endpoint]);
                $responseBody = (string) $response->getBody();
                $truncatedBody = strlen($responseBody) > 200 ? substr($responseBody, 0, 200).'...[truncated]' : $responseBody;
                throw new SnapGrabException($message.' - '.$truncatedBody);
            }

            $payload = json_decode((string) $response->getBody(), true);
            if (!is_array($payload) || !isset($payload['url'])) {
                throw new SnapGrabException('Upload response is invalid.');
            }

            return $payload;
        } catch (JsonException $e) {
            throw new SnapGrabException('Failed to encode upload options.', 0, $e);
        } catch (GuzzleException $e) {
            $this->logger->error('SnapGrab upload failed', ['url' => $endpoint, 'error' => $e->getMessage()]);
            throw new SnapGrabException('Upload failed: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_resource($fileResource)) {
                fclose($fileResource);
            }
        }
    }
}
