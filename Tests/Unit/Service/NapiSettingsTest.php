<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Service;

use Nordkirche\NkcBase\Service\NapiSettings;
use Nordkirche\NkcBase\Tests\Unit\Fixtures\NapiSettingsFixture;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class NapiSettingsTest extends UnitTestCase
{
    use NapiSettingsFixture;

    #[Test]
    public function baseUriIsBuiltFromTheParts(): void
    {
        self::assertSame('https://www.nordkirche.de:443/api/v1/', $this->createNapiSettings(self::NAPI_CONFIGURATION)->getBaseUri());
    }

    #[Test]
    public function missingSettingsAreReported(): void
    {
        $configuration = self::NAPI_CONFIGURATION;
        unset($configuration['NDK_NAPI_HOST']);
        $configuration['NDK_NAPI_ACCESS_TOKEN'] = '  ';

        self::assertSame(['NDK_NAPI_HOST', 'NDK_NAPI_ACCESS_TOKEN'], $this->createNapiSettings($configuration)->getMissing());
        self::assertSame([], $this->createNapiSettings(self::NAPI_CONFIGURATION)->getMissing());
    }

    #[Test]
    public function aMissingExtensionConfigurationCountsAsEmpty(): void
    {
        $settings = $this->createNapiSettings(new \RuntimeException('not configured'));

        self::assertSame([], $settings->all());
        self::assertSame(NapiSettings::REQUIRED, $settings->getMissing());
    }

    #[Test]
    public function defaultsAreUsedForEmptyOrInvalidValues(): void
    {
        $settings = $this->createNapiSettings(self::NAPI_CONFIGURATION + [
            'NDK_NAPI_TIMEOUT' => '0',
            'NDK_HEALTH_SLOW_MS' => 'abc',
            'NDK_HEALTH_ALERT_AFTER' => '-1',
        ]);

        self::assertSame(20, $settings->getTimeout());
        self::assertSame(1500, $settings->getSlowThreshold());
        self::assertSame(3, $settings->getAlertAfter());
        self::assertSame(['institutions', 'people', 'events', 'categories'], $settings->getHealthEndpoints());
        self::assertSame([], $settings->getHttpAuth());
    }

    #[Test]
    public function configuredValuesAreUsed(): void
    {
        $settings = $this->createNapiSettings(self::NAPI_CONFIGURATION + [
            'NDK_NAPI_TIMEOUT' => '5',
            'NDK_HEALTH_SLOW_MS' => '800',
            'NDK_HEALTH_ALERT_AFTER' => '2',
            'NDK_HTTP_AUTH_USERNAME' => 'user',
            'NDK_HTTP_AUTH_PASSWORD' => 'secret',
        ]);

        self::assertSame(5, $settings->getTimeout());
        self::assertSame(800, $settings->getSlowThreshold());
        self::assertSame(2, $settings->getAlertAfter());
        self::assertSame(['user', 'secret'], $settings->getHttpAuth());
    }

    #[Test]
    public function onlyValidAlertRecipientsAreUsed(): void
    {
        $settings = $this->createNapiSettings(['NDK_HEALTH_ALERT_RECIPIENTS' => 'admin@example.org, no-mail ,team@example.org']);

        self::assertSame(['admin@example.org', 'team@example.org'], $settings->getAlertRecipients());
    }

    #[Test]
    public function healthEndpointsMustBeSimpleNames(): void
    {
        $settings = $this->createNapiSettings(['NDK_HEALTH_ENDPOINTS' => 'events, ../admin,people?x=1,target_groups']);

        self::assertSame(['events', 'target_groups'], $settings->getHealthEndpoints());
    }
}
