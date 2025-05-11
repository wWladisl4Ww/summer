<?php
require 'db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'agent') {
    die('Доступ запрещён');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = $_POST['address'];
    $price = $_POST['price'];
    $type = $_POST['type'];
    $description = $_POST['description'];
    $agent_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO properties (address, price, type, description, agent_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$address, $price, $type, $description, $agent_id]);
        $property_id = $pdo->lastInsertId();

        // Обработка фотографий (если они есть)
        if (!empty($_FILES['photos']['name'][0])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadedFiles = [];
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
                $fileName = basename($_FILES['photos']['name'][$key]);
                $targetFilePath = $uploadDir . $fileName;
                if (move_uploaded_file($tmpName, $targetFilePath)) {
                    $stmt = $pdo->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
                    $stmt->execute([$property_id, $targetFilePath]);
                    $uploadedFiles[] = $targetFilePath;
                }
            }

            // Отладочная информация
            echo '<pre>';
            print_r(['uploaded_files' => $uploadedFiles, 'files_data' => $_FILES]);
            echo '</pre>';
            exit;
        }

        $message = '<div class="alert alert-success">Объект успешно добавлен!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка при добавлении объекта. Попробуйте позже.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить объект недвижимости</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 class="text-center">Добавить объект недвижимости</h1>
        <?= $message ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="address" class="form-label">Адрес</label>
                <input type="text" class="form-control" id="address" name="address" required>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Цена</label>
                <input type="number" class="form-control" id="price" name="price" required>
            </div>
            <div class="mb-3">
                <label for="type" class="form-label">Тип</label>
                <select class="form-select" id="type" name="type" required>
                    <option value="Квартира">Квартира</option>
                    <option value="Дом">Дом</option>
                    <option value="Вилла">Вилла</option>
                    <option value="Офис">Офис</option>
                    <option value="Коммерческая недвижимость">Коммерческая недвижимость</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Описание</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <div class="mb-3">
                <label for="photos" class="form-label">Фотографии</label>
                <input type="file" class="form-control" id="photos" name="photos[]" multiple>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>