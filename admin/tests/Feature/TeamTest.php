<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Repositories\ActivityLogRepository;
use Wiz\Repositories\UserRepository;
use Wiz\Services\TeamService;

/**
 * Team rules against the real schema.
 *
 * The tests create their own accounts (prefixed phpunit-team-) and put every
 * administrator they switch off back the way they found it, so the suite can
 * run against a development database without changing it.
 */
final class TeamTest extends TestCase
{
    private Database $db;
    private UserRepository $users;
    private TeamService $team;

    /** @var list<int> */
    private array $created = [];

    /** @var list<int> */
    private array $reactivate = [];

    protected function setUp(): void
    {
        $name = getenv('DB_NAME');

        if ($name === false || $name === '') {
            self::markTestSkipped('No database configured (DB_NAME is empty).');
        }

        $this->db = new Database([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('DB_USER') ?: '',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ]);

        (new Migrations($this->db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();

        $this->users = new UserRepository($this->db);
        $this->team = new TeamService($this->users, new ActivityLogRepository($this->db));
    }

    protected function tearDown(): void
    {
        // setUp() skips the test when no database is configured; nothing was
        // created in that case, so there is nothing to undo.
        if (!isset($this->db)) {
            return;
        }

        foreach ($this->reactivate as $id) {
            $this->db->execute('UPDATE users SET is_active = 1 WHERE id = :id', [':id' => $id]);
        }

        foreach ($this->created as $id) {
            $this->db->execute('DELETE FROM users WHERE id = :id', [':id' => $id]);
        }
    }

    public function testCreatesAnAccountWithAHashedPassword(): void
    {
        $actor = $this->user('phpunit-team-actor', 'admin');

        $result = $this->team->create([
            'name' => 'Hanna Tesfaye',
            'email' => 'PHPUnit-Team-Hanna@Example.test',
            'role' => 'staff',
            'password' => 'long-enough-pass',
            'password_confirm' => 'long-enough-pass',
        ], $actor, '127.0.0.1');

        self::assertTrue($result['ok']);
        self::assertNull($result['error']);

        $created = $this->users->findByEmail('phpunit-team-hanna@example.test');

        self::assertNotNull($created);
        self::assertSame('Hanna Tesfaye', $created['name']);
        self::assertSame('staff', $created['role']);
        self::assertSame(1, (int) $created['is_active']);

        $record = $this->db->first(
            'SELECT password_hash FROM users WHERE id = :id',
            [':id' => (int) $created['id']],
        );

        self::assertNotNull($record);
        self::assertTrue(password_verify('long-enough-pass', (string) $record['password_hash']));

        $this->created[] = (int) $created['id'];
    }

    public function testRefusesAnEmailThatSomebodyAlreadyUses(): void
    {
        $actor = $this->user('phpunit-team-owner', 'admin');

        $first = $this->team->create([
            'name' => 'First Person',
            'email' => 'phpunit-team-twin@example.test',
            'role' => 'staff',
            'password' => 'long-enough-pass',
            'password_confirm' => 'long-enough-pass',
        ], $actor, '127.0.0.1');

        self::assertTrue($first['ok']);
        $this->created[] = (int) $first['id'];

        $second = $this->team->create([
            'name' => 'Second Person',
            'email' => 'PHPUNIT-TEAM-TWIN@example.test',
            'role' => 'staff',
            'password' => 'long-enough-pass',
            'password_confirm' => 'long-enough-pass',
        ], $actor, '127.0.0.1');

        self::assertFalse($second['ok']);
        self::assertStringContainsString('already signs in', (string) $second['error']);
    }

    public function testRefusesAShortPassword(): void
    {
        $actor = $this->user('phpunit-team-short', 'admin');

        $result = $this->team->create([
            'name' => 'Short Password',
            'email' => 'phpunit-team-short@example.test',
            'role' => 'staff',
            'password' => 'short',
            'password_confirm' => 'short',
        ], $actor, '127.0.0.1');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('at least 10', (string) $result['error']);
    }

    public function testAnAdministratorCannotLowerTheirOwnRole(): void
    {
        $actor = $this->user('phpunit-team-self', 'admin');

        $result = $this->team->update($actor, [
            'name' => 'Self Demotion',
            'email' => 'phpunit-team-self@example.test',
            'role' => 'staff',
            'password' => '',
            'password_confirm' => '',
        ], $actor, '127.0.0.1');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('your own administrator access', (string) $result['error']);

        $still = $this->users->findById($actor);
        self::assertSame('admin', $still['role'] ?? null);
    }

    public function testTheLastActiveAdministratorCannotBeSwitchedOff(): void
    {
        $actor = $this->user('phpunit-team-actor-two', 'admin');
        $target = $this->user('phpunit-team-last', 'admin');

        $this->isolateAdministrator($target);

        $result = $this->team->setActive($target, false, $actor, '127.0.0.1');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('at least one active administrator', (string) $result['error']);
        self::assertSame(1, (int) ($this->users->findById($target)['is_active'] ?? 0));
    }

    public function testTheLastActiveAdministratorCannotBeDemoted(): void
    {
        $actor = $this->user('phpunit-team-actor-three', 'admin');
        $target = $this->user('phpunit-team-last-two', 'admin');

        $this->isolateAdministrator($target);

        $result = $this->team->update($target, [
            'name' => 'Last Admin',
            'email' => 'phpunit-team-last-two@example.test',
            'role' => 'staff',
            'password' => '',
            'password_confirm' => '',
        ], $actor, '127.0.0.1');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('at least one active administrator', (string) $result['error']);
    }

    public function testYouCannotSwitchOffYourOwnAccount(): void
    {
        $actor = $this->user('phpunit-team-own', 'admin');

        $result = $this->team->setActive($actor, false, $actor, '127.0.0.1');

        self::assertFalse($result['ok']);
        self::assertStringContainsString('your own account', (string) $result['error']);
    }

    public function testAnAdministratorCanBeSwitchedOffWhileAnotherRemains(): void
    {
        $actor = $this->user('phpunit-team-keeper', 'admin');
        $other = $this->user('phpunit-team-leaver', 'admin');

        $result = $this->team->setActive($other, false, $actor, '127.0.0.1');

        self::assertTrue($result['ok']);
        self::assertSame(0, (int) ($this->users->findById($other)['is_active'] ?? 1));

        $this->reactivate[] = $other;
    }

    public function testChangingAPasswordIsOptional(): void
    {
        $actor = $this->user('phpunit-team-editor', 'admin');
        $id = $this->user('phpunit-team-edited', 'staff');

        $before = $this->db->first('SELECT password_hash FROM users WHERE id = :id', [':id' => $id]);
        self::assertNotNull($before);

        $result = $this->team->update($id, [
            'name' => 'Renamed Person',
            'email' => 'phpunit-team-edited@example.test',
            'role' => 'staff',
            'password' => '',
            'password_confirm' => '',
        ], $actor, '127.0.0.1');

        self::assertTrue($result['ok']);

        $after = $this->db->first('SELECT name, password_hash FROM users WHERE id = :id', [':id' => $id]);

        self::assertNotNull($after);
        self::assertSame('Renamed Person', $after['name']);
        self::assertSame((string) $before['password_hash'], (string) $after['password_hash']);
    }

    /**
     * Switch every other active administrator off, remembering them for
     * tearDown, so "the last one" is unambiguous.
     */
    private function isolateAdministrator(int $keepId): void
    {
        $rows = $this->db->all(
            "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 AND id <> :id",
            [':id' => $keepId],
        );

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $this->db->execute('UPDATE users SET is_active = 0 WHERE id = :id', [':id' => $id]);
            $this->reactivate[] = $id;
        }
    }

    private function user(string $prefix, string $role): int
    {
        $email = $prefix . '-' . bin2hex(random_bytes(3)) . '@example.test';

        $id = $this->users->create($prefix, $email, password_hash('long-enough-pass', PASSWORD_DEFAULT), $role);
        $this->created[] = $id;

        return $id;
    }
}
