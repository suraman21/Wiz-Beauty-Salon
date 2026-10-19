<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wiz\Support\Paginator;

final class PaginatorTest extends TestCase
{
    public function testTheFirstPageStartsAtRowOne(): void
    {
        $page = Paginator::of(1, 25, 31);

        self::assertSame(1, $page->page);
        self::assertSame(0, $page->offset());
        self::assertSame(2, $page->pages());
        self::assertSame(1, $page->firstItem());
        self::assertSame(25, $page->lastItem());
        self::assertTrue($page->hasNext());
        self::assertFalse($page->hasPrevious());
    }

    public function testTheLastPageHoldsOnlyWhatIsLeft(): void
    {
        $page = Paginator::of(2, 25, 31);

        self::assertSame(25, $page->offset());
        self::assertSame(26, $page->firstItem());
        self::assertSame(31, $page->lastItem());
        self::assertFalse($page->hasNext());
        self::assertTrue($page->hasPrevious());
    }

    public function testAPageBeyondTheEndIsClampedToTheLastPage(): void
    {
        $page = Paginator::of(99, 25, 31);

        self::assertSame(2, $page->page);
        self::assertSame(25, $page->offset());
    }

    public function testZeroAndNegativePagesBecomeOne(): void
    {
        self::assertSame(1, Paginator::of(0, 10, 5)->page);
        self::assertSame(1, Paginator::of(-4, 10, 5)->page);
    }

    public function testAnEmptySetIsStillOnePage(): void
    {
        $page = Paginator::of(1, 25, 0);

        self::assertSame(1, $page->pages());
        self::assertSame(0, $page->firstItem());
        self::assertSame(0, $page->lastItem());
        self::assertFalse($page->hasNext());
    }

    public function testTheWindowStaysShortOnALongList(): void
    {
        $window = Paginator::of(10, 10, 300)->window();

        self::assertSame(1, $window[0]);
        self::assertSame(30, $window[count($window) - 1]);
        self::assertContains(10, $window);
        self::assertLessThan(9, count($window));
    }

    public function testTheWindowIsEveryPageWhenTheyFit(): void
    {
        self::assertSame([1, 2, 3], Paginator::of(2, 10, 25)->window());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function limits(): iterable
    {
        yield 'zero rows' => [1, 10, 0];
        yield 'one row' => [1, 10, 1];
        yield 'exactly one page' => [1, 10, 10];
        yield 'one row over' => [2, 10, 11];
        yield 'many pages' => [7, 10, 61];
    }

    #[DataProvider('limits')]
    public function testPagingNeverOverlapsOrSkipsARow(int $requested, int $perPage, int $total): void
    {
        $page = Paginator::of($requested, $perPage, $total);

        self::assertSame(($page->page - 1) * $perPage, $page->offset());
        self::assertSame($total, $page->offset() + max(0, $page->lastItem() - $page->offset()));
    }
}
