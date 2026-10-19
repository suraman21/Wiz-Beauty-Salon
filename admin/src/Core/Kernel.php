<?php
declare(strict_types=1);

namespace Wiz\Core;

use Wiz\Core\Middleware\AdminOnlyMiddleware;
use Wiz\Core\Middleware\AuthMiddleware;
use Wiz\Core\Middleware\CsrfMiddleware;
use Wiz\Core\Middleware\GuestMiddleware;

/**
 * Turns a request into a response.
 *
 * Order of operations: route match → middleware chain → controller → response.
 * Any HttpException is converted into a rendered error page (or JSON for API
 * callers), so controllers can simply throw.
 */
final class Kernel
{
    /** @var array<string, class-string> */
    private array $middleware = [
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'csrf' => CsrfMiddleware::class,
        'admin' => AdminOnlyMiddleware::class,
    ];

    public function __construct(
        private readonly App $app,
        private readonly Router $router,
        private readonly View $view,
        private readonly Logger $logger,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (RedirectException $e) {
            return Response::redirect($e->location(), $e->status());
        } catch (HttpException $e) {
            return $this->errorResponse($request, $e);
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled exception: ' . $e->getMessage(), [
                'path' => $request->path(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return $this->errorResponse($request, new HttpException(500, 'Something went wrong.'));
        }
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request->method(), $request->path());

        if ($match === null) {
            // A known path with the wrong verb is a 405, not a 404.
            $allowed = $this->router->allowedMethods($request->path());

            throw $allowed === []
                ? HttpException::notFound()
                : new HttpException(405, 'Method not allowed.');
        }

        // CSRF applies to every state-changing request automatically — except
        // on the public JSON API, which carries no session cookie. Cross-site
        // posts there are rejected by the Origin check inside the endpoint.
        if ($request->isPost() && !str_starts_with($request->path(), '/api/')) {
            $this->run($this->middleware['csrf'], $request);
        }

        foreach ($match['middleware'] as $name) {
            if (!isset($this->middleware[$name])) {
                throw new \RuntimeException('Unknown middleware: ' . $name);
            }

            $this->run($this->middleware[$name], $request);
        }

        return $this->invoke($match['handler'], $request, $match['params']);
    }

    /**
     * @param class-string $class
     */
    private function run(string $class, Request $request): void
    {
        $instance = $this->app->get($class);

        if (method_exists($instance, 'handle')) {
            $instance->handle($request);
        }
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param array<string, string> $params
     */
    private function invoke(array|callable $handler, Request $request, array $params): Response
    {
        if (is_callable($handler) && !is_array($handler)) {
            $result = $handler($request, $params);
        } else {
            /** @var array{0: class-string, 1: string} $handler */
            $controller = $this->app->get($handler[0]);
            $method = $handler[1];

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException(sprintf('Action not found: %s::%s', $handler[0], $method));
            }

            $result = $controller->{$method}($request, $params);
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        return Response::html('');
    }

    private function errorResponse(Request $request, HttpException $e): Response
    {
        $status = $e->status();

        if ($request->wantsJson()) {
            return Response::json(['error' => $e->getMessage(), 'status' => $status], $status);
        }

        $view = match ($status) {
            404 => 'errors/404',
            403 => 'errors/403',
            419 => 'errors/419',
            429 => 'errors/429',
            default => 'errors/500',
        };

        // The rendered page is a normal 200 response until the status is
        // applied to it — without this an error page would answer 200.
        return $this->view->render($view, [
            'title' => (string) $status,
            'message' => $e->getMessage(),
            'status' => $status,
        ], 'layouts/auth')->withStatus($status);
    }
}
