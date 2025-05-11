<?php
require 'db.php';
require 'helpers.php';

header('Content-Type: application/json');

// Определяем метод запроса
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        try {
            // Получение избранного клиента
            if (!empty($_GET['client_id']) && isset($_GET['favorites'])) {
                $client_id = (int) $_GET['client_id'];
                $favorites = getFavorites($pdo, $client_id);
                echo json_encode(['favorites' => $favorites]);
                exit;
            }

            // Получение сделок клиента
            if (!empty($_GET['client_id']) && isset($_GET['deals'])) {
                $client_id = (int) $_GET['client_id'];
                $deals = getDeals($pdo, $client_id);
                echo json_encode(['deals' => $deals]);
                exit;
            }

            // Получение объектов недвижимости агента
            if (!empty($_GET['agent_id']) && isset($_GET['sold'])) {
                $agent_id = (int) $_GET['agent_id'];
                $is_sold = filter_var($_GET['sold'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'; // Преобразуем в строку

                $query = "
                    SELECT p.*, FLOOR(p.price) AS price_rub
                    FROM properties p
                    WHERE p.agent_id = ? AND p.is_sold = ?
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$agent_id, $is_sold]);
                $properties = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Возвращаем пустой массив, если данных нет

                // Добавляем фотографии к каждому объекту
                foreach ($properties as &$property) {
                    $stmt = $pdo->prepare("SELECT photo_path FROM property_photos WHERE property_id = ?");
                    $stmt->execute([$property['id']]);
                    $property['photos'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []; // Если нет фотографий, возвращаем пустой массив
                }

                echo json_encode([
                    'properties' => $properties,
                    'debug' => [
                        'query' => $query,
                        'agent_id' => $agent_id,
                        'is_sold' => $is_sold,
                        'properties_count' => count($properties),
                    ],
                ]);
                exit;
            }

            // Получение сделок агента
            if (!empty($_GET['agent_id'])) {
                $agent_id = (int) $_GET['agent_id'];
                $query = "
                    SELECT d.id, d.property_id, d.price, 
                           TO_CHAR(d.deal_date, 'YYYY-MM-DD HH24:MI') AS deal_date,
                           d.is_confirmed, p.address AS property_address, c.name AS client_name
                    FROM deals d
                    JOIN properties p ON d.property_id = p.id
                    JOIN clients c ON d.client_id = c.id
                    WHERE d.agent_id = ?
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$agent_id]);
                $deals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; // Возвращаем пустой массив, если данных нет

                echo json_encode([
                    'deals' => $deals,
                    'debug' => [
                        'query' => $query,
                        'agent_id' => $agent_id,
                        'deals_count' => count($deals),
                    ],
                ]);
                exit;
            }

            if ($_GET['sold'] === 'true') {
                $query = "
                    SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
                    FROM properties p
                    JOIN agents a ON p.agent_id = a.id
                    WHERE p.is_sold = TRUE
                    ORDER BY p.created_at DESC
                ";
                $stmt = $pdo->query($query);
                $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['properties' => $properties]);
                exit;
            }

            if (!empty($_GET['type']) || !empty($_GET['minPrice']) || !empty($_GET['maxPrice']) || !empty($_GET['address'])) {
                $filters = [
                    'type' => $_GET['type'] ?? null,
                    'minPrice' => $_GET['minPrice'] ?? null,
                    'maxPrice' => $_GET['maxPrice'] ?? null,
                    'address' => $_GET['address'] ?? null,
                    'sort' => $_GET['sort'] ?? 'asc',
                ];
                $properties = getProperties($pdo, $filters);
                echo json_encode(['properties' => $properties]);
                exit;
            }

            // Получение объектов недвижимости
            $query = "
                SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
                FROM properties p
                JOIN agents a ON p.agent_id = a.id
                WHERE p.is_sold = FALSE
            ";

            $params = [];
            if (!empty($_GET['type'])) {
                $query .= " AND p.type = ?";
                $params[] = $_GET['type'];
            }
            if (!empty($_GET['minPrice'])) {
                $query .= " AND p.price >= ?";
                $params[] = $_GET['minPrice'];
            }
            if (!empty($_GET['maxPrice'])) {
                $query .= " AND p.price <= ?";
                $params[] = $_GET['maxPrice'];
            }
            if (!empty($_GET['address'])) {
                $query .= " AND p.address ILIKE ?";
                $params[] = "%" . $_GET['address'] . "%";
            }
            $query .= " ORDER BY p.price " . ($_GET['sort'] ?? 'asc');

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Добавляем фотографии к каждому объекту
            foreach ($properties as &$property) {
                $stmt = $pdo->prepare("SELECT photo_path FROM property_photos WHERE property_id = ?");
                $stmt->execute([$property['id']]);
                $property['photos'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []; // Если нет фотографий, возвращаем пустой массив
            }

            echo json_encode(['properties' => $properties]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'message' => 'Ошибка при загрузке данных',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        break;

    case 'POST':
        // Загрузка одной фотографии для объекта недвижимости
        if (isset($_POST['property_id']) && isset($_FILES['photo'])) {
            $property_id = (int) $_POST['property_id'];
            $photo = $_FILES['photo'];

            if ($property_id <= 0) {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректный идентификатор объекта']);
                break;
            }

            // Проверяем, что загружается только один файл
            if (is_array($photo['name'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Можно загрузить только один файл']);
                break;
            }

            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileType = mime_content_type($photo['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['message' => 'Недопустимый тип файла', 'file_type' => $fileType]);
                break;
            }

            $fileName = uniqid() . '_' . basename($photo['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($photo['tmp_name'], $filePath)) {
                $stmt = $pdo->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
                $stmt->execute([$property_id, 'uploads/' . $fileName]);
                echo json_encode(['message' => 'Фотография успешно загружена', 'file' => 'uploads/' . $fileName]);
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Ошибка при загрузке файла']);
            }
            break;
        }

        // Загрузка фотографий для объекта недвижимости
        if (isset($_POST['property_id']) && isset($_FILES['photos'])) {
            $property_id = (int) $_POST['property_id'];
            $photos = $_FILES['photos'];

            if ($property_id <= 0) {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректный идентификатор объекта']);
                break;
            }

            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            $requiredWidth = 1000;
            $requiredHeight = 1000;
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadedFiles = [];
            for ($i = 0; $i < count($photos['name']); $i++) {
                $fileType = mime_content_type($photos['tmp_name'][$i]);
                if (!in_array($fileType, $allowedTypes)) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Недопустимый тип файла', 'file_type' => $fileType]);
                    break 2;
                }

                // Проверяем размеры изображения
                $imageInfo = getimagesize($photos['tmp_name'][$i]);
                if ($imageInfo[0] !== $requiredWidth || $imageInfo[1] !== $requiredHeight) {
                    http_response_code(400);
                    echo json_encode([
                        'message' => 'Изображение должно быть размером 1000x1000 пикселей',
                        'file_name' => $photos['name'][$i],
                        'width' => $imageInfo[0],
                        'height' => $imageInfo[1]
                    ]);
                    break 2;
                }

                $fileName = uniqid() . '_' . basename($photos['name'][$i]);
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($photos['tmp_name'][$i], $filePath)) {
                    $stmt = $pdo->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
                    $stmt->execute([$property_id, 'uploads/' . $fileName]);
                    $uploadedFiles[] = 'uploads/' . $fileName;
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Ошибка при загрузке файла']);
                    break 2;
                }
            }

            echo json_encode(['message' => 'Фотографии успешно загружены', 'files' => $uploadedFiles]);
            break;
        }

        // Создание сделки
        if (isset($input['client_id'], $input['property_id'], $input['price'])) {
            $client_id = (int) $input['client_id'];
            $property_id = (int) $input['property_id'];
            $price = (float) $input['price'];

            if ($client_id > 0 && $property_id > 0 && $price > 0) {
                try {
                    $pdo->beginTransaction();

                    // Проверяем, находится ли объект в избранном
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE client_id = ? AND property_id = ?");
                    $stmt->execute([$client_id, $property_id]);
                    $is_in_favorites = $stmt->fetchColumn() > 0;

                    // Удаляем объект из избранного, если он там есть
                    if ($is_in_favorites) {
                        $stmt = $pdo->prepare("DELETE FROM favorites WHERE client_id = ? AND property_id = ?");
                        $stmt->execute([$client_id, $property_id]);
                    }

                    // Создаём сделку
                    $stmt = $pdo->prepare("INSERT INTO deals (property_id, client_id, agent_id, price) 
                                           SELECT ?, ?, agent_id, ? FROM properties WHERE id = ?");
                    $stmt->execute([$property_id, $client_id, $price, $property_id]);

                    $pdo->commit();
                    echo json_encode(['message' => 'Сделка успешно создана. Ожидается подтверждение продавца.']);
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['message' => 'Ошибка при создании сделки', 'error' => $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректные данные для создания сделки']);
            }
            break;
        }

        // Проверяем, что это запрос на добавление в избранное
        if (isset($input['client_id'], $input['property_id']) && !isset($input['price'])) {
            $client_id = (int) $input['client_id'];
            $property_id = (int) $input['property_id'];

            if ($client_id > 0 && $property_id > 0) {
                $stmt = $pdo->prepare("INSERT INTO favorites (client_id, property_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
                $stmt->execute([$client_id, $property_id]);
                echo json_encode(['message' => 'Объект добавлен в избранное']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректные данные для добавления в избранное']);
            }
            break;
        }

        // Добавление нового объекта недвижимости
        if (isset($_POST['address'], $_POST['price'], $_POST['type'], $_POST['description'], $_POST['agent_id'])) {
            $address = trim($_POST['address']);
            $price = (float) $_POST['price'];
            $type = trim($_POST['type']);
            $description = trim($_POST['description']);
            $agent_id = (int) $_POST['agent_id'];

            if ($address && $price > 0 && $type && $description && $agent_id > 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO properties (address, price, type, description, agent_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$address, $price, $type, $description, $agent_id]);
                    $property_id = $pdo->lastInsertId();

                    // Обработка фотографий (если они есть)
                    if (!empty($_FILES['photos']['name'][0])) {
                        $uploadDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        foreach ($_FILES['photos']['tmp_name'] as $key => $tmpName) {
                            $fileName = uniqid() . '_' . basename($_FILES['photos']['name'][$key]);
                            $filePath = $uploadDir . $fileName;
                            if (move_uploaded_file($tmpName, $filePath)) {
                                $stmt = $pdo->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
                                $stmt->execute([$property_id, 'uploads/' . $fileName]);
                            }
                        }
                    }

                    echo json_encode(['message' => 'Объект успешно добавлен']);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['message' => 'Ошибка при добавлении объекта']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректные данные для добавления объекта']);
            }
            break;
        }

        http_response_code(400);
        echo json_encode(['message' => 'Отсутствуют необходимые данные']);
        break;

    case 'PUT':
        // Отладочная информация
        file_put_contents('php://stderr', "PUT Request Input: " . json_encode($input) . "\n");

        // Обновление объекта недвижимости
        if (isset($input['id'], $input['address'], $input['price'], $input['type'], $input['description'])) {
            $id = (int) $input['id'];
            $address = trim($input['address']);
            $price = (float) $input['price'];
            $type = trim($input['type']);
            $description = trim($input['description']);

            if ($id > 0 && $address && $price > 0 && $type && $description) {
                try {
                    $stmt = $pdo->prepare("UPDATE properties SET address = ?, price = ?, type = ?, description = ? WHERE id = ?");
                    $stmt->execute([$address, $price, $type, $description, $id]);
                    echo json_encode(['message' => 'Объект успешно обновлён']);
                } catch (PDOException $e) {
                    http_response_code(500);
                    echo json_encode(['message' => 'Ошибка при обновлении объекта', 'error' => $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректные данные для обновления объекта']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Отсутствуют необходимые данные для обновления объекта']);
        }
        break;

    case 'DELETE':
        // Отладочная информация
        file_put_contents('php://stderr', "DELETE Request Input: " . json_encode($input) . "\n");

        // Удаление объекта из избранного
        if (isset($input['client_id'], $input['property_id'])) {
            $client_id = (int) $input['client_id'];
            $property_id = (int) $input['property_id'];

            if ($client_id > 0 && $property_id > 0) {
                $stmt = $pdo->prepare("DELETE FROM favorites WHERE client_id = ? AND property_id = ?");
                $stmt->execute([$client_id, $property_id]);
                echo json_encode(['message' => 'Объект успешно удалён из избранного']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректные данные для удаления из избранного']);
            }
            break;
        }

        // Удаление объекта недвижимости
        if (isset($input['id'])) {
            $id = (int) $input['id'];

            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['message' => 'Объект успешно удалён']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Некорректный идентификатор объекта']);
            }
            break;
        }

        // Если данные отсутствуют
        http_response_code(400);
        echo json_encode(['message' => 'Отсутствуют необходимые данные']);
        break;

    case 'PATCH':
        // Подтверждение сделки
        if (isset($input['id'])) {
            $id = (int) $input['id'];

            try {
                $pdo->beginTransaction();

                // Подтверждаем сделку
                $stmt = $pdo->prepare("UPDATE deals SET is_confirmed = TRUE WHERE id = ?");
                $stmt->execute([$id]);

                // Получаем ID объекта недвижимости, связанного с этой сделкой
                $stmt = $pdo->prepare("SELECT property_id FROM deals WHERE id = ?");
                $stmt->execute([$id]);
                $property_id = $stmt->fetchColumn();

                // Помечаем объект как "продан"
                $stmt = $pdo->prepare("UPDATE properties SET is_sold = TRUE WHERE id = ?");
                $stmt->execute([$property_id]);

                $pdo->commit();
                echo json_encode(['message' => 'Сделка подтверждена, объект помечен как проданный.']);
            } catch (PDOException $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['message' => 'Ошибка при подтверждении сделки', 'error' => $e->getMessage()]);
            }
            break;
        }

        http_response_code(400);
        echo json_encode(['message' => 'Отсутствуют необходимые данные для подтверждения сделки']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}
?>
