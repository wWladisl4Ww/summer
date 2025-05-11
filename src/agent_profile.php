<?php
require 'db.php';
session_start();

// Проверяем, что пользователь авторизован как агент
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: login.php');
    exit;
}

$agent_id = $_SESSION['user_id'] ?? null;
$profile_info = 'Агент (' . ($_SESSION['login'] ?? 'Неизвестно') . ')';

// Предопределённые категории типов недвижимости
$property_types = ['Квартира', 'Дом', 'Вилла', 'Офис', 'Коммерческая недвижимость'];

$message = '';

// Обработка удаления объекта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_property_id'])) {
    $property_id = (int)$_POST['delete_property_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ? AND agent_id = ?");
        $stmt->execute([$property_id, $_SESSION['user_id']]);
        $message = '<div class="alert alert-success">Объект успешно удалён.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка при удалении объекта.</div>';
    }
}

// Обработка редактирования объекта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_property_id'])) {
    $property_id = (int)$_POST['edit_property_id'];
    $address = $_POST['address'] ?? '';
    $price = $_POST['price'] ?? 0;
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';

    try {
        $stmt = $pdo->prepare("UPDATE properties SET address = ?, price = ?, type = ?, description = ? WHERE id = ? AND agent_id = ?");
        $stmt->execute([$address, $price, $type, $description, $property_id, $_SESSION['user_id']]);
        $message = '<div class="alert alert-success">Объект успешно обновлён.</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger">Ошибка при обновлении объекта.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = $_POST['address'] ?? '';
    $price = $_POST['price'] ?? 0;
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $agent_id = $_SESSION['user_id'];
    $photo = $_FILES['photo'] ?? null;

    if (!$photo || is_array($photo['name'])) {
        $message = '<div class="alert alert-danger">Можно загрузить только одну фотографию.</div>';
    } else {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $fileType = mime_content_type($photo['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            $message = '<div class="alert alert-danger">Недопустимый тип файла.</div>';
        } else {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '_' . basename($photo['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($photo['tmp_name'], $filePath)) {
                try {
                    $pdo->beginTransaction();

                    // Создаём объект недвижимости
                    $stmt = $pdo->prepare("INSERT INTO properties (agent_id, address, price, type, description) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$agent_id, $address, $price, $type, $description]);
                    $property_id = $pdo->lastInsertId();

                    // Добавляем фото
                    $stmt = $pdo->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
                    $stmt->execute([$property_id, 'uploads/' . $fileName]);

                    $pdo->commit();
                    $message = '<div class="alert alert-success">Объект успешно добавлен с фотографией.</div>';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = '<div class="alert alert-danger">Ошибка при добавлении объекта.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Ошибка при загрузке файла.</div>';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $property_id = $_POST['property_id'] ?? null;
    $photo = $_FILES['photo'];

    if (!$property_id) {
        $message = '<div class="alert alert-danger">Не указан объект недвижимости для загрузки фото.</div>';
    } elseif (is_array($photo['name'])) {
        $message = '<div class="alert alert-danger">Можно загрузить только одно фото.</div>';
    } else {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        $fileType = mime_content_type($photo['tmp_name']);

        if (!in_array($fileType, $allowedTypes)) {
            $message = '<div class="alert alert-danger">Недопустимый тип файла.</div>';
        } else {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = uniqid() . '_' . basename($photo['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($photo['tmp_name'], $filePath)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
                    $stmt->execute([$property_id, 'uploads/' . $fileName]);
                    $message = '<div class="alert alert-success">Фото успешно загружено.</div>';
                } catch (PDOException $e) {
                    $message = '<div class="alert alert-danger">Ошибка при сохранении фото в базе данных.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Ошибка при загрузке файла.</div>';
            }
        }
    }
}
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
                        <form id="addPropertyForm" enctype="multipart/form-data">
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
                                    <option value="">Выберите тип</option>
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
                                <label for="photo" class="form-label">Фото</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*" required>
                                <small class="form-text">Можно загрузить только одно фото.</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Добавить объект</button>
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
                        <form id="editPropertyForm" enctype="multipart/form-data">
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
                            <div class="mb-3">
                                <label for="editPhotos" class="form-label">Фотографии</label>
                                <input type="file" class="form-control" id="editPhotos" name="photos[]" multiple>
                            </div>
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('photo').addEventListener('change', function (e) {
            if (this.files.length > 1) {
                alert('Можно загрузить только один файл.');
                this.value = ''; // Сбрасываем выбор файлов
            }
        });

        // Функция для загрузки активных объектов недвижимости агента
        async function loadActiveProperties(agentId) {
            try {
                const response = await fetch(`api.php?agent_id=${agentId}&sold=false`);
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Не удалось загрузить активные объекты');
                }

                const properties = result.properties || [];
                const activePropertiesContainer = document.getElementById('activeProperties');
                activePropertiesContainer.innerHTML = ''; // Очищаем контейнер

                if (properties.length === 0) {
                    activePropertiesContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
                    return;
                }

                properties.forEach(property => {
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
                                        <strong>Описание:</strong> ${property.description}<br>
                                        <strong>Статус:</strong> ${property.is_sold ? 'Продан' : 'Активен'}
                                    </p>
                                    <div class="d-flex justify-content-between">
                                        <button class="btn btn-warning btn-sm" onclick="editProperty(${property.id}, '${property.address}', ${property.price}, '${property.type}', '${property.description}')">Редактировать</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteProperty(${property.id})">Удалить</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    activePropertiesContainer.innerHTML += propertyCard;
                });
            } catch (error) {
                console.error('Ошибка при загрузке активных объектов:', error);
                alert('Ошибка при загрузке активных объектов. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки проданных объектов агента
        async function loadSoldProperties(agentId) {
            try {
                const response = await fetch(`api.php?agent_id=${agentId}&sold=true`);
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Не удалось загрузить проданные объекты');
                }

                const properties = result.properties || [];
                const soldPropertiesContainer = document.getElementById('soldProperties');
                soldPropertiesContainer.innerHTML = ''; // Очищаем контейнер

                if (properties.length === 0) {
                    soldPropertiesContainer.innerHTML = '<p class="empty-section-message">Этот раздел пока что пуст</p>';
                    return;
                }

                properties.forEach(property => {
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
                                    <span class="badge bg-secondary">Продан</span>
                                </div>
                            </div>
                        </div>
                    `;
                    soldPropertiesContainer.innerHTML += propertyCard;
                });
            } catch (error) {
                console.error('Ошибка при загрузке проданных объектов:', error);
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

            const formData = new FormData(this);
            const photos = document.getElementById('editPhotos').files;

            // Проверяем, что загружается только одна фотография
            if (photos.length > 1) {
                alert('Можно загрузить только одну фотографию.');
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'PUT',
                    body: formData
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadActiveProperties(<?= json_encode($agent_id) ?>); // Обновляем список активных объектов
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editPropertyModal'));
                    modal.hide();
                } else {
                    alert(result.message || 'Ошибка при редактировании объекта.');
                }
            } catch (error) {
                console.error('Ошибка при редактировании объекта:', error);
                alert('Ошибка при редактировании объекта. Проверьте консоль для подробностей.');
            }
        });

        // Функция для добавления объекта недвижимости
        document.getElementById('addPropertyForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('agent_id', <?= json_encode($agent_id) ?>);

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadActiveProperties(<?= json_encode($agent_id) ?>); // Обновляем список активных объектов
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addPropertyModal'));
                    modal.hide();
                } else {
                    alert(result.message || 'Ошибка при добавлении объекта.');
                }
            } catch (error) {
                console.error('Ошибка при добавлении объекта:', error);
                alert('Ошибка при добавлении объекта. Проверьте консоль для подробностей.');
            }
        });

        // Функция для удаления объекта недвижимости
        async function deleteProperty(propertyId) {
            if (!confirm('Вы уверены, что хотите удалить этот объект?')) return;

            try {
                console.log('Отправляем запрос на удаление объекта с ID:', propertyId);

                const response = await fetch('api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: propertyId }) // Передаём корректный параметр id
                });

                const result = await response.json();
                console.log('Ответ сервера на удаление объекта:', result);

                if (response.ok) {
                    alert(result.message);
                    loadActiveProperties(<?= json_encode($agent_id) ?>); // Обновляем список активных объектов
                } else {
                    alert(result.message || 'Ошибка при удалении объекта.');
                }
            } catch (error) {
                console.error('Ошибка при удалении объекта:', error);
                alert('Ошибка при удалении объекта. Проверьте консоль для подробностей.');
            }
        }

        // Функция для загрузки сделок агента
        async function loadAgentDeals(agentId) {
            try {
                console.log(`Запрос на загрузку сделок агента: api.php?agent_id=${agentId}`);

                const response = await fetch(`api.php?agent_id=${agentId}`);
                const result = await response.json();

                console.log('Ответ сервера для сделок агента:', result);

                if (!response.ok) {
                    throw new Error(result.message || 'Не удалось загрузить сделки агента');
                }

                const deals = result.deals || [];
                const dealsContainer = document.getElementById('deals');
                dealsContainer.innerHTML = ''; // Очищаем контейнер

                if (deals.length === 0) {
                    dealsContainer.innerHTML = '<p class="empty-section-message">У вас пока нет сделок</p>';
                    return;
                }

                deals.forEach(deal => {
                    const statusClass = deal.is_confirmed ? 'status-confirmed' : 'status-pending'; // Используем класс 'status-pending' для жёлтого текста
                    const statusText = deal.is_confirmed ? 'Подтверждена' : 'Ожидает подтверждения';

                    const confirmButton = !deal.is_confirmed ? `
                        <button class="btn btn-success btn-sm" onclick="confirmDeal(${deal.id})">Подтвердить</button>
                    ` : '';

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
                                        <strong>Статус:</strong> <span class="${statusClass}">${statusText}</span>
                                    </p>
                                    ${confirmButton}
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
            if (!confirm('Вы уверены, что хотите подтвердить эту сделку?')) return;

            try {
                const response = await fetch('api.php', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: dealId })
                });

                const result = await response.json();
                if (response.ok) {
                    alert(result.message);
                    loadAgentDeals(<?= json_encode($agent_id) ?>); // Обновляем список сделок
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
            const agentId = <?= json_encode($agent_id) ?>; // Передаём agent_id в JavaScript
            if (agentId) {
                loadAgentDeals(agentId);
                loadActiveProperties(agentId);
                loadSoldProperties(agentId);
            } else {
                console.error('Ошибка: agent_id не определён.');
                alert('Ошибка: Не удалось загрузить данные агента.');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
