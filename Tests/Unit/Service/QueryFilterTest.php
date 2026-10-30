<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Service;

use Nordkirche\Ndk\Domain\Model\Geocode;
use Nordkirche\NkcBase\Service\QueryFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class QueryFilterTest extends UnitTestCase
{
    public static function listDataProvider(): \Generator
    {
        yield 'empty string' => ['', []];
        yield 'null' => [null, []];
        yield 'zero' => [0, []];
        yield 'single value' => ['12', ['12']];
        yield 'comma list with spaces and gaps' => [' 1, 2 ,,3 ', ['1', '2', '3']];
        yield 'NAPI resource URIs' => ['napi:institution:1,napi:person:2', ['napi:institution:1', 'napi:person:2']];
        yield 'array with empty values' => [['1', '', 2], ['1', '2']];
    }

    #[Test]
    #[DataProvider('listDataProvider')]
    public function toListNormalizesFilterValues(mixed $filter, array $expected): void
    {
        self::assertSame($expected, QueryFilter::toList($filter));
    }

    public static function institutionOptionDataProvider(): \Generator
    {
        yield 'selected only' => ['', 'setInstitutions'];
        yield 'children' => ['CHILDREN', 'setParentInstitutions'];
        yield 'children recursive' => ['CHILDREN_RECURSIVE', 'setAncestorInstitutions'];
        yield 'children and self' => ['BOTH', 'setParentInstitutionsOrSelf'];
        yield 'children recursive and self' => ['BOTH_RECURSIVE', 'setAncestorInstitutionsOrSelf'];
        yield 'unknown option' => ['SOMETHING', 'setInstitutions'];
    }

    #[Test]
    #[DataProvider('institutionOptionDataProvider')]
    public function institutionsUsesTheSetterOfTheSelectOption(string $option, string $expectedSetter): void
    {
        $query = $this->createQuery();
        QueryFilter::institutions($query, '1,2', $option);

        self::assertSame([$expectedSetter => ['1', '2']], $query->calls);
    }

    #[Test]
    public function emptyFiltersDoNotTouchTheQuery(): void
    {
        $query = $this->createQuery();
        QueryFilter::institutions($query, '', 'CHILDREN');
        QueryFilter::institutionTypes($query, '');
        QueryFilter::personInstitutions($query, null);
        QueryFilter::functionTypes($query, '');
        QueryFilter::persons($query, []);
        QueryFilter::availableFunctions($query, '');
        QueryFilter::categories($query, '');

        self::assertSame([], $query->calls);
    }

    #[Test]
    public function filtersUseTheMatchingSetters(): void
    {
        $query = $this->createQuery();
        QueryFilter::institutionTypes($query, '4,9');
        QueryFilter::personInstitutions($query, '7');
        QueryFilter::functionTypes($query, '11');
        QueryFilter::persons($query, '3,5');
        QueryFilter::availableFunctions($query, '8');

        self::assertSame([
            'setInstitutionType' => ['4', '9'],
            'setInstitutions' => ['7'],
            'setFunctions' => ['11'],
            'setPersons' => ['3', '5'],
            'setAvailableFunctions' => ['8'],
        ], $query->calls);
    }

    #[Test]
    public function categoriesAreIntegers(): void
    {
        $query = $this->createQuery();
        QueryFilter::categories($query, '12, 13,abc');

        self::assertSame(['setCategories' => [12, 13]], $query->calls);
    }

    #[Test]
    public function geoIsOnlyAppliedWhenActiveAndComplete(): void
    {
        $query = $this->createQuery();
        QueryFilter::geo($query, 0, 53.5, 10.0, 5);
        QueryFilter::geo($query, 1, 0, 10.0, 5);
        QueryFilter::geo($query, '1', 53.5, 10.0, 0);

        self::assertSame([], $query->calls);
    }

    #[Test]
    public function geoRoundsTheRadius(): void
    {
        $query = $this->createQuery();
        QueryFilter::geo($query, '1', '53.55', '9.99', '2.6');

        $geocode = $query->calls['setGeocode'] ?? null;
        self::assertInstanceOf(Geocode::class, $geocode);
        self::assertSame(53.55, $geocode->getLatitude());
        self::assertSame(9.99, $geocode->getLongitude());
        self::assertSame(3, $geocode->getRadius());
    }

    /**
     * Records the setter calls (NDK queries have many setters, the filters only call them)
     */
    private function createQuery(): object
    {
        return new class () {
            public array $calls = [];

            public function __call(string $name, array $arguments): void
            {
                $this->calls[$name] = $arguments[0];
            }
        };
    }

    #[Test]
    public function idsOfNapiUrlsAndIds(): void
    {
        self::assertSame(['12', '13', '7'], QueryFilter::ids('12, napi://resource/people/13,napi:resource/institutions/7, 12'));
        self::assertSame([], QueryFilter::ids('{rootline:tx_kkslfl_institution_uid}'), 'Unresolved placeholders are no id');
        self::assertSame(['2658'], QueryFilter::siteInstitutions(['2658', '0', '']));
    }
}
