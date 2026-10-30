<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Service;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extension configuration of nkc_base (NAPI access and status check)
 */
final class NapiSettings
{
    public const REQUIRED = ['NDK_NAPI_HOST', 'NDK_NAPI_PORT', 'NDK_NAPI_PATH', 'NDK_NAPI_PROTOCOL', 'NDK_NAPI_VERSION', 'NDK_NAPI_USER_ID', 'NDK_NAPI_ACCESS_TOKEN'];

    private const DEFAULT_TIMEOUT = 20;
    private const DEFAULT_SLOW_MS = 1500;
    private const DEFAULT_ALERT_AFTER = 3;
    private const DEFAULT_ENDPOINTS = 'institutions,people,events,categories';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    public function __construct(private readonly ExtensionConfiguration $extensionConfiguration) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->settings === null) {
            try {
                $this->settings = (array)$this->extensionConfiguration->get('nkc_base');
            } catch (\Throwable) {
                $this->settings = [];
            }
        }
        return $this->settings;
    }

    public function get(string $key): string
    {
        return trim((string)($this->all()[$key] ?? ''));
    }

    /**
     * @return string[] Names of required settings that are empty
     */
    public function getMissing(): array
    {
        return array_values(array_filter(self::REQUIRED, fn(string $key): bool => $this->get($key) === ''));
    }

    /**
     * Base URI of the API, e.g. https://www.nordkirche.de:443/api/v1/
     */
    public function getBaseUri(): string
    {
        return sprintf(
            '%s://%s:%d/%sv%d/',
            $this->get('NDK_NAPI_PROTOCOL'),
            $this->get('NDK_NAPI_HOST'),
            (int)$this->get('NDK_NAPI_PORT'),
            ltrim($this->get('NDK_NAPI_PATH'), '/'),
            (int)$this->get('NDK_NAPI_VERSION')
        );
    }

    /**
     * @return array{0: string, 1: string}|array{}
     */
    public function getHttpAuth(): array
    {
        $username = $this->get('NDK_HTTP_AUTH_USERNAME');
        return $username !== '' ? [$username, $this->get('NDK_HTTP_AUTH_PASSWORD')] : [];
    }

    public function getTimeout(): int
    {
        $timeout = (int)$this->get('NDK_NAPI_TIMEOUT');
        return $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * Response time in milliseconds from which the API counts as slow
     */
    public function getSlowThreshold(): int
    {
        $value = (int)$this->get('NDK_HEALTH_SLOW_MS');
        return $value > 0 ? $value : self::DEFAULT_SLOW_MS;
    }

    /**
     * Number of failed checks in a row before an alert is sent
     */
    public function getAlertAfter(): int
    {
        $value = (int)$this->get('NDK_HEALTH_ALERT_AFTER');
        return $value > 0 ? $value : self::DEFAULT_ALERT_AFTER;
    }

    /**
     * @return string[]
     */
    public function getAlertRecipients(): array
    {
        return array_values(array_filter(
            GeneralUtility::trimExplode(',', $this->get('NDK_HEALTH_ALERT_RECIPIENTS'), true),
            static fn(string $address): bool => GeneralUtility::validEmail($address)
        ));
    }

    /**
     * @return string[] Endpoints used by the status check
     */
    public function getHealthEndpoints(): array
    {
        $endpoints = GeneralUtility::trimExplode(',', $this->get('NDK_HEALTH_ENDPOINTS') ?: self::DEFAULT_ENDPOINTS, true);
        return array_values(array_filter($endpoints, static fn(string $endpoint): bool => (bool)preg_match('/^[a-z_]+$/', $endpoint)));
    }
}
