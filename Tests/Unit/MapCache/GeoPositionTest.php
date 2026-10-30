<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\MapCache;

use Nordkirche\Ndk\Domain\Model\Address;
use Nordkirche\NkcBase\MapCache\GeoPosition;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class GeoPositionTest extends UnitTestCase
{
    #[Test]
    public function coordinatesOfAnAddress(): void
    {
        $address = new Address();
        $address->setLatitude(54.25);
        $address->setLongitude(9.65);

        self::assertSame([54.25, 9.65], GeoPosition::fromAddress($address));
    }

    #[Test]
    public function noPositionWithoutCompleteCoordinates(): void
    {
        $onlyLatitude = new Address();
        $onlyLatitude->setLatitude(54.25);
        $zero = new Address();
        $zero->setLatitude(0.0);
        $zero->setLongitude(0.0);

        self::assertNull(GeoPosition::fromAddress(new Address()), 'Address of the NAPI without coordinates');
        self::assertNull(GeoPosition::fromAddress($onlyLatitude));
        self::assertNull(GeoPosition::fromAddress($zero));
        self::assertNull(GeoPosition::fromAddress(null));
        self::assertNull(GeoPosition::fromAddress('Dorfstraße 27'));
    }
}
