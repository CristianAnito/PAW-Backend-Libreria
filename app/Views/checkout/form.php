<?php
/**
 * Formulario de compra (GET /compra, y POST /compra con errores).
 * Estructura tomada de formulario/formulario.html del frontend (sin envío ni datos de tarjeta:
 * no hay coordinación de entrega y el pago es solo con Mercado Pago).
 *
 * Variables: $lines (renglones del carrito), $total, $old (valores ingresados),
 *            $errors (campo => mensaje).
 *
 * Los atributos de validación (required, minlength, pattern...) salen de las constantes
 * de App\Models\Order, las mismas que usa el Validator del servidor.
 */
use App\Models\Order as O;

// Atributos de error de un campo: lo marca como inválido y lo asocia a su mensaje.
$invalid = static fn (string $field): string => isset($errors[$field])
    ? ' aria-invalid="true" aria-describedby="error-' . $field . '"'
    : '';

// Mensaje de error de un campo (si tiene).
$error = static fn (string $field): string => isset($errors[$field])
    ? '<p class="error-campo" id="error-' . $field . '">' . e($errors[$field]) . '</p>'
    : '';
?>
<h1>Finalizar compra</h1>

<form action="/compra" method="post">
    <?php if (isset($errors['general'])): ?>
        <p class="alerta-error" role="alert"><?= e($errors['general']) ?></p>
    <?php elseif ($errors !== []): ?>
        <p class="alerta-error" role="alert">Revisá los campos marcados.</p>
    <?php endif; ?>

    <fieldset>
        <legend>1. Datos personales</legend>
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" required autocomplete="given-name"
               minlength="<?= O::NAME_MIN ?>" maxlength="<?= O::NAME_MAX ?>"
               pattern="<?= e(O::NAME_PATTERN) ?>" title="Solo letras y espacios"
               placeholder="Juan" value="<?= e($old['nombre'] ?? '') ?>"<?= $invalid('nombre') ?>>
        <?= $error('nombre') ?>

        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" required autocomplete="family-name"
               minlength="<?= O::NAME_MIN ?>" maxlength="<?= O::NAME_MAX ?>"
               pattern="<?= e(O::NAME_PATTERN) ?>" title="Solo letras y espacios"
               placeholder="Pérez" value="<?= e($old['apellido'] ?? '') ?>"<?= $invalid('apellido') ?>>
        <?= $error('apellido') ?>

        <label for="documento">Documento</label>
        <input type="text" id="documento" name="documento" required inputmode="numeric"
               pattern="<?= e(O::DNI_PATTERN) ?>" maxlength="8" title="7 u 8 números, sin puntos"
               value="<?= e($old['documento'] ?? '') ?>"<?= $invalid('documento') ?>>
        <?= $error('documento') ?>
    </fieldset>

    <fieldset>
        <legend>2. Contacto</legend>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autocomplete="email"
               maxlength="<?= O::EMAIL_MAX ?>"
               placeholder="tu@email.com" value="<?= e($old['email'] ?? '') ?>"<?= $invalid('email') ?>>
        <?= $error('email') ?>

        <label for="telefono">Teléfono</label>
        <input type="tel" id="telefono" name="telefono" required autocomplete="tel"
               minlength="<?= O::PHONE_MIN ?>" maxlength="<?= O::PHONE_MAX ?>"
               pattern="<?= e(O::PHONE_PATTERN) ?>" title="Solo números y espacios, con + inicial opcional"
               placeholder="2323 123456" value="<?= e($old['telefono'] ?? '') ?>"<?= $invalid('telefono') ?>>
        <?= $error('telefono') ?>
    </fieldset>

    <fieldset>
        <legend>3. Pago</legend>
        <p>El pago se realiza con <strong><?= e(O::PAYMENT_METHOD) ?></strong>.</p>
    </fieldset>

    <button type="submit">Finalizar compra</button>
</form>

<aside>
    <h2>Resumen de compra</h2>
    <dl>
        <?php foreach ($lines as $line): ?>
            <dt><?= e($line['book']->title) ?> × <?= e($line['quantity']) ?></dt>
            <dd><?= e(money($line['subtotal'])) ?></dd>
        <?php endforeach; ?>
        <dt>Total</dt>
        <dd><?= e(money($total)) ?></dd>
    </dl>
    <p><a href="/carrito">Modificar carrito</a></p>
</aside>
