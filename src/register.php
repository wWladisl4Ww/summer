<?php
require 'db.php';

$message = '';
$showLoginButton = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role']; // 'agent' или 'client'
    $login = $_POST['login'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Хэшируем пароль
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    try {
        // Проверяем уникальность логина
        if ($role === 'agent') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE login = ?");
        } elseif ($role === 'client') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE login = ?");
        } else {
            throw new Exception('Неверная роль.');
        }

        $stmt->execute([$login]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $message = '<div class="alert alert-danger">Логин уже занят. Попробуйте другой.</div>';
        } else {
            // Добавляем пользователя
            if ($role === 'agent') {
                $stmt = $pdo->prepare("INSERT INTO agents (login, password, name, phone, email) VALUES (?, ?, ?, ?, ?)");
            } elseif ($role === 'client') {
                $stmt = $pdo->prepare("INSERT INTO clients (login, password, name, phone, email) VALUES (?, ?, ?, ?, ?)");
            }

            $stmt->execute([$login, $password, $name, $phone, $email]);
            $message = '<div class="alert alert-success">Регистрация прошла успешно!</div>';
            $showLoginButton = true;
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка регистрации. Попробуйте позже.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Регистрация</h1>
        <div class="registration-container mt-4">
            <?= $message ?>
            <?php if ($showLoginButton): ?>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary w-100">Перейти на страницу входа</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="role" class="form-label">Выберите роль</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="agent">Агент</option>
                            <option value="client">Клиент</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="login" class="form-label">Логин</label>
                        <input type="text" class="form-control" id="login" name="login" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Пароль</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Имя</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Телефон</label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>