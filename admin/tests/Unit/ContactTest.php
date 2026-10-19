<?php
declare(strict_types=1);

namespace Wiz\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wiz\Support\Contact;

final class ContactTest extends TestCase
{
    public function testRecognisesAnEmailAddress(): void
    {
        self::assertSame(
            ['email' => 'hanna@example.com', 'phone' => null],
            Contact::split('  hanna@example.com '),
        );
    }

    public function testTreatsEverythingElseAsAPhoneNumber(): void
    {
        self::assertSame(['email' => null, 'phone' => '0911223344'], Contact::split('0911223344'));
        self::assertSame(['email' => null, 'phone' => '+251 91 122 3344'], Contact::split('+251 91 122 3344'));
    }

    public function testEmptyIsNeither(): void
    {
        self::assertSame(['email' => null, 'phone' => null], Contact::split('   '));
    }

    public function testSomethingThatLooksLikeNeitherIsStillKept(): void
    {
        // A typo should reach the salon, not be thrown away: they can see it
        // and call the person back.
        self::assertSame(['email' => null, 'phone' => 'hanna at example'], Contact::split('hanna at example'));
    }
}
