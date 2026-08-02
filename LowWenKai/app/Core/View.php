<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $root = dirname(__DIR__) . '/Views/';
        $viewPath = $root . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();
        Session::remove('_old_input');

        if ($layout === '') {
            return $content;
        }

        $layoutPath = $root . str_replace('.', '/', $layout) . '.php';
        ob_start();
        require $layoutPath;

        return (string) ob_get_clean();
    }
}
