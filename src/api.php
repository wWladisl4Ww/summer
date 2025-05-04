<?php
require 'db.php';

header('Content-Type: application/json');

// Определяем метод запроса
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // Получение объектов недвижимости или сделок клиента
        $type = $_GET['type'] ?? null;
        $minPrice = $_GET['minPrice'] ?? null;
        $maxPrice = $_GET['maxPrice'] ?? null;
        $address = $_GET['address'] ?? null;
        $sort = $_GET['sort'] ?? 'asc';
        $agent_id = $_GET['agent_id'] ?? null;
        $client_id = $_GET['client_id'] ?? null;
        $favorites = $_GET['favorites'] ?? null;
        $sold = $_GET['sold'] ?? null;

        if ($sold === 'true') {
            $query = "
                SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
                FROM properties p
                JOIN agents a ON p.agent_id = a.id
                WHERE p.is_sold = TRUE
                ORDER BY p.created_at DESC
            ";
            $stmt = $pdo->query($query);
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($properties);
            break;
        }

        if ($sold === 'false') {
            $query = "
                SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
                FROM properties p
                JOIN agents a ON p.agent_id = a.id
                WHERE p.is_sold = FALSE
                ORDER BY p.created_at DESC
            ";
            $stmt = $pdo->query($query);
            $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($properties);
            break;
        }

        if ($favorites && $client_id) {
            $query = "
                SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
                FROM favorites f
                JOIN properties p ON f.property_id = p.id
                JOIN agents a ON p.agent_id = a.id
                WHERE f.client_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$client_id]);
            $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($favorites);
            break;
        }

        if ($client_id) {
            $query = "
                SELECT d.id, d.property_id, d.price, d.deal_date, d.is_confirmed, p.address
                FROM deals d
                JOIN properties p ON d.property_id = p.id
                WHERE d.client_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$client_id]);
            $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($deals);
            break;
        }

        // Получение сделок агента
        if ($agent_id) {
            $query = "
                SELECT d.id, d.property_id, d.price, d.deal_date, d.is_confirmed, 
                       p.address AS property_address, c.name AS client_name
                FROM deals d
                JOIN properties p ON d.property_id = p.id
                JOIN clients c ON d.client_id = c.id
                WHERE d.agent_id = ?
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$agent_id]);
            $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($deals);
            break;
        }

        $query = "
            SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
            FROM properties p
            JOIN agents a ON p.agent_id = a.id
            WHERE 1=1
        ";

        $params = [];
        if ($agent_id) {
            $query .= " AND p.agent_id = ?";
            $params[] = $agent_id;
        }
        if ($type) {
            $query .= " AND p.type = ?";
            $params[] = $type;
        }
        if ($minPrice) {
            $query .= " AND p.price >= ?";
            $params[] = $minPrice;
        }
        if ($maxPrice) {
            $query .= " AND p.price <= ?";
            $params[] = $maxPrice;
        }
        if ($address) {
            $query .= " AND p.address ILIKE ?";
            $params[] = "%$address%";
        }

        $query .= " ORDER BY p.price $sort";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($properties);
        break;

    case 'POST':
        // Проверяем, что это запрос на покупку объекта
        if (isset($input['client_id'], $input['property_id'], $input['price']) && !isset($input['address'])) {
            $client_id = (int) $input['client_id'];
            $property_id = (int) $input['property_id'];
            $price = (float) $input['price'];

            if ($client_id > 0 && $property_id > 0 && $price > 0) {
                // Получаем ID агента, связанного с объектом
                $stmt = $pdo->prepare("SELECT agent_id FROM properties WHERE id = ?");
                $stmt->execute([$property_id]);
                $agent_id = $stmt->fetchColumn();

                if ($agent_id) {
                    $pdo->beginTransaction();
                    try {
                        // Создаём сделку
                        $stmt = $pdo->prepare("INSERT INTO deals (property_id, client_id, agent_id, price) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$property_id, $client_id, $agent_id, $price]);

                        // Обновляем статус объекта на "продан"
                        $stmt = $pdo->prepare("UPDATE properties SET is_sold = TRUE WHERE id = ?");
                        $stmt->execute([$property_id]);

                        $pdo->commit();
                        echo json_encode(['message' => 'Сделка успешно создана']);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        http_response_code(500);
                        echo json_encode(['message' => 'Ошибка при создании сделки']);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['message' => 'Объект не найден']);
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
        if (isset($input['address'], $input['price'], $input['type'], $input['description'], $input['agent_id'])) {
            $address = trim($input['address']);
            $price = (float) $input['price'];
            $type = trim($input['type']);
            $description = trim($input['description']);
            $agent_id = (int) $input['agent_id'];

            if ($address && $price > 0 && $type && $description && $agent_id > 0) {
                $stmt = $pdo->prepare("INSERT INTO properties (address, price, type, description, agent_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$address, $price, $type, $description, $agent_id]);
                echo json_encode(['message' => 'Объект успешно добавлен']);
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
        // Обновление объекта недвижимости
        if (isset($input['id'], $input['address'], $input['price'], $input['type'], $input['description'])) {
            $id = (int) $input['id'];
            $address = trim($input['address']);
            $price = (float) $input['price'];
            $type = trim($input['type']);
            $description = trim($input['description']);

            if ($id > 0 && $address && $price > 0 && $type && $description) {
                $stmt = $pdo->prepare("UPDATE properties SET address = ?, price = ?, type = ?, description = ? WHERE id = ?");
                $stmt->execute([$address, $price, $type, $description, $id]);
                echo json_encode(['message' => 'Объект успешно обновлён']);
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

        http_response_code(400);
        echo json_encode(['message' => 'Отсутствует идентификатор объекта']);
        break;

    case 'PATCH':
        // Подтверждение сделки агентом
        $id = $input['id'] ?? 0;

        $stmt = $pdo->prepare("UPDATE deals SET is_confirmed = TRUE WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['message' => 'Deal confirmed successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}
?>
