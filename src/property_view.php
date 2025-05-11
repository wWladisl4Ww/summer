<?php
require 'db.php';
session_start();

$property_id = $_GET['id'] ?? null;

if (!$property_id) {
    die('Объект не найден.');
}

$stmt = $pdo->prepare("SELECT p.*, a.login AS agent_login FROM properties p JOIN agents a ON p.agent_id = a.id WHERE p.id = ?");
$stmt->execute([$property_id]);
$property = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$property) {
    die('Объект не найден.');
}

// Проверяем, есть ли фотографии
$stmt = $pdo->prepare("SELECT photo_path FROM property_photos WHERE property_id = ?");
$stmt->execute([$property_id]);
$photos = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []; // Если нет фотографий, возвращаем пустой массив
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр объекта</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 class="text-center">Просмотр объекта</h1>
        <div class="property-details">
            <h2><?= htmlspecialchars($property['address']) ?></h2>
            <p><strong>Тип:</strong> <?= htmlspecialchars($property['type']) ?></p>
            <p><strong>Цена:</strong> <?= htmlspecialchars($property['price']) ?> ₽</p>
            <p><strong>Описание:</strong> <?= htmlspecialchars($property['description']) ?></p>
            <p><strong>Продавец:</strong> <?= htmlspecialchars($property['agent_login']) ?></p>
        </div>

        <div class="property-photos">
            <h3>Фотографии</h3>
            <div class="row">
                <?php foreach ($photos as $photo): ?>
                    <div class="col-md-4">
                        <img src="<?= htmlspecialchars($photo) ?>" class="img-fluid" alt="Фото объекта">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary">Вернуться к списку объектов</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>