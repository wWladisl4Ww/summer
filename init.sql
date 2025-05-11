-- Удаление всех таблиц, если они существуют
DROP TABLE IF EXISTS deals CASCADE;
DROP TABLE IF EXISTS properties CASCADE;
DROP TABLE IF EXISTS clients CASCADE;
DROP TABLE IF EXISTS agents CASCADE;
DROP TABLE IF EXISTS favorites CASCADE;
DROP TABLE IF EXISTS property_photos CASCADE;

-- Таблица агентов
CREATE TABLE agents (
    id SERIAL PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица клиентов
CREATE TABLE clients (
    id SERIAL PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица объектов недвижимости
CREATE TABLE properties (
    id SERIAL PRIMARY KEY,
    agent_id INT NOT NULL REFERENCES agents(id) ON DELETE CASCADE,
    address VARCHAR(255) NOT NULL,
    price NUMERIC(12, 2) NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('Квартира', 'Дом', 'Вилла', 'Офис', 'Коммерческая недвижимость')),
    description TEXT,
    is_sold BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблица сделок
CREATE TABLE deals (
    id SERIAL PRIMARY KEY,
    property_id INT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    client_id INT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    agent_id INT NOT NULL REFERENCES agents(id) ON DELETE SET NULL,
    deal_date TIMESTAMP(0) DEFAULT CURRENT_TIMESTAMP,
    price NUMERIC(12, 2) NOT NULL,
    is_confirmed BOOLEAN DEFAULT FALSE
);

-- Таблица избранного
CREATE TABLE favorites (
    id SERIAL PRIMARY KEY,
    client_id INT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    property_id INT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (client_id, property_id)
);

-- Таблица фотографий объектов недвижимости
CREATE TABLE property_photos (
    id SERIAL PRIMARY KEY,
    property_id INT NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
    photo_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

