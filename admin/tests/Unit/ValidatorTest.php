<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wiz\Core\Validator;

final class ValidatorTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     */
    private function validate(array $data, array $rules): Validator
    {
        $validator = new Validator($data, $rules, [
            'name' => 'Full name',
            'email' => 'Email address',
        ]);

        $validator->validate();

        return $validator;
    }

    public function testAcceptsACompleteForm(): void
    {
        $validator = $this->validate(
            ['name' => 'Hanna Tesfaye', 'email' => 'hanna@example.com'],
            ['name' => 'required|max:120', 'email' => 'required|email'],
        );

        self::assertTrue($validator->validate());
        self::assertNull($validator->firstError());
    }

    public function testRejectsAnEmptyRequiredField(): void
    {
        $validator = $this->validate(['name' => '   '], ['name' => 'required']);

        self::assertFalse($validator->validate());
        self::assertSame('Full name is required.', $validator->firstError());
    }

    public function testRejectsAMalformedEmail(): void
    {
        $validator = $this->validate(['email' => 'not-an-email'], ['email' => 'email']);

        self::assertFalse($validator->validate());
        self::assertSame('Email address must be a valid email address.', $validator->firstError());
    }

    public function testSkipsOptionalRulesForAnEmptyField(): void
    {
        $validator = $this->validate(['email' => ''], ['email' => 'email|max:190']);

        self::assertTrue($validator->validate());
    }

    public function testStopsAtTheFirstFailingRulePerField(): void
    {
        $validator = $this->validate(
            ['name' => 'ab'],
            ['name' => 'min:5|max:120'],
        );

        self::assertFalse($validator->validate());
        self::assertSame('Full name must be at least 5 characters.', $validator->firstError());
    }

    public function testCountsCharactersRatherThanBytes(): void
    {
        // Four Amharic characters: eight bytes, four characters.
        $validator = $this->validate(['name' => 'ሀለሉሊ'], ['name' => 'max:4']);

        self::assertTrue($validator->validate());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function phoneProvider(): array
    {
        return [
            'local mobile' => ['0911223344', true],
            'international' => ['+251911223344', true],
            'spaced' => ['0911 223 344', true],
            'letters' => ['call-me', false],
            'too short' => ['12345', false],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function testValidatesPhoneNumbers(string $phone, bool $expected): void
    {
        $validator = $this->validate(['name' => $phone], ['name' => 'phone']);

        self::assertSame($expected, $validator->validate());
    }

    public function testRejectsAnImpossibleDate(): void
    {
        $validator = $this->validate(['name' => '2026-02-31'], ['name' => 'date']);

        self::assertFalse($validator->validate());
    }

    public function testRejectsAValueOutsideTheAllowedList(): void
    {
        $validator = $this->validate(
            ['name' => 'owner'],
            ['name' => 'in:admin,staff'],
        );

        self::assertFalse($validator->validate());
        self::assertSame('Full name has an invalid value.', $validator->firstError());
    }

    public function testReturnsOnlyTheFieldsThatPassed(): void
    {
        $validator = new Validator(
            ['name' => 'Sara', 'email' => 'broken'],
            ['name' => 'required', 'email' => 'email'],
        );

        $validator->validate();

        self::assertSame(['name' => 'Sara'], $validator->validated());
        self::assertArrayHasKey('email', $validator->errors());
    }
}
