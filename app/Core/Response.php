<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta HTTP: cuerpo, código de estado y cabeceras.
 */
final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {}

    /** Redirección (303 por defecto: correcta tras un POST). */
    public static function redirect(string $url, int $status = 303): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}