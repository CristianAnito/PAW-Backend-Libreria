<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Config, Logger, Mailer, Request, Response, Validator};
use App\Models\Order;
use RuntimeException;

/**
 * Finalizar compra: formulario con los datos del cliente, validación en el servidor
 * y envío del pedido por mail al personal de la librería.
 */
final class CheckoutController extends Controller
{
    private const FIELDS = ['nombre', 'apellido', 'documento', 'email', 'telefono'];

    /**
     * GET /compra
     */
    public function form(Request $request): Response
    {
        if ($this->cart()->isEmpty()) {
            return Response::redirect('/carrito', 303);
        }

        return $this->showForm([]);
    }

    /**
     * POST /compra
     */
    public function submit(Request $request): Response
    {
        $cart = $this->cart();
        if ($cart->isEmpty()) {
            return Response::redirect('/carrito', 303);
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

        // Valores ingresados (sin espacios extremos), para volver a mostrarlos si hay errores.
        $old = [];
        foreach (self::FIELDS as $field) {
            $value = $request->input($field, '');
            $old[$field] = is_string($value) ? trim($value) : '';
        }

        if ($errors !== []) {
            // 422 Unprocessable Content: la petición llegó bien pero los datos no son válidos.
            return $this->showForm($old, $errors, 422);
        }

        $order = new Order(
            $old['nombre'],
            $old['apellido'],
            $old['documento'],
            $old['email'],
            $old['telefono'],
            $cart->lines(),
            $cart->total(),
        );

        try {
            $this->mailer()->send(
                Config::get('mail.to'),
                "Nueva compra de {$order->firstName} {$order->lastName}",
                $this->view->render('emails/order', ['order' => $order], layout: null),
                replyTo: $order->email,
            );
        } catch (RuntimeException) {
            // El detalle ya quedó en el log (ver Mailer); al usuario, un mensaje genérico.
            $errors['general'] = 'No pudimos registrar tu compra en este momento. Intentá nuevamente más tarde.';
            return $this->showForm($old, $errors, 503);
        }

        $cart->clear();

        // Post/Redirect/Get: si el usuario recarga la confirmación, no se reenvía el pedido.
        return Response::redirect('/compra/confirmada', 303);
    }

    /**
     * GET /compra/confirmada
     */
    public function confirmed(Request $request): Response
    {
        return $this->render('checkout/confirmed', [
            'title'    => 'Compra registrada',
            'extraCss' => ['/assets/css/formulario.css', '/assets/css/compra.css'],
        ]);
    }

    private function showForm(array $old, array $errors = [], int $status = 200): Response
    {
        $cart = $this->cart();

        return $this->render('checkout/form', [
            'title'    => 'Finalizar compra',
            'extraCss' => ['/assets/css/formulario.css', '/assets/css/compra.css'],
            'lines'    => $cart->lines(),
            'total'    => $cart->total(),
            'old'      => $old,
            'errors'   => $errors,
        ], $status);
    }

    private function mailer(): Mailer
    {
        $logger = Logger::create(Config::get('app.log.file'), Config::get('app.log.level'));
        return new Mailer(Config::get('mail'), $logger);
    }
}
