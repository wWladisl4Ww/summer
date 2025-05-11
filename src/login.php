<?php
require 'db.php';
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role']; // 'agent' или 'client'
    $login = $_POST['login'];
    $password = $_POST['password'];

    try {
        if ($role === 'agent') {
            $stmt = $pdo->prepare("SELECT * FROM agents WHERE login = ?");
        } elseif ($role === 'client') {
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE login = ?");
        } else {
            throw new Exception('Неверная роль.');
        }

        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $role;
            $_SESSION['login'] = $login; // Сохраняем логин в сессии

            header('Location: index.php');
            exit;
        } else {
            $message = '<div class="alert alert-danger">Неверный логин или пароль.</div>';
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Ошибка авторизации. Попробуйте позже.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Вход</h1>
        <div class="registration-container mt-4">
            <?= $message ?>
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
                <button type="submit" class="btn btn-primary w-100">Войти</button>
            </form>
            <div class="text-center mt-3">
                <a href="register.php" class="btn btn-success w-100 btn-spacing">Зарегистрироваться</a>
                <a href="index.php" class="text-link">Продолжить без регистрации</a> <!-- Изменён стиль кнопки -->
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
