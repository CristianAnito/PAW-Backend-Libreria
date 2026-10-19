<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Logger, Mailer, Request, Response, Validator, View};
use App\Models\Order;
use RuntimeException;

/**
 * Finalizar compra: validación en el servidor de los datos del cliente
 * y envío del pedido por mail al personal de la librería.
 * El formulario lo muestra el frontend; acá solo llega el envío (POST con JSON).
 */
final class CheckoutController extends Controller
{
    private const FIELDS = ['nombre', 'apellido', 'documento', 'email', 'telefono'];

    /**
     * POST /api/compra  {"nombre": ..., "apellido": ..., "documento": ..., "email": ..., "telefono": ...}
     *
     * 201: compra registrada. 409: carrito vacío. 422: datos inválidos. 503: no se pudo enviar el mail.
     */
    public function submit(Request $request): Response
    {
        $cart = $this->cart();
        if ($cart->isEmpty()) {
            return Response::error('El carrito está vacío.', 409);
        }

        $name = ['required', 'min:' . Order::NAME_MIN, 'max:' . Order::NAME_MAX, 'regex:' . Order::regex(Order::NAME_PATTERN)];
        $errors = Validator::validate($request->body, [
            'nombre'    => $name,
            'apellido'  => $name,
            'documento' => ['required', 'regex:' . Order::regex(Order::DNI_PATTERN)],
            'email'     => ['required', 'max:' . Order::EMAIL_MAX, 'email'],
            'telefono'  => ['required', 'min:' . Order::PHONE_MIN, 'max:' . Order::PHONE_MAX,
                            'regex:' . Order::regex(Order::PHONE_PATTERN)],
        ]);

        if ($errors !== []) {
            return $this->validationError($errors);
        }

        // Valores ingresados, sin espacios extremos.
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = trim((string) $request->input($field, ''));
        }

        $order = new Order(
            $data['nombre'],
            $data['apellido'],
            $data['documento'],
            $data['email'],
            $data['telefono'],
            $cart->lines(),
            $cart->total(),
        );

        try {
            $this->mailer()->send(
                Config::get('mail.to'),
                "Nueva compra de {$order->firstName} {$order->lastName}",
                (new View(dirname(__DIR__) . '/Views'))->render('emails/order', ['order' => $order]),
                replyTo: $order->email,
            );
        } catch (RuntimeException) {
            // El detalle ya quedó en el log (ver Mailer); al cliente, un mensaje genérico.
            return Response::error('No pudimos registrar tu compra en este momento. Intentá nuevamente más tarde.', 503);
        }

        $cart->clear();

        // 201 Created: el pedido quedó registrado.
        return Response::json(['data' => ['message' => 'Compra registrada.']], 201);
    }

    private function mailer(): Mailer
    {
        $logger = Logger::create(Config::get('app.log.file'), Config::get('app.log.level'));
        return new Mailer(Config::get('mail'), $logger);
    }
}
