<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: menyediakan render view + layout, redirect, dan JSON.
 */
abstract class Controller
{
    /**
     * Render sebuah view. View dirender ke buffer lalu dimasukkan ke layout.
     */
    protected function view(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $viewFile = APP_PATH . '/views/' . $view . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View tidak ditemukan: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = APP_PATH . '/views/' . $layout . '.php';

        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

    /**
     * Ambil input dari POST/GET, dengan fallback default.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }
}
