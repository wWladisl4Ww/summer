<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$client_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои сделки</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 class="text-center">Мои сделки</h1>
        <div id="deals" class="row mt-4"></div>
    </div>

    <script>
        async function loadDeals() {
            try {
                const response = await fetch(`api.php?client_id=<?= $client_id ?>&deals=true`);
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Не удалось загрузить сделки');
                }

                const deals = result.deals || [];
                const dealsContainer = document.getElementById('deals');
                dealsContainer.innerHTML = ''; // Очищаем контейнер

                if (deals.length === 0) {
                    dealsContainer.innerHTML = '<p class="empty-section-message">У вас пока нет сделок</p>';
                    return;
                }

                deals.forEach(deal => {
                    const photo = deal.property.photos && deal.property.photos.length > 0
                        ? `<img src="${deal.property.photos[0]}" class="card-img-top" alt="Фото объекта">`
                        : `<div class="card-img-top">Нет фото</div>`; // Текст "Нет фото" на фоне

                    const dealCard = `
                        <div class="col-md-4">
                            <div class="card mb-4">
                                ${photo}
                                <div class="card-body">
                                    <h5 class="card-title">${deal.property.address}</h5>
                                    <p class="card-text">
                                        <strong>Тип:</strong> ${deal.property.type}<br>
                                        <strong>Цена:</strong> ${deal.price} ₽<br>
                                        <strong>Дата сделки:</strong> ${deal.deal_date}<br>
                                        <strong>Статус:</strong> ${deal.is_confirmed ? 'Подтверждена' : 'Ожидает подтверждения'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                    dealsContainer.innerHTML += dealCard;
                });
            } catch (error) {
                console.error('Ошибка при загрузке сделок:', error);
                alert('Ошибка при загрузке сделок.');
            }
        }

        document.addEventListener('DOMContentLoaded', loadDeals);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>