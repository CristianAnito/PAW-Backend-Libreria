<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = []): Response
    {
        extract($data);

        $viewPath = __DIR__ . "/../Views/{$view}.php";

        if (!file_exists($viewPath)) {
            throw new \Exception("View not found: {$view}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // Check if layout should be wrapped or if it is a layout itself
        if (str_starts_with($view, 'layouts/') || str_starts_with($view, 'errors/')) {
            return new Response($content);
        }

        // Wrap in main layout by default
        ob_start();
        $contentLayout = $content;
        require __DIR__ . '/../Views/layouts/main.php';
        $fullContent = ob_get_clean();

        return new Response($fullContent);
    }
}
