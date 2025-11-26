<?php

namespace Dshovchko\ImageMigrate\Listener;

use Dshovchko\ImageMigrate\SnapGrab\HealthCheckException;
use Dshovchko\ImageMigrate\SnapGrab\SnapGrabClient;
use Flarum\Settings\Event\Saving;
use Illuminate\Validation\ValidationException;

class ValidateBackendSettings
{
    public function __construct(private readonly SnapGrabClient $client)
    {
    }

    public function handle(Saving $event): void
    {
        $baseKey = 'dshovchko-image-migrate.snapgrab_base_url';
        $envKey = 'dshovchko-image-migrate.snapgrab_env';
        $apiKey = 'dshovchko-image-migrate.snapgrab_api_key';

        if (array_key_exists($baseKey, $event->settings)) {
            $event->settings[$baseKey] = $this->client->normalizeBaseUrl($event->settings[$baseKey]);
        }

        if (array_key_exists($envKey, $event->settings)) {
            $event->settings[$envKey] = in_array($event->settings[$envKey], ['integration', 'production'], true)
                ? $event->settings[$envKey]
                : 'production';
        }

        if (!$this->shouldValidate($event->settings, [$baseKey, $envKey, $apiKey])) {
            return;
        }

        $baseUrl = array_key_exists($baseKey, $event->settings)
            ? $event->settings[$baseKey]
            : $this->client->getBaseUrl();

        if (!$baseUrl) {
            return;
        }

        try {
            $this->client->healthCheck($baseUrl);
        } catch (HealthCheckException $e) {
            throw new ValidationException([$baseKey => $e->getMessage()]);
        }
    }

    private function shouldValidate(array $payload, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }
}
