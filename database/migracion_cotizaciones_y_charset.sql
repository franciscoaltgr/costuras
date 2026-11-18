-- ==============================================================================
-- Migración: Soporte UTF-8 Profesional y Módulo de Cotizaciones Grill Guard
-- Archivo: database/migracion_cotizaciones_y_charset.sql
-- Motor: MySQL 8.x / MariaDB
-- ==============================================================================

SET NAMES 'utf8mb4';
SET CHARACTER SET utf8mb4;

USE `taller_costuras`;

-- 1. Asegurar charset y collation a nivel de base de datos
ALTER DATABASE `taller_costuras` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 2. Convertir tablas existentes a utf8mb4_unicode_ci para soporte completo de tildes y caracteres especiales
ALTER TABLE `clientes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `pedidos` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `medidas` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `pagos` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Corregir registros existentes que tenían caracteres corruptos (?) por importación previa
UPDATE `clientes` SET `nombre` = 'Mariana Gómez' WHERE `id` = 1 AND `nombre` LIKE '%Mariana%';
UPDATE `clientes` SET `nombre` = 'Sofía Valenzuela' WHERE `id` = 3 AND `nombre` LIKE '%Sof%';
UPDATE `clientes` SET `nombre` = 'Francisco Díaz' WHERE `id` = 5 AND `nombre` LIKE '%Francisco%';

UPDATE `pedidos` SET `descripcion_prenda` = 'Vestido de novia corte princesa con encaje francés' WHERE `id` = 1;
UPDATE `pedidos` SET `descripcion_prenda` = 'Birretes de graduación' WHERE `id` = 4;
UPDATE `pedidos` SET `descripcion_prenda` = 'Estolas graduación' WHERE `id` = 5;

UPDATE `medidas` SET `observaciones` = 'Talla justa en torso, escote ilusión y cola desmontable de 1.5 metros.' WHERE `pedido_id` = 1;
UPDATE `medidas` SET `observaciones` = 'Reducción de 2 cm en cintura y subir bastilla 4 cm para tacón de 7 cm.' WHERE `pedido_id` = 3;
UPDATE `medidas` SET `observaciones` = 'Poner bordado o imagen de graduación con serigrafiado.' WHERE `pedido_id` = 5;

-- 4. Creación de la tabla `cotizaciones`
CREATE TABLE IF NOT EXISTS `cotizaciones` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `numero` VARCHAR(50) NOT NULL UNIQUE,
    `cliente_id` INT NULL,
    `cliente_nombre` VARCHAR(150) NOT NULL,
    `cliente_direccion` VARCHAR(255) NULL DEFAULT '',
    `cliente_telefono` VARCHAR(50) NULL DEFAULT '',
    `cliente_email` VARCHAR(150) NULL DEFAULT '',
    `fecha` DATE NOT NULL,
    `subtotal` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `iva` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `total` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `abono` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `saldo` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `porcentaje_abono` INT NOT NULL DEFAULT 50,
    `condiciones` TEXT NULL,
    `estado` ENUM('Borrador', 'Enviada', 'Aprobada', 'Rechazada', 'Convertida en Pedido') NOT NULL DEFAULT 'Enviada',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cotizaciones_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    INDEX `idx_cotizaciones_numero` (`numero`),
    INDEX `idx_cotizaciones_fecha` (`fecha`),
    INDEX `idx_cotizaciones_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Creación de la tabla `cotizacion_items` (Detalle de productos / confección)
CREATE TABLE IF NOT EXISTS `cotizacion_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cotizacion_id` INT NOT NULL,
    `producto` VARCHAR(255) NOT NULL,
    `color` VARCHAR(100) NULL DEFAULT '',
    `cantidad` INT NOT NULL DEFAULT 1,
    `precio_unitario` DECIMAL(12,0) NOT NULL DEFAULT 0,
    `total_linea` DECIMAL(12,0) NOT NULL DEFAULT 0,
    CONSTRAINT `fk_cotizacion_items_cotizacion`
        FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    INDEX `idx_cotizacion_items_cotizacion` (`cotizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Insertar cotización semilla basada en la imagen adjunta del usuario
INSERT INTO `cotizaciones` (
    `id`, `numero`, `cliente_id`, `cliente_nombre`, `cliente_direccion`, 
    `cliente_telefono`, `cliente_email`, `fecha`, `subtotal`, `iva`, 
    `total`, `abono`, `saldo`, `porcentaje_abono`, `condiciones`, `estado`
) VALUES (
    1, 
    '21126', 
    NULL, 
    'FACTOR PRINT', 
    '', 
    '56920838625', 
    '', 
    '2026-11-11', 
    890000, 
    169100, 
    1059100, 
    529550, 
    529550, 
    50, 
    'TALLER SOLICITA 50% CON LA CONFIRMACION Y 50% CON LA ENTREGA', 
    'Enviada'
) ON DUPLICATE KEY UPDATE 
    `cliente_nombre` = VALUES(`cliente_nombre`),
    `subtotal` = VALUES(`subtotal`),
    `iva` = VALUES(`iva`),
    `total` = VALUES(`total`),
    `abono` = VALUES(`abono`),
    `saldo` = VALUES(`saldo`);

-- 7. Insertar el ítem de la cotización 21126 (BOLSAS 38X43, NEGRA, 1000, $890 c/u)
DELETE FROM `cotizacion_items` WHERE `cotizacion_id` = 1;
INSERT INTO `cotizacion_items` (`cotizacion_id`, `producto`, `color`, `cantidad`, `precio_unitario`, `total_linea`) VALUES
(1, 'BOLSAS 38X43', 'NEGRA', 1000, 890, 890000);
