<?php
declare(strict_types=1);

namespace Wiz\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Wiz\Core\App;
use Wiz\Core\Database;

/**
 * Every controller named in a route file must be registered in the container.
 *
 * This exists because a missing binding is invisible until someone opens the
 * page: the route resolves fine at lint time and the unit tests build their own
 * objects. Shipping without it means a 500 on a screen nobody clicked in
 * staging.
 */
final class ContainerBindingsTest extends TestCase
{
    private static ?App $app = null;

    private function app(): App
    {
        if (self::$app === null) {
            /** @var App $app */
            $app = require __DIR__ . '/../../bootstrap/app.php';
            self::$app = $app;
        }

        return self::$app;
    }

    /**
     * @return list<class-string>
     */
    private function routeHandlers(): array
    {
        $classes = [];

        foreach (glob(__DIR__ . '/../../routes/*.php') ?: [] as $file) {
            $source = file_get_contents($file);

            if ($source === false) {
                continue;
            }

            preg_match_all(
                '/([A-Za-z_][A-Za-z0-9_]*)\\\\([A-Za-z_][A-Za-z0-9_]*Controller)::class/',
                $source,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $classes[] = $match[1] . '\\' . $match[2];
            }

            // Controllers imported under a short name: use Controller::class ...
            preg_match_all('/(?<![\\\\A-Za-z0-9_])([A-Za-z_][A-Za-z0-9_]*Controller)::class/', $source, $short);

            foreach ($short[1] as $name) {
                if (preg_match('/^use\s+([^;]+\\\\' . preg_quote($name, '/') . ');/m', $source, $use) === 1) {
                    $classes[] = $use[1];
                }
            }
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    public function testEveryControllerInTheRoutesCanBeBuilt(): void
    {
        $app = $this->app();
        $handlers = $this->routeHandlers();

        self::assertNotEmpty($handlers, 'No controllers were found in routes/*.php.');

        foreach ($handlers as $class) {
            self::assertTrue(
                $app->has($class),
                sprintf('%s is used by a route but is not registered in bootstrap/app.php.', $class),
            );
        }
    }

    public function testMailServicesThatSendRealEmailAreBound(): void
    {
        $app = $this->app();

        foreach ([
            \Wiz\Contracts\MailTransport::class,
            \Wiz\Services\EmailTemplateService::class,
            \Wiz\Support\MailSettings::class,
            \Wiz\Services\BookingNotifier::class,
        ] as $class) {
            self::assertTrue($app->has($class), $class . ' is not registered in bootstrap/app.php.');
        }
    }

    /**
     * Resolving these must not touch the network or the database, so it is safe
     * on a machine with no MySQL running. It proves the constructor arguments in
     * the binding match the real constructors — the failure mode that produces a
     * 500 the moment a screen loads.
     */
    public function testMailServicesActuallyResolve(): void
    {
        $app = $this->app();

        self::assertInstanceOf(\Wiz\Contracts\MailTransport::class, $app->get(\Wiz\Contracts\MailTransport::class));
        self::assertInstanceOf(\Wiz\Support\MailSettings::class, $app->get(\Wiz\Support\MailSettings::class));
        self::assertInstanceOf(\Wiz\Core\Mailer::class, $app->get(\Wiz\Core\Mailer::class));
    }

    public function testDatabaseAndTemplateServiceBindingIsTypedCorrectly(): void
    {
        $app = $this->app();

        // Resolving EmailTemplateService only builds a Database object; the PDO
        // handle is opened on first query, which this test never makes.
        $service = $app->get(\Wiz\Services\EmailTemplateService::class);

        self::assertInstanceOf(\Wiz\Services\EmailTemplateService::class, $service);
        self::assertInstanceOf(Database::class, $app->get(Database::class));
    }
}
