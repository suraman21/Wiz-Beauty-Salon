<?php
declare(strict_types=1);

namespace Wiz\Database;

use Wiz\Core\Database;

/**
 * Contract for a single migration file.
 *
 * Each file in database/migrations returns an anonymous class implementing this
 * interface. Keeping the shape explicit means a malformed migration fails at
 * load time with a clear type error rather than halfway through a schema change.
 */
interface Migration
{
    public function up(Database $db): void;

    public function down(Database $db): void;
}
