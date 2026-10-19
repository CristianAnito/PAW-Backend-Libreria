<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validación de datos de entrada en el servidor.
 * Nunca se confía en la validación del navegador: puede saltearse enviando la petición a mano.
 *
 * Uso:
 *   $errors = Validator::validate($request->body, [
 *       'nombre'   => ['required', 'min:2', 'max:50'],
 *       'email'    => ['required', 'email'],
 *       'cantidad' => ['required', 'integer', 'between:1,5'],
 *   ]);
 *   // $errors = ['email' => 'Ingresá un email válido.', ...]  (vacío si todo está bien)
 *
 * Reglas: required, min:N y max:N (largo del texto), email, integer, between:A,B (número),
 *         regex:/patrón/u, o una función fn ($valor): ?string que devuelve el mensaje de error o null.
 */
final class Validator
{
    /**
     * @return array<string, string> Primer error de cada campo inválido.
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';

            // Un campo enviado como array (campo[]=...) nunca es válido en estos formularios.
            if (!is_string($value)) {
                $errors[$field] = 'El valor ingresado no es válido.';
                continue;
            }
            $value = trim($value);

            // Campo opcional y vacío: no se aplican las demás reglas.
            if ($value === '' && !in_array('required', $fieldRules, true)) {
                continue;
            }

            foreach ($fieldRules as $rule) {
                $error = self::check($value, $rule);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // un mensaje por campo
                }
            }
        }

        return $errors;
    }

    private static function check(string $value, string|callable $rule): ?string
    {
        if (is_callable($rule) && !is_string($rule)) {
            return $rule($value);
        }

        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);

        return match ($name) {
            'required' => $value === '' ? 'Este campo es obligatorio.' : null,
            'min'      => mb_strlen($value) < (int) $arg ? "Debe tener al menos {$arg} caracteres." : null,
            'max'      => mb_strlen($value) > (int) $arg ? "Debe tener como máximo {$arg} caracteres." : null,
            'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Ingresá un email válido.' : null,
            'integer'  => filter_var($value, FILTER_VALIDATE_INT) === false ? 'Debe ser un número entero.' : null,
            'between'  => self::between($value, $arg),
            'regex'    => preg_match($arg, $value) !== 1 ? 'El formato no es válido.' : null,
            default    => throw new \InvalidArgumentException("Regla de validación desconocida: {$name}"),
        };
    }

    private static function between(string $value, string $range): ?string
    {
        [$min, $max] = array_map('intval', explode(',', $range));
        $number = filter_var($value, FILTER_VALIDATE_INT);

        return $number === false || $number < $min || $number > $max
            ? "Debe ser un número entre {$min} y {$max}."
            : null;
    }
}
