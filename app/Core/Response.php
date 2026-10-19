<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta HTTP: cuerpo, código de estado y cabeceras.
 */
final class Response
{
    /** @var array<string, array{value: string, options: array}> Cookies a enviar (Set-Cookie). */
    private array $cookies = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = ['Content-Type' => 'application/json; charset=utf-8'],
    ) {}

    /**
     * Respuesta JSON. Es la representación que consume el frontend (SPA).
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * Error en formato JSON: {"error": {"message": "...", "fields": {...}}}.
     * $fields (opcional) trae el mensaje de cada campo inválido de un formulario.
     */
    public static function error(string $message, int $status, array $fields = []): self
    {
        $error = ['message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }
        return self::json(['error' => $error], $status);
    }

    /** Respuesta sin cuerpo (ej. 204 No Content). */
    public static function empty(int $status = 204): self
    {
        return new self('', $status, []);
    }

    /** Copia de la respuesta con cabeceras agregadas (o reemplazadas). */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = $headers + $this->headers;
        return $clone;
    }

    /**
     * Copia de la respuesta que además le pide al navegador guardar una cookie.
     *
     * @param array $options Opciones de setcookie(): expires, path, secure, httponly, samesite.
     */
    public function withCookie(string $name, string $value, array $options): self
    {
        $clone = clone $this;
        $clone->cookies[$name] = ['value' => $value, 'options' => $options];
        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);

        // PHP agrega "X-Powered-By: PHP/8.x.x": revela la versión exacta del servidor, que a un
        // atacante le sirve para buscar vulnerabilidades conocidas. No se envía.
        header_remove('X-Powered-By');

        // Cada cookie viaja en su propia cabecera Set-Cookie.
        foreach ($this->cookies as $name => $cookie) {
            setcookie($name, $cookie['value'], $cookie['options']);
        }

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
