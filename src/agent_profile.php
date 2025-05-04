<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: login.php');
    exit;
}

$agent_id = $_SESSION['user_id'];
$profile_info = 'Агент (' . ($_SESSION['login'] ?? 'Неизвестно') . ')';

// Предопределённые категории типов недвижимости
$property_types = ['Квартира', 'Дом', 'Вилла', 'Офис', 'Коммерческая недвижимость'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль агента</title>
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

        <h1 class="text-center mt-5">Активные объекты недвижимости</h1>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPropertyModal">Добавить объект</button>
        <div id="activeProperties" class="row mt-4"></div>

        <h2 class="text-center mt-5">Проданные объекты</h2>
        <div id="soldProperties" class="row mt-4"></div>

        <!-- Модальное окно для добавления объекта -->
        <div class="modal fade" id="addPropertyModal" tabindex="-1" aria-labelledby="addPropertyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPropertyModalLabel">Добавить объект</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addPropertyForm">
                            <div class="mb-3">
                                <label for="address" class="form-label">Адрес</label>
                                <input type="text" class="form-control" id="address" required>
                            </div>
                            <div class="mb-3">
                                <label for="price" class="form-label">Цена</label>
                                <input type="number" class="form-control" id="price" required>
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Тип</label>
                                <select class="form-select" id="type" required>
                                    <option value="">Выберите тип</option>
                                    <?php foreach ($property_types as $type): ?>
                                        <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Описание</label>
                                <textarea class="form-control" id="description" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Добавить</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно для редактирования объекта -->
        <div class="modal fade" id="editPropertyModal" tabindex="-1" aria-labelledby="editPropertyModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPropertyModalLabel">Редактировать объект</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editPropertyForm">
                            <input type="hidden" id="editPropertyId">
                            <div class="mb-3">
                                <label for="editAddress" class="form-label">Адрес</label>
                                <input type="text" class="form-control" id="editAddress" required>
                            </div>
                            <div class="mb-3">
                                <label for="editPrice" class="form-label">Цена</label>
                                <input type="number" class="form-control" id="editPrice" required>
                            </div>
                            <div class="mb-3">
                                <label for="editType" class="form-label">Тип</label>
                                <select class="form-select" id="editType" required>
                                    <option value="">Выберите тип</option>
                                    <?php foreach ($property_types as $type): ?>
                                        <option value="<?= $type ?>"><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editDescription" class="form-label">Описание</label>
                                <textarea class="form-control" id="editDescription" rows="3" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Функция для загрузки активных объектов недвижимости агента
        async function loadActiveProperties() {
            try {
                const response = await fetch(`api.php?sold=false&agent_id=${<?= $agent_id ?>}`);
                if (!response.ok) throw new Error('Не удалось загрузить активные объекты');
                const properties = await response.json();

                const activePropertiesContainer = document.getElementById('activeProperties');
                activePropertiesContainer.innerHTML = ''; // Очищаем контейнер

                if (properties.length === 0) {
                    activePropertiesContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
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
                                        <strong>Описание:</strong> ${property.description}
                                    </p>
                                    <div class="d-flex justify-content-between mt-3">
                                        <button class="btn btn-warning btn-sm" onclick="editProperty(${property.id}, '${property.address}', ${property.price_rub}, '${property.type}', '${property.description}')">Редактировать</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProperty(${property.id})">Удалить</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    activePropertiesContainer.innerHTML += propertyCard;
                });
            } catch (error) {
                console.error(error);
                alert('Ошибка при загрузке активных объектов. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки проданных объектов агента
        async function loadSoldProperties() {
            try {
                const response = await fetch(`api.php?sold=true&agent_id=${<?= $agent_id ?>}`);
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
                                        <strong>Описание:</strong> ${property.description}
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

        // Функция для открытия модального окна редактирования
        function openEditModal(id, address, price, type, description) {
            document.getElementById('editPropertyId').value = id;
            document.getElementById('editAddress').value = address;
            document.getElementById('editPrice').value = price;
            document.getElementById('editType').value = type;
            document.getElementById('editDescription').value = description;

            const editModal = new bootstrap.Modal(document.getElementById('editPropertyModal'));
            editModal.show();
        }

        // Функция для редактирования объекта недвижимости
        function editProperty(id, address, price, type, description) {
            document.getElementById('editPropertyId').value = id;
            document.getElementById('editAddress').value = address;
            document.getElementById('editPrice').value = price;
            document.getElementById('editType').value = type;
            document.getElementById('editDescription').value = description;

            const editModal = new bootstrap.Modal(document.getElementById('editPropertyModal'));
            editModal.show();
        }

        // Обработчик формы редактирования объекта
        document.getElementById('editPropertyForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const id = document.getElementById('editPropertyId').value;
            const address = document.getElementById('editAddress').value.trim();
            const price = parseFloat(document.getElementById('editPrice').value);
            const type = document.getElementById('editType').value;
            const description = document.getElementById('editDescription').value.trim();

            if (!id || !address || !price || !type || !description) {
                alert('Все поля должны быть заполнены.');
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id,
                        address,
                        price,
                        type,
                        description
                    })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editPropertyModal'));
                    editModal.hide();
                    loadActiveProperties(); // Обновляем список активных объектов
                } else {
                    alert(result.message || 'Ошибка при редактировании объекта.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при редактировании объекта. Проверьте консоль для подробностей.');
            }
        });

        // Функция для добавления объекта недвижимости
        document.getElementById('addPropertyForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const address = document.getElementById('address').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const type = document.getElementById('type').value;
            const description = document.getElementById('description').value.trim();

            if (!address || !price || !type || !description) {
                alert('Все поля должны быть заполнены.');
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        address,
                        price,
                        type,
                        description,
                        agent_id: <?= $agent_id ?>
                    })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadActiveProperties();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addPropertyModal'));
                    modal.hide();
                } else {
                    alert(result.message || 'Ошибка при добавлении объекта.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при добавлении объекта. Проверьте консоль для подробностей.');
            }
        });

        // Функция для удаления объекта недвижимости
        async function deleteProperty(propertyId) {
            if (!confirm('Вы уверены, что хотите удалить этот объект?')) return;

            try {
                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: propertyId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadActiveProperties();
                } else {
                    alert(result.message || 'Ошибка при удалении объекта.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при удалении объекта. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки сделок агента
        async function loadAgentDeals() {
            try {
                const response = await fetch(`api.php?agent_id=${<?= $agent_id ?>}`);
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
                                        <strong>Объект:</strong> ${deal.property_address || 'Неизвестно'}<br>
                                        <strong>Клиент:</strong> ${deal.client_name || 'Неизвестно'}<br>
                                        <strong>Цена:</strong> ${deal.price || 'Неизвестно'} ₽<br>
                                        <strong>Дата:</strong> ${deal.deal_date || 'Неизвестно'}<br>
                                        <strong>Статус:</strong> ${deal.is_confirmed ? 'Подтверждена' : 'Ожидает подтверждения'}
                                    </p>
                                    ${!deal.is_confirmed ? `<button class="btn btn-success btn-sm" onclick="confirmDeal(${deal.id})">Подтвердить</button>` : ''}
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

        // Функция для подтверждения сделки
        async function confirmDeal(dealId) {
            try {
                const response = await fetch('api.php', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: dealId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadAgentDeals(); // Обновляем список сделок
                } else {
                    alert(result.message || 'Ошибка при подтверждении сделки.');
                }
            } catch (error) {
                console.error(error);
                alert('Ошибка при подтверждении сделки. Проверьте консоль для подробностей.');
            }
        }

        // Загружаем данные при загрузке страницы
        document.addEventListener('DOMContentLoaded', () => {
            loadAgentDeals();
            loadActiveProperties();
            loadSoldProperties();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
