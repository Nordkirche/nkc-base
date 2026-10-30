<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Fixtures;

use Nordkirche\NkcBase\Service\NapiSettings;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * NapiSettings with a given extension configuration of nkc_base
 */
trait NapiSettingsFixture
{
    private const NAPI_CONFIGURATION = [
        'NDK_NAPI_HOST' => 'www.nordkirche.de',
        'NDK_NAPI_PORT' => '443',
        'NDK_NAPI_PATH' => '/api/',
        'NDK_NAPI_PROTOCOL' => 'https',
        'NDK_NAPI_VERSION' => '1',
        'NDK_NAPI_USER_ID' => '42',
        'NDK_NAPI_ACCESS_TOKEN' => 'test-token',
    ];

    private function createNapiSettings(array|\Throwable $configuration): NapiSettings
    {
        // A stub, because ExtensionConfiguration is readonly in TYPO3 v14 only
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        if ($configuration instanceof \Throwable) {
            $extensionConfiguration->method('get')->willThrowException($configuration);
        } else {
            $extensionConfiguration->method('get')->willReturn($configuration);
        }
        return new NapiSettings($extensionConfiguration);
    }
}
