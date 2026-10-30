<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Backend;

use Nordkirche\NkcBase\Backend\TemplateLayouts;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Schema\Struct\SelectItem;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TemplateLayoutsTest extends UnitTestCase
{
    #[Test]
    public function layoutsOfThePageAreAddedToSelectItems(): void
    {
        // TYPO3 v14 passes the existing items as SelectItem objects
        $params = [
            'row' => ['pid' => 5],
            'config' => ['templateLayouts' => 'tx_nkcevent.templateLayouts'],
            'items' => [
                SelectItem::fromTcaItemArray(['label' => 'Standard', 'value' => ''], 'select'),
                SelectItem::fromTcaItemArray(['label' => 'Kompakt', 'value' => 'Compact'], 'select'),
            ],
        ];

        $this->createSubject(['NoImage' => 'Ohne Bild', 'Compact' => 'Doppelt', 'Bad-Name!' => 'Bereinigt', 'Sub.' => ['x' => 'y']])->addItems($params);

        $values = array_map(static fn(array|\ArrayAccess $item): string => (string)$item['value'], $params['items']);
        self::assertSame(['', 'Compact', 'NoImage', 'BadName'], $values, 'Existing values are not added again');
        self::assertSame('Ohne Bild', $params['items'][2]['label']);
    }

    #[Test]
    public function layoutsOfThePageAreAddedToItemArrays(): void
    {
        // TYPO3 v13: arrays
        $params = ['flexParentDatabaseRow' => ['pid' => 5], 'config' => ['templateLayouts' => 'tx_nkcaddress.templateLayouts'], 'items' => [['label' => 'Standard', 'value' => '']]];

        $this->createSubject(['NoImage' => 'Ohne Bild'])->addItems($params);

        self::assertSame(['label' => 'Ohne Bild', 'value' => 'NoImage'], $params['items'][1]);
    }

    #[Test]
    public function withoutTsConfigPathNothingIsAdded(): void
    {
        $params = ['row' => ['pid' => 5], 'items' => []];

        $this->createSubject(['NoImage' => 'Ohne Bild'])->addItems($params);

        self::assertSame([], $params['items']);
    }

    private function createSubject(array $layouts): TemplateLayouts
    {
        return new class ($layouts) extends TemplateLayouts {
            public function __construct(private readonly array $layouts) {}

            protected function getLayouts(int $pageId, string $path): array
            {
                return $pageId === 5 && str_ends_with($path, '.templateLayouts') ? $this->layouts : [];
            }
        };
    }
}
