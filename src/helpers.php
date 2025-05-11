<?php

function getFavorites($pdo, $client_id) {
    $query = "
        SELECT p.*, FLOOR(p.price) AS price_rub
        FROM favorites f
        JOIN properties p ON f.property_id = p.id
        WHERE f.client_id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$client_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($favorites as &$favorite) {
        $favorite['photos'] = getPropertyPhotos($pdo, $favorite['id']);
    }

    return $favorites;
}

function getDeals($pdo, $client_id) {
    $query = "
        SELECT d.id, d.property_id, d.price, 
               TO_CHAR(d.deal_date, 'YYYY-MM-DD HH24:MI') AS deal_date,
               d.is_confirmed, p.address AS property_address, a.name AS agent_name
        FROM deals d
        JOIN properties p ON d.property_id = p.id
        JOIN agents a ON d.agent_id = a.id
        WHERE d.client_id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$client_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getPropertyPhotos($pdo, $property_id) {
    $stmt = $pdo->prepare("SELECT photo_path FROM property_photos WHERE property_id = ?");
    $stmt->execute([$property_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function getProperties($pdo, $filters = []) {
    $query = "
        SELECT p.*, FLOOR(p.price) AS price_rub, a.login AS agent_login
        FROM properties p
        JOIN agents a ON p.agent_id = a.id
        WHERE p.is_sold = FALSE
    ";

    $params = [];
    if (!empty($filters['type'])) {
        $query .= " AND p.type = ?";
        $params[] = $filters['type'];
    }
    if (!empty($filters['minPrice'])) {
        $query .= " AND p.price >= ?";
        $params[] = $filters['minPrice'];
    }
    if (!empty($filters['maxPrice'])) {
        $query .= " AND p.price <= ?";
        $params[] = $filters['maxPrice'];
    }
    if (!empty($filters['address'])) {
        $query .= " AND p.address ILIKE ?";
        $params[] = "%" . $filters['address'] . "%";
    }
    $query .= " ORDER BY p.price " . ($filters['sort'] ?? 'asc');

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($properties as &$property) {
        $property['photos'] = getPropertyPhotos($pdo, $property['id']);
    }

    return $properties;
}
