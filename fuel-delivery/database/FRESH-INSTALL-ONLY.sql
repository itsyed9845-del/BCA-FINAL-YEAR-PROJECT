-- FRESH INSTALL ONLY. Existing users: do NOT import this file.
-- Upgrade automatically adds tables; keep your current database.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS fuel_delivery_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fuel_delivery_db;


CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    role ENUM('customer', 'delivery_agent', 'admin') NOT NULL DEFAULT 'customer',
    avatar_url VARCHAR(500) NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_phone (phone),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE fuel_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fuel_type ENUM('Petrol/Gasoline', 'Diesel', 'Premium Octane', 'CNG') NOT NULL,
    price_per_liter DECIMAL(10,2) NOT NULL,
    stock_liters DECIMAL(12,2) NOT NULL DEFAULT 0,
    tank_capacity DECIMAL(12,2) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_fuel_type (fuel_type),
    KEY idx_inventory_stock (stock_liters)
) ENGINE=InnoDB;

CREATE TABLE delivery_agent_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    vehicle_plate VARCHAR(30) NOT NULL,
    license_number VARCHAR(60) NOT NULL,
    current_lat DECIMAL(10,7) NULL,
    current_lng DECIMAL(10,7) NULL,
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
    UNIQUE KEY uq_agent_user (user_id),
    UNIQUE KEY uq_agent_vehicle_plate (vehicle_plate),
    UNIQUE KEY uq_agent_license (license_number),
    KEY idx_agent_online (is_online),
    CONSTRAINT fk_agent_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE customer_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    vehicle_name VARCHAR(80) NOT NULL,
    vehicle_number VARCHAR(30) NOT NULL,
    fuel_type ENUM('Petrol/Gasoline', 'Diesel', 'Premium Octane', 'CNG') NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_vehicle_number (vehicle_number),
    KEY idx_vehicle_customer (customer_id),
    CONSTRAINT fk_vehicle_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE delivery_addresses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    label VARCHAR(50) NOT NULL,
    full_address VARCHAR(500) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_address_customer (customer_id),
    CONSTRAINT fk_address_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_code VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    delivery_agent_id INT UNSIGNED NULL,
    fuel_type ENUM('Petrol/Gasoline', 'Diesel', 'Premium Octane', 'CNG') NOT NULL,
    quantity_liters DECIMAL(8,2) NOT NULL,
    price_per_liter DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    delivery_address VARCHAR(500) NOT NULL,
    delivery_pincode VARCHAR(10) NOT NULL,
    delivery_time_slot VARCHAR(80) NOT NULL,
    vehicle_number VARCHAR(30) NOT NULL,
    order_status ENUM('Pending', 'Accepted', 'Dispatched', 'Arrived', 'Pumping', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    payment_method ENUM('Card', 'UPI/Cash', 'Wallet') NOT NULL,
    payment_status ENUM('Paid', 'Pending', 'Refunded') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_tracking_code (tracking_code),
    KEY idx_orders_customer_created (customer_id, created_at),
    KEY idx_orders_agent_status (delivery_agent_id, order_status),
    KEY idx_orders_status_created (order_status, created_at),
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_order_agent FOREIGN KEY (delivery_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_order_quantity CHECK (quantity_liters > 0),
    CONSTRAINT chk_order_amount CHECK (total_amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE delivery_earnings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    base_payout DECIMAL(10,2) NOT NULL DEFAULT 0,
    distance_bonus DECIMAL(10,2) NOT NULL DEFAULT 0,
    tip_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_payout DECIMAL(10,2) NOT NULL,
    payout_status ENUM('Pending', 'Processed') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_earnings_order (order_id),
    KEY idx_earnings_agent_created (agent_id, created_at),
    KEY idx_earnings_payout_status (payout_status),
    CONSTRAINT fk_earning_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_earning_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reviews_ratings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_review_order (order_id),
    KEY idx_reviews_agent (agent_id, rating),
    CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_review_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE agent_live_locations (
    agent_id INT UNSIGNED PRIMARY KEY,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_meters DECIMAL(8,2) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_live_location_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    order_status ENUM('Pending','Accepted','Dispatched','Arrived','Pumping','Completed','Cancelled') NOT NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_status_history_order_time (order_id, created_at),
    CONSTRAINT fk_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_messages_order_time (order_id, created_at),
    CONSTRAINT fk_order_message_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('New', 'Read', 'Resolved') NOT NULL DEFAULT 'New',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_status_created (status, created_at),
    KEY idx_contact_email (email)
) ENGINE=InnoDB;

INSERT INTO users (id, full_name, email, password_hash, phone, role, avatar_url, status, created_at) VALUES
(1, 'Aarav Mehta', 'admin@fuel.test', '$2b$12$htpSbMadlHDxS9gXdpgY8eECZad4sWv3YKQooQIWOTNwx0Ni1SRee', '9000000001', 'admin', NULL, 'active', '2026-08-01 09:00:00'),
(2, 'Rohan Verma', 'agent1@fuel.test', '$2b$12$htpSbMadlHDxS9gXdpgY8eECZad4sWv3YKQooQIWOTNwx0Ni1SRee', '9000000002', 'delivery_agent', NULL, 'active', '2026-08-02 09:00:00'),
(3, 'Imran Khan', 'agent2@fuel.test', '$2b$12$htpSbMadlHDxS9gXdpgY8eECZad4sWv3YKQooQIWOTNwx0Ni1SRee', '9000000003', 'delivery_agent', NULL, 'active', '2026-08-03 09:00:00'),
(4, 'Neha Sharma', 'customer@fuel.test', '$2b$12$htpSbMadlHDxS9gXdpgY8eECZad4sWv3YKQooQIWOTNwx0Ni1SRee', '9000000004', 'customer', NULL, 'active', '2026-08-04 09:00:00'),
(5, 'Aditya Rao', 'aditya@fuel.test', '$2b$12$htpSbMadlHDxS9gXdpgY8eECZad4sWv3YKQooQIWOTNwx0Ni1SRee', '9000000005', 'customer', NULL, 'active', '2026-08-05 09:00:00'),
(6, 'Sara Joseph', 'sara@fuel.test', '$2b$12$htpSbMadlHDxS9gXdpgY8eECZad4sWv3YKQooQIWOTNwx0Ni1SRee', '9000000006', 'customer', NULL, 'suspended', '2026-08-06 09:00:00');

INSERT INTO fuel_inventory (id, fuel_type, price_per_liter, stock_liters, tank_capacity) VALUES
(1, 'Petrol/Gasoline', 104.90, 8200.00, 12000.00),
(2, 'Diesel', 92.85, 6700.00, 10000.00),
(3, 'Premium Octane', 118.50, 3100.00, 6000.00),
(4, 'CNG', 76.59, 850.00, 5000.00);

INSERT INTO delivery_agent_profiles (id, user_id, vehicle_plate, license_number, current_lat, current_lng, is_online, rating) VALUES
(1, 2, 'KA01SF802', 'DL-KA-2026-10802', 12.9715987, 77.5945627, 1, 4.91),
(2, 3, 'KA05SF114', 'DL-KA-2026-10114', 12.9351929, 77.6244807, 1, 4.78);

INSERT INTO customer_vehicles (id, customer_id, vehicle_name, vehicle_number, fuel_type, is_default) VALUES
(1, 4, 'Honda City Sedan', 'KA03AB1234', 'Petrol/Gasoline', 1),
(2, 4, 'Royal Enfield Classic', 'KA03MC8877', 'Petrol/Gasoline', 0),
(3, 5, 'Hyundai Creta', 'KA05CR4500', 'Diesel', 1);

INSERT INTO delivery_addresses (id, customer_id, label, full_address, pincode, is_default) VALUES
(1, 4, 'Home', '24 Residency Road, Ashok Nagar, Bengaluru', '560025', 1),
(2, 4, 'Office', '12th Floor, Orion Tech Park, Mahadevapura, Bengaluru', '560048', 0),
(3, 5, 'Home', '18 Jayanagar 4th Block, Bengaluru', '560011', 1);

INSERT INTO orders (id, tracking_code, customer_id, delivery_agent_id, fuel_type, quantity_liters, price_per_liter, delivery_fee, tax_amount, total_amount, delivery_address, delivery_pincode, delivery_time_slot, vehicle_number, order_status, payment_method, payment_status, created_at) VALUES
(1, 'FD-2026-1001', 4, 2, 'Petrol/Gasoline', 12.00, 104.90, 99.00, 68.99, 1426.79, '24 Residency Road, Ashok Nagar, Bengaluru', '560025', '09:00 AM - 11:00 AM', 'KA03AB1234', 'Completed', 'UPI/Cash', 'Paid', '2026-08-29 10:15:00'),
(2, 'FD-2026-1002', 5, 3, 'Diesel', 20.00, 92.85, 129.00, 99.27, 2085.27, '18 Jayanagar 4th Block, Bengaluru', '560011', '01:00 PM - 03:00 PM', 'KA05CR4500', 'Completed', 'Card', 'Paid', '2026-08-30 13:10:00'),
(3, 'FD-2026-1003', 4, 2, 'Premium Octane', 8.00, 118.50, 149.00, 59.24, 1156.24, '12th Floor, Orion Tech Park, Mahadevapura, Bengaluru', '560048', 'Emergency - ASAP', 'KA03MC8877', 'Dispatched', 'Wallet', 'Paid', '2026-09-03 08:30:00'),
(4, 'FD-2026-1004', 5, NULL, 'Diesel', 15.00, 92.85, 99.00, 76.42, 1568.17, 'Outer Ring Road, Bellandur, Bengaluru', '560103', '05:00 PM - 07:00 PM', 'KA05CR4500', 'Pending', 'UPI/Cash', 'Pending', '2026-09-03 11:45:00'),
(5, 'FD-2026-1005', 4, 3, 'Petrol/Gasoline', 10.00, 104.90, 99.00, 62.01, 1210.01, '24 Residency Road, Ashok Nagar, Bengaluru', '560025', '03:00 PM - 05:00 PM', 'KA03AB1234', 'Accepted', 'Card', 'Paid', '2026-09-03 12:10:00'),
(6, 'FD-2026-1006', 5, 2, 'CNG', 7.00, 76.59, 129.00, 39.96, 705.09, 'Koramangala 5th Block, Bengaluru', '560095', 'Emergency - ASAP', 'KA05CR4500', 'Cancelled', 'Wallet', 'Refunded', '2026-09-02 16:20:00');

INSERT INTO delivery_earnings (id, agent_id, order_id, base_payout, distance_bonus, tip_amount, total_payout, payout_status, created_at) VALUES
(1, 2, 1, 120.00, 45.00, 30.00, 195.00, 'Processed', '2026-08-29 11:05:00'),
(2, 3, 2, 140.00, 60.00, 50.00, 250.00, 'Processed', '2026-08-30 14:20:00');

INSERT INTO reviews_ratings (id, order_id, customer_id, agent_id, rating, review_text, created_at) VALUES
(1, 1, 4, 2, 5, 'Quick, professional and completely transparent delivery.', '2026-08-29 11:30:00'),
(2, 2, 5, 3, 4, 'Smooth service and the driver kept me updated.', '2026-08-30 15:00:00');


INSERT INTO order_status_history (order_id,order_status,changed_by,created_at) VALUES
(1,'Pending',4,'2026-08-29 10:15:00'),(1,'Accepted',1,'2026-08-29 10:19:00'),(1,'Dispatched',2,'2026-08-29 10:25:00'),(1,'Arrived',2,'2026-08-29 10:52:00'),(1,'Pumping',2,'2026-08-29 10:56:00'),(1,'Completed',2,'2026-08-29 11:05:00'),
(3,'Pending',4,'2026-09-03 08:30:00'),(3,'Accepted',1,'2026-09-03 08:35:00'),(3,'Dispatched',2,'2026-09-03 08:42:00');

INSERT INTO order_messages (order_id,sender_id,message,created_at) VALUES
(3,2,'I have started the route with your secured fuel module.','2026-09-03 08:43:00'),
(3,4,'Thank you. Please call when you reach the entrance.','2026-09-03 08:44:00');

SET FOREIGN_KEY_CHECKS = 1;
