-- ==============================================================================
-- Sistema de Gestión para Taller de Costuras
-- Archivo: database/schema.sql
-- Motor: MySQL 8.x / MariaDB (InnoDB, UTF-8 con soporte en español)
-- Moneda: Pesos Chilenos (CLP - sin decimales)
-- ==============================================================================

SET NAMES 'utf8mb4';
SET CHARACTER SET utf8mb4;

-- Creación de la base de datos con juego de caracteres UTF-8 en español
CREATE DATABASE IF NOT EXISTS `taller_costuras` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_spanish_ci;

USE `taller_costuras`;

SET NAMES 'utf8mb4';

-- Desactivar temporalmente restricciones de llaves foráneas para reinicio limpio
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `pagos`;
DROP TABLE IF EXISTS `medidas`;
DROP TABLE IF EXISTS `pedidos`;
DROP TABLE IF EXISTS `clientes`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. Tabla: clientes
-- Entidad base para identificar al solicitante del trabajo
-- ------------------------------------------------------------------------------
CREATE TABLE `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `telefono` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_clientes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------
-- 2. Tabla: pedidos
-- Registro principal del encargo de costura o confección.
-- Los costos se definen en Pesos Chilenos (CLP) enteros.
-- ------------------------------------------------------------------------------
CREATE TABLE `pedidos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT NOT NULL,
    `descripcion_prenda` VARCHAR(255) NOT NULL,
    `estado` ENUM('Pendiente', 'En Proceso', 'Prueba Lista', 'Entregado', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
    `fecha_entrega` DATE NOT NULL,
    `costo_total` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `abono` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pedidos_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
    INDEX `idx_pedidos_cliente` (`cliente_id`),
    INDEX `idx_pedidos_estado` (`estado`),
    INDEX `idx_pedidos_fecha_entrega` (`fecha_entrega`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------
-- 3. Tabla: medidas
-- Registra las especificaciones anatómicas en centímetros (soporta decimales, ej: 92.5 cm).
-- ------------------------------------------------------------------------------
CREATE TABLE `medidas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `busto` DECIMAL(6,2) NULL DEFAULT NULL,
    `cintura` DECIMAL(6,2) NULL DEFAULT NULL,
    `cadera` DECIMAL(6,2) NULL DEFAULT NULL,
    `largo` DECIMAL(6,2) NULL DEFAULT NULL,
    `observaciones` TEXT NULL,
    CONSTRAINT `fk_medidas_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    INDEX `idx_medidas_pedido` (`pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------
-- 4. Tabla: pagos
-- Bitácora de transacciones monetarias (en Pesos Chilenos - CLP).
-- ------------------------------------------------------------------------------
CREATE TABLE `pagos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `monto` DECIMAL(12,0) NOT NULL,
    `fecha_pago` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pagos_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE RESTRICT 
        ON UPDATE CASCADE,
    INDEX `idx_pagos_pedido` (`pedido_id`),
    INDEX `idx_pagos_fecha` (`fecha_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ------------------------------------------------------------------------------
-- Datos Iniciales / Semilla (Seeders) con caracteres y acentos en español
-- ------------------------------------------------------------------------------
INSERT INTO `clientes` (`id`, `nombre`, `telefono`, `email`) VALUES
(1, 'Mariana Gómez', '+56 9 8765 4321', 'mariana.gomez@email.com'),
(2, 'Carlos Mendoza', '+56 9 1234 5678', 'carlos.mendoza@email.com'),
(3, 'Sofía Valenzuela', '+56 9 4567 8901', 'sofia.valenzuela@email.com');

-- Pedido 1: Mariana Gómez - Vestido de Novia ($450.000 CLP, abono inicial $200.000 CLP)
INSERT INTO `pedidos` (`id`, `cliente_id`, `descripcion_prenda`, `estado`, `fecha_entrega`, `costo_total`, `abono`, `created_at`) VALUES
(1, 1, 'Vestido de novia corte princesa con encaje francés', 'En Proceso', DATE_ADD(CURDATE(), INTERVAL 20 DAY), 450000, 200000, NOW());

INSERT INTO `medidas` (`pedido_id`, `busto`, `cintura`, `cadera`, `largo`, `observaciones`) VALUES
(1, 92.50, 68.00, 96.00, 145.00, 'Talla justa en torso, escote ilusión y cola desmontable de 1.5 metros.');

INSERT INTO `pagos` (`pedido_id`, `monto`, `fecha_pago`) VALUES
(1, 200000, NOW());

-- Pedido 2: Carlos Mendoza - Traje Dos Piezas ($180.000 CLP, abono inicial $50.000 CLP)
INSERT INTO `pedidos` (`id`, `cliente_id`, `descripcion_prenda`, `estado`, `fecha_entrega`, `costo_total`, `abono`, `created_at`) VALUES
(2, 2, 'Traje sastre dos piezas lana fría azul marino', 'Pendiente', DATE_ADD(CURDATE(), INTERVAL 12 DAY), 180000, 50000, NOW());

INSERT INTO `medidas` (`pedido_id`, `busto`, `cintura`, `cadera`, `largo`, `observaciones`) VALUES
(2, 102.00, 86.00, 100.00, 104.00, 'Saco corte slim fit con forro interior personalizado. Pantalón con basta italiana.');

INSERT INTO `pagos` (`pedido_id`, `monto`, `fecha_pago`) VALUES
(2, 50000, NOW());

-- Pedido 3: Sofía Valenzuela - Ajuste de Vestido de Gala ($45.000 CLP pagado completo)
INSERT INTO `pedidos` (`id`, `cliente_id`, `descripcion_prenda`, `estado`, `fecha_entrega`, `costo_total`, `abono`, `created_at`) VALUES
(3, 3, 'Ajuste de bastas y entalle de vestido de gala', 'Prueba Lista', DATE_ADD(CURDATE(), INTERVAL 4 DAY), 45000, 45000, NOW());

INSERT INTO `medidas` (`pedido_id`, `busto`, `cintura`, `cadera`, `largo`, `observaciones`) VALUES
(3, 88.00, 64.00, 92.00, 138.00, 'Reducción de 2 cm en cintura y subir bastilla 4 cm para tacón de 7 cm.');

INSERT INTO `pagos` (`pedido_id`, `monto`, `fecha_pago`) VALUES
(3, 20000, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 25000, NOW());
