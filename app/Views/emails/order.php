<?php
/**
 * Cuerpo del mail de compra al personal (texto plano, sin layout).
 * Variables: $order (App\Models\Order).
 * Nota: PHP descarta el salto de línea que sigue a "?>", por eso hay líneas en blanco extra.
 */
?>
Nueva compra

Libros
<?php foreach ($order->lines as $line): ?>
- <?= $line['book']->title ?> (<?= $line['book']->author ?>) × <?= $line['quantity'] ?> = <?= money($line['subtotal']) ?>

<?php endforeach; ?>

Total:     <?= money($order->total) ?>

Pago:      <?= $order::PAYMENT_METHOD ?>


Cliente
Nombre:    <?= $order->firstName ?> <?= $order->lastName ?>

DNI:       <?= $order->dni ?>

Email:     <?= $order->email ?>

Teléfono:  <?= $order->phone ?>


Fecha:     <?= date('d/m/Y H:i') ?>


Respondé este mail para contactar al cliente.
