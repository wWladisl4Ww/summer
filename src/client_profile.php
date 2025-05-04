<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$client_id = $_SESSION['user_id'];
$profile_info = 'Клиент (' . ($_SESSION['login'] ?? 'Неизвестно') . ')';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль клиента</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-end align-items-center mb-3">
            <span class="me-3 text-white"><?= $profile_info ?></span>
            <a href="index.php" class="btn btn-secondary me-2">На главную</a>
            <form method="POST" action="logout.php" class="m-0">
                <button type="submit" class="btn btn-danger">Выйти</button>
            </form>
        </div>
        <h1 class="text-center">Мои сделки</h1>
        <div id="deals" class="row mt-4"></div>

        <h1 class="text-center mt-5">Избранное</h1>
        <div id="favorites" class="row mt-4"></div>
    </div>

    <script>
        // Функция для загрузки сделок клиента
        async function loadClientDeals() {
            try {
                const response = await fetch(`api.php?client_id=${<?= $client_id ?>}`);
                if (!response.ok) throw new Error('Не удалось загрузить сделки');
                const deals = await response.json();

                const dealsContainer = document.getElementById('deals');
                dealsContainer.innerHTML = ''; // Очищаем контейнер

                if (deals.length === 0) {
                    dealsContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
                    return;
                }

                deals.forEach(deal => {
                    const dealCard = `
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Сделка #${deal.id}</h5>
                                    <p class="card-text">
                                        <strong>Объект:</strong> ${deal.address || 'Неизвестно'}<br>
                                        <strong>Тип:</strong> ${deal.type || 'Неизвестно'}<br>
                                        <strong>Цена:</strong> ${deal.price || 'Неизвестно'} ₽<br>
                                        <strong>Дата:</strong> ${deal.deal_date || 'Неизвестно'}<br>
                                        <strong>Статус:</strong> ${deal.is_confirmed ? 'Подтверждена' : 'Ожидает подтверждения'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                    dealsContainer.innerHTML += dealCard;
                });
            } catch (error) {
                console.error(error);
                alert('Ошибка при загрузке сделок. Проверьте консоль для подробностей.');
            }
        }

        // Функция для покупки объекта недвижимости
        async function buyProperty(propertyId, price) {
            const clientId = <?= $client_id ?>;
            if (!clientId) {
                alert('Вы должны войти как клиент, чтобы покупать объекты.');
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ client_id: clientId, property_id: propertyId, price })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadFavorites(); // Обновляем избранное после покупки
                    loadClientDeals(); // Обновляем сделки
                } else {
                    alert(result.message || 'Ошибка при покупке объекта.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при покупке объекта. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки избранного
        async function loadFavorites() {
            try {
                const response = await fetch(`api.php?favorites=true&client_id=${<?= $client_id ?>}`);
                if (!response.ok) throw new Error('Не удалось загрузить избранное');
                const favorites = await response.json();

                const favoritesContainer = document.getElementById('favorites');
                favoritesContainer.innerHTML = ''; // Очищаем контейнер

                if (favorites.length === 0) {
                    favoritesContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
                    return;
                }

                favorites.forEach(property => {
                    const favoriteCard = `
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">${property.address}</h5>
                                    <p class="card-text">
                                        <strong>Тип:</strong> ${property.type}<br>
                                        <strong>Цена:</strong> ${property.price_rub} ₽<br>
                                        <strong>Описание:</strong> ${property.description}<br>
                                        <strong>Продавец:</strong> ${property.agent_login}
                                    </p>
                                    <div class="d-flex justify-content-between mt-3">
                                        <button class="btn btn-outline-danger btn-sm" onclick="removeFromFavorites(${property.id})">Удалить из избранного</button>
                                        <button class="btn btn-success btn-sm" onclick="buyProperty(${property.id}, ${property.price_rub})">Купить</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    favoritesContainer.innerHTML += favoriteCard;
                });
            } catch (error) {
                console.error(error);
                alert('Ошибка при загрузке избранного. Проверьте консоль для подробностей.');
            }
        }

        // Функция для удаления объекта из избранного
        async function removeFromFavorites(propertyId) {
            try {
                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ client_id: <?= $client_id ?>, property_id: propertyId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadFavorites(); // Обновляем список избранного
                } else {
                    alert(result.message || 'Ошибка при удалении из избранного.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при удалении из избранного. Проверьте консоль для подробностей.');
            }
        }

        // Загружаем данные при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            loadClientDeals();
            loadFavorites();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
