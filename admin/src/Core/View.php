<?php
declare(strict_types=1);

namespace Wiz\Core;

use RuntimeException;

/**
 * Plain PHP template renderer.
 *
 * Views are ordinary PHP files. Output is escaped through `e()` unless the
 * template explicitly opts out, so a missing escape is visible in review.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $viewsPath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render a view inside a layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = [], ?string $layout = 'layouts/admin'): Response
    {
        $content = $this->capture($view, $data);

        if ($layout === null) {
            return Response::html($content);
        }

        $rendered = $this->capture($layout, array_merge($data, ['content' => $content]));

        return Response::html($rendered);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function capture(string $view, array $data = []): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        $vars = array_merge($this->shared, $data);

        extract($vars, EXTR_SKIP);

        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }
}
