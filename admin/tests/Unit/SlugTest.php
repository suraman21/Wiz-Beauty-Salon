<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wiz\Support\Slug;

final class SlugTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function slugProvider(): array
    {
        return [
            'simple' => ['Silk press', 'silk-press'],
            'extra spaces' => ['  Deep   conditioning  ', 'deep-conditioning'],
            'punctuation' => ['Wash & Blow-dry!', 'wash-and-blow-dry'],
            'accents' => ['Coiffure Élégante', 'coiffure-elegante'],
            'numbers kept' => ['Braids (2026)', 'braids-2026'],
        ];
    }

    #[DataProvider('slugProvider')]
    public function testMakesAUrlSafeSlug(string $input, string $expected): void
    {
        self::assertSame($expected, Slug::make($input));
    }

    public function testFallsBackWhenNothingUsableRemains(): void
    {
        self::assertSame('service', Slug::make('ሀለሉሊ', 'service'));
    }

    public function testCapsTheLength(): void
    {
        self::assertLessThanOrEqual(150, strlen(Slug::make(str_repeat('a', 400))));
    }

    public function testAppendsASuffixUntilTheSlugIsFree(): void
    {
        $taken = ['silk-press', 'silk-press-2', 'silk-press-3'];

        $slug = Slug::unique('silk-press', static fn (string $candidate): bool => in_array($candidate, $taken, true));

        self::assertSame('silk-press-4', $slug);
    }

    public function testLeavesAFreeSlugAlone(): void
    {
        self::assertSame('manicure', Slug::unique('manicure', static fn (): bool => false));
    }
}
