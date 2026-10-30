<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Controller;

use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\Ndk\Domain\Query\PersonQuery;
use Nordkirche\Ndk\Service\Exception\ClientException;
use Nordkirche\Ndk\Service\Exception\ServerException;
use Nordkirche\Ndk\Service\Result;
use Nordkirche\NkcBase\Controller\BaseController;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BaseControllerTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    public function listsReturnTheResultOfTheNapi(): void
    {
        $result = new Result(new \SplObjectStorage(), 3, 42, []);
        $query = new PersonQuery();
        $repository = new class ($result) {
            public ?PageQuery $query = null;

            public function __construct(private readonly Result $result) {}

            public function get(PageQuery $query): Result
            {
                $this->query = $query;
                return $this->result;
            }
        };

        self::assertSame($result, $this->createSubject()->fetchList($repository, $query));
        self::assertSame($query, $repository->query);
    }

    #[Test]
    public function queriesRejectedByTheNapiLeadToAnEmptyList(): void
    {
        $repository = new class () {
            public function get(PageQuery $query): Result
            {
                throw new ClientException('cities is not allowed', 1494246071, 'https://napi.test/api/v1/people');
            }
        };

        $result = $this->createSubject()->fetchList($repository, new PersonQuery());

        self::assertSame(0, $result->getRecordCount());
        self::assertSame(0, $result->getPageCount());
        self::assertCount(0, $result);
    }

    #[Test]
    public function emptyListsOfRejectedQueriesAreNotCached(): void
    {
        $cacheInstruction = new CacheInstruction();
        $request = new Request(
            (new ServerRequest('https://example.org/'))
                ->withAttribute('extbase', new ExtbaseRequestParameters())
                ->withAttribute('frontend.cache.instruction', $cacheInstruction)
        );
        $repository = new class () {
            public function get(PageQuery $query): Result
            {
                throw new ClientException('Not Found', 1494246071, 'https://napi.test/api/v1/events');
            }
        };

        $this->createSubject($request)->fetchList($repository, new PersonQuery());

        self::assertFalse($cacheInstruction->isCachingAllowed());
    }

    #[Test]
    public function serverErrorsAreNotHidden(): void
    {
        $repository = new class () {
            public function get(PageQuery $query): Result
            {
                throw new ServerException('down', 1494246071, 'https://napi.test/api/v1/people');
            }
        };

        $this->expectException(ServerException::class);
        $this->createSubject()->fetchList($repository, new PersonQuery());
    }

    private function createSubject(?Request $request = null): object
    {
        return new class ($request) extends BaseController {
            public function __construct(?Request $request)
            {
                if ($request !== null) {
                    $this->request = $request;
                }
            }

            public function fetchList(object $repository, PageQuery $query): Result
            {
                return parent::fetchList($repository, $query);
            }
        };
    }
}
