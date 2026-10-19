<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Support\Paginator;

final class PaginatorTest extends TestCase
{
    public function testSplitsRowsIntoPages(): void
    {
        $paginator = Paginator::of(2, 25, 120);

        self::assertSame(2, $paginator->page);
        self::assertSame(5, $paginator->pages());
        self::assertSame(25, $paginator->offset());
        self::assertSame(26, $paginator->firstItem());
        self::assertSame(50, $paginator->lastItem());
        self::assertTrue($paginator->hasPrevious());
        self::assertTrue($paginator->hasNext());
    }

    public function testClampsAPageBeyondTheEnd(): void
    {
        $paginator = Paginator::of(99, 25, 40);

        self::assertSame(2, $paginator->page);
        self::assertFalse($paginator->hasNext());
    }

    public function testClampsAPageBelowOne(): void
    {
        $paginator = Paginator::of(0, 25, 40);

        self::assertSame(1, $paginator->page);
        self::assertFalse($paginator->hasPrevious());
    }

    public function testAnEmptyListStillHasOnePage(): void
    {
        $paginator = Paginator::of(3, 25, 0);

        self::assertSame(1, $paginator->page);
        self::assertSame(1, $paginator->pages());
        self::assertSame(0, $paginator->firstItem());
        self::assertSame(0, $paginator->lastItem());
    }

    public function testWindowKeepsTheEndsAndTheCurrentPage(): void
    {
        $window = Paginator::of(10, 10, 200)->window();

        self::assertSame(1, $window[0]);
        self::assertSame(20, $window[count($window) - 1]);
        self::assertContains(10, $window);
        self::assertContains(null, $window);
    }

    public function testShortListsShowEveryPageNumber(): void
    {
        self::assertSame([1, 2, 3], Paginator::of(2, 10, 30)->window());
    }
}
