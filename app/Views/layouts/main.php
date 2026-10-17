<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- $title lo define cada controlador. Se escapa con e(). -->
    <title><?= e($title ?? 'Librería') ?> | Librería</title>

    <!-- CSS global, con ruta absoluta desde public/. El orden importa: reset primero. -->
    <link rel="stylesheet" href="/assets/css/reset.css">
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- CSS propio de cada página (opcional). -->
    <?php foreach ($extraCss ?? [] as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <main<?= !empty($mainClass) ? ' class="' . e($mainClass) . '"' : '' ?>>
        <?= $content ?>
    </main>

    <?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
