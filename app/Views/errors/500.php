<div class="error-view">
    <h2>500 - Error del servidor</h2>
    <p>Ocurrió un error inesperado. Ya quedó registrado; intentá nuevamente más tarde.</p>
    <a href="/" class="btn">Volver al Inicio</a>

    <?php if (!empty($exception)): ?>
        <!-- Solo en desarrollo (APP_ENV=development). En producción $exception es null. -->
        <details open>
            <summary>Detalle (solo visible en desarrollo)</summary>
            <p><strong><?= e($exception::class) ?>:</strong> <?= e($exception->getMessage()) ?></p>
            <p><?= e($exception->getFile()) ?>:<?= e($exception->getLine()) ?></p>
            <pre><?= e($exception->getTraceAsString()) ?></pre>
        </details>
    <?php endif; ?>
</div>
