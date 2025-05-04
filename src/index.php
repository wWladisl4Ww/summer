<?php
require 'db.php';
session_start();

$profile_url = '';
$profile_info = '';
if (isset($_SESSION['role'])) {
    $profile_url = $_SESSION['role'] === 'agent' ? 'agent_profile.php' : 'client_profile.php';
    $profile_info = $_SESSION['role'] === 'agent' ? 'Агент' : 'Клиент';
    $profile_info .= ' (' . ($_SESSION['login'] ?? 'Неизвестно') . ')';
}

// Предопределённые категории типов недвижимости
$property_types = ['Квартира', 'Дом', 'Вилла', 'Офис', 'Коммерческая недвижимость'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Агентство недвижимости</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Объекты недвижимости</h1>
        <?php if ($profile_url): ?>
            <div class="d-flex justify-content-end align-items-center mb-3">
                <span class="me-3 text-white"><?= $profile_info ?></span>
                <a href="<?= $profile_url ?>" class="btn btn-primary">Перейти в профиль</a>
            </div>
        <?php endif; ?>
        <div class="mb-4">
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <label for="type" class="form-label">Тип</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Все</option>
                        <?php foreach ($property_types as $type): ?>
                            <option value="<?= $type ?>"><?= $type ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="minPrice" class="form-label">Мин. цена</label>
                    <input type="number" class="form-control" id="minPrice" name="minPrice" placeholder="0">
                </div>
                <div class="col-md-4">
                    <label for="maxPrice" class="form-label">Макс. цена</label>
                    <input type="number" class="form-control" id="maxPrice" name="maxPrice" placeholder="1000000">
                </div>
                <div class="col-md-4">
                    <label for="address" class="form-label">Адрес</label>
                    <input type="text" class="form-control" id="address" name="address" placeholder="Например, Main St">
                </div>
                <div class="col-md-4">
                    <label for="sort" class="form-label">Сортировка</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="asc">Цена: по возрастанию</option>
                        <option value="desc">Цена: по убыванию</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Применить фильтр</button>
                </div>
            </form>
        </div>
        <div id="properties" class="row mt-4"></div>
    </div>

    <script>
        // Функция для добавления объекта в избранное
        async function addToFavorites(propertyId) {
            const clientId = <?= isset($_SESSION['user_id']) && $_SESSION['role'] === 'client' ? $_SESSION['user_id'] : 'null' ?>;
            if (!clientId) {
                alert('Вы должны войти как клиент, чтобы добавлять в избранное.');
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ client_id: clientId, property_id: propertyId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                } else {
                    alert(result.message || 'Ошибка при добавлении в избранное.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при добавлении в избранное. Проверьте консоль для подробностей.');
            }
        }

        // Функция для покупки объекта недвижимости
        async function buyProperty(propertyId, price) {
            const clientId = <?= isset($_SESSION['user_id']) && $_SESSION['role'] === 'client' ? $_SESSION['user_id'] : 'null' ?>;
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
                } else {
                    alert(result.message || 'Ошибка при покупке объекта.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при покупке объекта. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки объектов недвижимости с фильтрацией
        async function loadProperties(filters = {}) {
            try {
                const queryParams = new URLSearchParams(filters).toString();
                const response = await fetch(`api.php?${queryParams}`);
                if (!response.ok) throw new Error('Не удалось загрузить объекты');
                const properties = await response.json();

                const propertiesContainer = document.getElementById('properties');
                propertiesContainer.innerHTML = ''; // Очищаем контейнер

                if (properties.length === 0) {
                    propertiesContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
                    return;
                }

                properties.forEach(property => {
                    const propertyCard = `
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
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'client'): ?>
                                            <button class="btn btn-outline-primary btn-sm" onclick="addToFavorites(${property.id})">В избранное</button>
                                            <button class="btn btn-success btn-sm" onclick="buyProperty(${property.id}, ${property.price_rub})">Купить</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    propertiesContainer.innerHTML += propertyCard;
                });
            } catch (error) {
                console.error(error);
                alert('Ошибка при загрузке объектов. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки проданных объектов
        async function loadSoldProperties() {
            try {
                const response = await fetch('api.php?sold=true');
                if (!response.ok) throw new Error('Не удалось загрузить проданные объекты');
                const properties = await response.json();

                const soldPropertiesContainer = document.getElementById('soldProperties');
                soldPropertiesContainer.innerHTML = ''; // Очищаем контейнер

                if (properties.length === 0) {
                    soldPropertiesContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
                    return;
                }

                properties.forEach(property => {
                    const propertyCard = `
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
                                    <span class="badge bg-secondary">Продан</span>
                                </div>
                            </div>
                        </div>
                    `;
                    soldPropertiesContainer.innerHTML += propertyCard;
                });
            } catch (error) {
                console.error(error);
                alert('Ошибка при загрузке проданных объектов. Проверьте консоль для подробностей.');
            }
        }

        // Обработчик формы фильтрации
        document.getElementById('filterForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const filters = {
                type: document.getElementById('type').value,
                minPrice: document.getElementById('minPrice').value,
                maxPrice: document.getElementById('maxPrice').value,
                address: document.getElementById('address').value,
                sort: document.getElementById('sort').value,
            };

            loadProperties(filters);
        });

        // Загружаем данные при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            loadProperties();
            loadSoldProperties();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <div class="container mt-5">
        <h2 class="text-center">Проданные объекты</h2>
        <div id="soldProperties" class="row mt-4"></div>
    </div>
</body>
</html>