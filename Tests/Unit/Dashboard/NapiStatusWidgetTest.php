<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Dashboard;

use Nordkirche\NkcBase\Dashboard\NapiStatusWidget;
use Nordkirche\NkcBase\Napi\Health\HealthState;
use Nordkirche\NkcBase\Napi\Traffic\TrafficStats;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class NapiStatusWidgetTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    #[Test]
    public function usersWithAccessToTheModuleGetTheLink(): void
    {
        $GLOBALS['BE_USER'] = self::createStub(BackendUserAuthentication::class);

        self::assertSame('/typo3/module/system/napi-status', $this->createSubject(true)->getModuleUri());
    }

    #[Test]
    public function editorsWithoutAccessGetNoLink(): void
    {
        $GLOBALS['BE_USER'] = self::createStub(BackendUserAuthentication::class);

        self::assertSame('', $this->createSubject(false)->getModuleUri());
    }

    #[Test]
    public function withoutBackendUserThereIsNoLink(): void
    {
        self::assertSame('', $this->createSubject(true)->getModuleUri());
    }

    private function createSubject(bool $accessGranted): NapiStatusWidget
    {
        $moduleProvider = self::createStub(ModuleProvider::class);
        $moduleProvider->method('accessGranted')->willReturnCallback(
            static fn(string $identifier): bool => $identifier === 'nkcbase_napistatus' && $accessGranted
        );
        $uriBuilder = self::createStub(UriBuilder::class);
        $uriBuilder->method('buildUriFromRoute')->willReturn(new Uri('/typo3/module/system/napi-status'));

        return new NapiStatusWidget(
            self::createStub(WidgetConfigurationInterface::class),
            // Final class, not used for the link
            (new \ReflectionClass(BackendViewFactory::class))->newInstanceWithoutConstructor(),
            self::createStub(HealthState::class),
            self::createStub(TrafficStats::class),
            $uriBuilder,
            $moduleProvider,
        );
    }
}
