<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\Database;
use Wiz\Core\Migrations;
use Wiz\Repositories\MessageRepository;
use Wiz\Support\Contact;

/**
 * The contact inbox, against the real schema.
 */
final class MessageInboxTest extends TestCase
{
    private MessageRepository $messages;

    protected function setUp(): void
    {
        $name = getenv('DB_NAME');

        if ($name === false || $name === '') {
            self::markTestSkipped('No database configured (DB_NAME is empty).');
        }

        $db = new Database([
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('DB_USER') ?: '',
            'pass' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8mb4',
        ]);

        (new Migrations($db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();

        $db->execute('DELETE FROM contact_messages');

        $this->messages = new MessageRepository($db);
    }

    public function testStoresAnEmailEnquiry(): void
    {
        $split = Contact::split('hanna@example.com');

        $this->messages->create('Hanna Tesfaye', $split['email'], $split['phone'], 'Do you do bridal hair?', '127.0.0.1');

        $all = $this->messages->all();

        self::assertCount(1, $all);
        self::assertSame('Hanna Tesfaye', $all[0]['name']);
        self::assertSame('hanna@example.com', $all[0]['email']);
        self::assertNull($all[0]['phone']);
        self::assertSame(0, (int) $all[0]['is_read']);
    }

    public function testStoresAPhoneEnquiry(): void
    {
        $split = Contact::split('0911223344');

        $this->messages->create('Selam B.', $split['email'], $split['phone'], 'Price for braids?', '');

        $all = $this->messages->all();

        self::assertSame('0911223344', $all[0]['phone']);
        self::assertNull($all[0]['email']);
        self::assertNull($all[0]['ip_address']);
    }

    public function testUnreadCountFollowsTheReadFlag(): void
    {
        $id = $this->messages->create('Marta', 'marta@example.com', null, 'Hello', '');
        $this->messages->create('Ruth', null, '0912000111', 'Hi', '');

        self::assertSame(2, $this->messages->unreadCount());

        $this->messages->setRead($id, true);

        self::assertSame(1, $this->messages->unreadCount());
        self::assertCount(1, $this->messages->all(true));
        self::assertCount(2, $this->messages->all(false));

        $this->messages->setRead($id, false);

        self::assertSame(2, $this->messages->unreadCount());
    }

    public function testDeletingRemovesItCompletely(): void
    {
        $id = $this->messages->create('Liya', null, '0912345678', 'Note', '');

        $this->messages->delete($id);

        self::assertSame(0, $this->messages->count());
        self::assertNull($this->messages->find($id));
    }

    public function testNewestComesFirst(): void
    {
        $this->messages->create('First', null, '0911000001', 'One', '');
        $this->messages->create('Second', null, '0911000002', 'Two', '');

        $all = $this->messages->all();

        self::assertSame('Second', $all[0]['name']);
    }
}
