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
    <style>
        .status-pending {
            color: orange; /* Или используйте желтый цвет, если предпочитаете */
        }
    </style>
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
        // Функция для загрузки избранного
        async function loadFavorites(clientId) {
            try {
                console.log(`Запрос на загрузку избранного: api.php?client_id=${clientId}&favorites=true`);

                const response = await fetch(`api.php?client_id=${clientId}&favorites=true`);
                const result = await response.json();

                console.log('Ответ сервера для избранного:', result);

                if (!response.ok) {
                    throw new Error(result.message || 'Не удалось загрузить избранное');
                }

                const favorites = result.favorites || [];
                if (!Array.isArray(favorites)) {
                    throw new Error('Некорректный формат данных для избранного');
                }

                const favoritesContainer = document.getElementById('favorites');
                favoritesContainer.innerHTML = ''; // Очищаем контейнер

                if (favorites.length === 0) {
                    favoritesContainer.innerHTML = '<p class="empty-section-message">У вас пока нет избранных объектов</p>';
                    return;
                }

                favorites.forEach(property => {
                    const photo = property.photos && property.photos.length > 0
                        ? `<img src="${property.photos[0]}" class="card-img-top" alt="Фото объекта">`
                        : `<div class="card-img-top">Нет фото</div>`; // Текст "Нет фото" на фоне

                    const propertyCard = `
                        <div class="col-md-4">
                            <div class="card mb-4">
                                ${photo}
                                <div class="card-body">
                                    <h5 class="card-title">${property.address}</h5>
                                    <p class="card-text">
                                        <strong>Тип:</strong> ${property.type}<br>
                                        <strong>Цена:</strong> ${property.price_rub} ₽<br>
                                        <strong>Описание:</strong> ${property.description}
                                    </p>
                                    <div class="d-flex justify-content-between mt-3">
                                        <button class="btn btn-success btn-sm" onclick="buyProperty(${property.id}, ${property.price_rub})">Купить</button>
                                        <button class="btn btn-danger btn-sm" onclick="removeFromFavorites(${property.id})">Удалить из избранного</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    favoritesContainer.innerHTML += propertyCard;
                });
            } catch (error) {
                console.error('Ошибка при загрузке избранного:', error);
                alert('Ошибка при загрузке избранного. Проверьте консоль для подробностей.');
            }
        }

        // Функция для удаления объекта из избранного
        async function removeFromFavorites(propertyId) {
            const clientId = <?= json_encode($_SESSION['user_id'] ?? null) ?>;
            if (!clientId) {
                alert('Вы должны войти как клиент, чтобы удалять объекты из избранного.');
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ client_id: clientId, property_id: propertyId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadFavorites(clientId); // Обновляем список избранного
                } else {
                    alert(result.message || 'Ошибка при удалении из избранного.');
                }
            } catch (error) {
                console.error('Ошибка при удалении из избранного:', error);
                alert('Ошибка при удалении из избранного. Проверьте консоль для подробностей.');
            }
        }

        // Функция для покупки объекта недвижимости
        async function buyProperty(propertyId, price) {
            const clientId = <?= json_encode($_SESSION['user_id'] ?? null) ?>;
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
                    loadFavorites(clientId); // Обновляем список избранного
                    loadClientDeals(clientId); // Обновляем список сделок
                } else {
                    alert(result.message || 'Ошибка при покупке объекта.');
                }
            } catch (error) {
                console.error('Ошибка при покупке объекта:', error);
                alert('Ошибка при покупке объекта. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки сделок клиента
        async function loadClientDeals(clientId) {
            try {
                console.log(`Запрос на загрузку сделок клиента: api.php?client_id=${clientId}&deals=true`);

                const response = await fetch(`api.php?client_id=${clientId}&deals=true`);
                const result = await response.json();

                console.log('Ответ сервера для сделок клиента:', result);

                if (!response.ok) {
                    throw new Error(result.message || 'Не удалось загрузить сделки');
                }

                const deals = result.deals || [];
                if (!Array.isArray(deals)) {
                    throw new Error('Некорректный формат данных для сделок');
                }

                const dealsContainer = document.getElementById('deals');
                dealsContainer.innerHTML = ''; // Очищаем контейнер

                if (deals.length === 0) {
                    dealsContainer.innerHTML = '<p class="empty-section-message">У вас пока нет сделок</p>';
                    return;
                }

                deals.forEach(deal => {
                    const statusClass = deal.is_confirmed ? 'status-confirmed' : 'status-pending'; // Используем класс 'status-pending' для жёлтого текста
                    const statusText = deal.is_confirmed ? 'Подтверждена' : 'Ожидает подтверждения';

                    const dealCard = `
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Сделка #${deal.id}</h5>
                                    <p class="card-text">
                                        <strong>Объект:</strong> ${deal.property_address || 'Неизвестно'}<br>
                                        <strong>Агент:</strong> ${deal.agent_name || 'Неизвестно'}<br>
                                        <strong>Цена:</strong> ${deal.price || 'Неизвестно'} ₽<br>
                                        <strong>Дата:</strong> ${deal.deal_date || 'Неизвестно'}<br>
                                        <strong>Статус:</strong> <span class="${statusClass}">${statusText}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                    dealsContainer.innerHTML += dealCard;
                });
            } catch (error) {
                console.error('Ошибка при загрузке сделок клиента:', error);
                alert('Ошибка при загрузке сделок клиента. Проверьте консоль для подробностей.');
            }
        }

        // Функция для отмены сделки
        async function cancelDeal(dealId) {
            if (!confirm('Вы уверены, что хотите отменить эту сделку?')) return;

            try {
                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: dealId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    const clientId = <?= json_encode($_SESSION['user_id'] ?? null) ?>;
                    loadClientDeals(clientId); // Обновляем список сделок
                } else {
                    alert(result.message || 'Ошибка при отмене сделки.');
                }
            } catch (error) {
                console.error('Ошибка при отмене сделки:', error);
                alert('Ошибка при отмене сделки. Проверьте консоль для подробностей.');
            }
        }

        // Загружаем данные при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            const clientId = <?= json_encode($_SESSION['user_id'] ?? null) ?>;
            if (clientId) {
                loadFavorites(clientId);
                loadClientDeals(clientId);
            } else {
                console.error('Ошибка: client_id не определён.');
                alert('Ошибка: Не удалось загрузить данные клиента.');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
