-- ============================================================
-- Script de creacion de base de datos: bdfacturas_mariadb_local
-- Compatible con MySQL 8.0+ y MariaDB 10.4+
-- Incluye: tablas, restricciones, triggers, SPs y datos de ejemplo
-- Equivalente a bdfacturas_sqlserver.sql del proyecto C#
-- ============================================================

-- CREATE DATABASE IF NOT EXISTS bdfacturas_mariadb_local
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;

-- USE bdfacturas_mariadb_local;

-- ============================================================
-- LIMPIEZA: Eliminar objetos existentes en orden correcto
-- ============================================================

-- Triggers
DROP TRIGGER IF EXISTS trg_prodfact_insert;
DROP TRIGGER IF EXISTS trg_prodfact_update;
DROP TRIGGER IF EXISTS trg_prodfact_delete;

-- Procedimientos almacenados
DROP PROCEDURE IF EXISTS sp_insertar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_consultar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_listar_facturas_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_actualizar_factura_y_productosporfactura;
DROP PROCEDURE IF EXISTS sp_borrar_factura_y_productosporfactura;

-- Tablas dependientes primero
DROP TABLE IF EXISTS rutarol;
DROP TABLE IF EXISTS rol_usuario;
DROP TABLE IF EXISTS productosporfactura;
DROP TABLE IF EXISTS factura;
DROP TABLE IF EXISTS cliente;
DROP TABLE IF EXISTS vendedor;
DROP TABLE IF EXISTS empresa;
DROP TABLE IF EXISTS persona;
DROP TABLE IF EXISTS producto;
DROP TABLE IF EXISTS rol;
DROP TABLE IF EXISTS ruta;
DROP TABLE IF EXISTS usuario;

-- ============================================================
-- TABLAS INDEPENDIENTES (sin foreign keys)
-- ============================================================

CREATE TABLE empresa (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    CONSTRAINT pk_empresa PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE persona (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    CONSTRAINT pk_persona PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE producto (
    codigo VARCHAR(10) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    stock INT NOT NULL,
    valorunitario DECIMAL(18,2) NOT NULL,
    CONSTRAINT pk_producto PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rol (
    id INT AUTO_INCREMENT NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    CONSTRAINT pk_rol PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ruta (
    id INT AUTO_INCREMENT NOT NULL,
    ruta VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NOT NULL,
    CONSTRAINT pk_ruta PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuario (
    email VARCHAR(100) NOT NULL,
    contrasena VARCHAR(200) NOT NULL,
    CONSTRAINT pk_usuario PRIMARY KEY (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLAS DEPENDIENTES (con foreign keys)
-- ============================================================

CREATE TABLE cliente (
    id INT AUTO_INCREMENT NOT NULL,
    credito DECIMAL(18,2) NOT NULL DEFAULT 0,
    fkcodpersona VARCHAR(10) NOT NULL,
    fkcodempresa VARCHAR(10),
    CONSTRAINT pk_cliente PRIMARY KEY (id),
    CONSTRAINT fk_cliente_persona FOREIGN KEY (fkcodpersona) REFERENCES persona(codigo),
    CONSTRAINT fk_cliente_empresa FOREIGN KEY (fkcodempresa) REFERENCES empresa(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendedor (
    id INT AUTO_INCREMENT NOT NULL,
    carnet INT NOT NULL,
    direccion VARCHAR(100) NOT NULL,
    fkcodpersona VARCHAR(10) NOT NULL,
    CONSTRAINT pk_vendedor PRIMARY KEY (id),
    CONSTRAINT fk_vendedor_persona FOREIGN KEY (fkcodpersona) REFERENCES persona(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE factura (
    numero INT AUTO_INCREMENT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    fkidcliente INT NOT NULL,
    fkidvendedor INT NOT NULL,
    CONSTRAINT pk_factura PRIMARY KEY (numero),
    CONSTRAINT fk_factura_cliente FOREIGN KEY (fkidcliente) REFERENCES cliente(id),
    CONSTRAINT fk_factura_vendedor FOREIGN KEY (fkidvendedor) REFERENCES vendedor(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE productosporfactura (
    fknumfactura INT NOT NULL,
    fkcodproducto VARCHAR(10) NOT NULL,
    cantidad INT NOT NULL,
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    CONSTRAINT pk_productosporfactura PRIMARY KEY (fknumfactura, fkcodproducto),
    CONSTRAINT fk_prodfact_factura FOREIGN KEY (fknumfactura) REFERENCES factura(numero) ON DELETE CASCADE,
    CONSTRAINT fk_prodfact_producto FOREIGN KEY (fkcodproducto) REFERENCES producto(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rol_usuario (
    fkemail VARCHAR(100) NOT NULL,
    fkidrol INT NOT NULL,
    CONSTRAINT pk_rol_usuario PRIMARY KEY (fkemail, fkidrol),
    CONSTRAINT fk_rolusuario_usuario FOREIGN KEY (fkemail) REFERENCES usuario(email),
    CONSTRAINT fk_rolusuario_rol FOREIGN KEY (fkidrol) REFERENCES rol(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rutarol (
    fkidruta INT NOT NULL,
    rol VARCHAR(50) NOT NULL,
    CONSTRAINT pk_rutarol PRIMARY KEY (fkidruta, rol),
    CONSTRAINT fk_rutarol_ruta FOREIGN KEY (fkidruta) REFERENCES ruta(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TRIGGERS: Actualizar totales de factura y stock de producto
-- En MySQL/MariaDB cada trigger es FOR EACH ROW (fila por fila)
-- a diferencia de SQL Server que usa tablas virtuales INSERTED/DELETED.
--
-- Equivalencia:
--   SQL Server: AFTER INSERT con tabla virtual INSERTED
--   MySQL:      BEFORE INSERT con NEW.columna (fila actual)
-- ============================================================

DELIMITER //

-- TRIGGER INSERT: Calcular subtotal, descontar stock, recalcular total factura
CREATE TRIGGER trg_prodfact_insert
BEFORE INSERT ON productosporfactura
FOR EACH ROW
BEGIN
    DECLARE v_precio DECIMAL(18,2);
    DECLARE v_stock_actual INT;
    DECLARE v_msg VARCHAR(500);

    -- Obtener precio y stock del producto
    SELECT valorunitario, stock INTO v_precio, v_stock_actual
    FROM producto WHERE codigo = NEW.fkcodproducto;

    -- Validar stock suficiente
    IF v_stock_actual < NEW.cantidad THEN
        SET v_msg = CONCAT('Stock insuficiente para producto ', NEW.fkcodproducto,
            '. Stock disponible: ', v_stock_actual, ', cantidad solicitada: ', NEW.cantidad);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Calcular subtotal = cantidad * valorunitario
    SET NEW.subtotal = NEW.cantidad * v_precio;

    -- Descontar stock del producto
    UPDATE producto
    SET stock = stock - NEW.cantidad
    WHERE codigo = NEW.fkcodproducto;
END//

-- TRIGGER AFTER INSERT: Recalcular total de factura (debe ser AFTER porque subtotal ya esta calculado)
CREATE TRIGGER trg_prodfact_after_insert
AFTER INSERT ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (
        SELECT COALESCE(SUM(subtotal), 0)
        FROM productosporfactura
        WHERE fknumfactura = NEW.fknumfactura
    )
    WHERE numero = NEW.fknumfactura;
END//

-- TRIGGER UPDATE: Ajustar stock, recalcular subtotal y total factura
CREATE TRIGGER trg_prodfact_update
BEFORE UPDATE ON productosporfactura
FOR EACH ROW
BEGIN
    DECLARE v_precio DECIMAL(18,2);
    DECLARE v_stock_actual INT;
    DECLARE v_msg VARCHAR(500);

    -- Obtener precio y stock del producto
    SELECT valorunitario, stock INTO v_precio, v_stock_actual
    FROM producto WHERE codigo = NEW.fkcodproducto;

    -- Validar stock suficiente (devolver old.cantidad, descontar new.cantidad)
    IF (v_stock_actual + OLD.cantidad) < NEW.cantidad THEN
        SET v_msg = CONCAT('Stock insuficiente para producto ', NEW.fkcodproducto,
            '. Stock disponible: ', (v_stock_actual + OLD.cantidad),
            ', cantidad solicitada: ', NEW.cantidad);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;

    -- Recalcular subtotal
    SET NEW.subtotal = NEW.cantidad * v_precio;

    -- Ajustar stock: devolver old.cantidad y descontar new.cantidad
    UPDATE producto
    SET stock = stock + OLD.cantidad - NEW.cantidad
    WHERE codigo = NEW.fkcodproducto;
END//

-- TRIGGER AFTER UPDATE: Recalcular total factura
CREATE TRIGGER trg_prodfact_after_update
AFTER UPDATE ON productosporfactura
FOR EACH ROW
BEGIN
    UPDATE factura
    SET total = (
        SELECT COALESCE(SUM(subtotal), 0)
        FROM productosporfactura
        WHERE fknumfactura = NEW.fknumfactura
    )
    WHERE numero = NEW.fknumfactura;
END//

-- TRIGGER DELETE: Restaurar stock y recalcular total factura
CREATE TRIGGER trg_prodfact_delete
AFTER DELETE ON productosporfactura
FOR EACH ROW
BEGIN
    -- Restaurar stock del producto
    UPDATE producto
    SET stock = stock + OLD.cantidad
    WHERE codigo = OLD.fkcodproducto;

    -- Recalcular total de la factura
    UPDATE factura
    SET total = (
        SELECT COALESCE(SUM(subtotal), 0)
        FROM productosporfactura
        WHERE fknumfactura = OLD.fknumfactura
    )
    WHERE numero = OLD.fknumfactura;
END//

DELIMITER ;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS
-- Equivalentes a los SPs de SQL Server.
-- Diferencias clave:
--   SQL Server: OPENJSON, FOR JSON PATH, OUTPUT params
--   MySQL:      JSON_TABLE (8.0+), JSON_OBJECT/JSON_ARRAY, OUT params
-- ============================================================

DELIMITER //

-- ------------------------------------------------------------
-- 1. SP INSERTAR FACTURA Y PRODUCTOSPORFACTURA
-- Recibe: id cliente, id vendedor, y un JSON array de productos
-- Retorna: JSON con la factura creada y sus productos
-- Ejemplo via API:
--   POST /api/procedimientos/ejecutarsp
--   { "nombreSP": "sp_insertar_factura_y_productosporfactura",
--     "p_fkidcliente": 1, "p_fkidvendedor": 1,
--     "p_productos": "[{\"codigo\":\"PR001\",\"cantidad\":2}]",
--     "p_resultado": null }
-- ------------------------------------------------------------
CREATE PROCEDURE sp_insertar_factura_y_productosporfactura(
    IN p_fkidcliente INT,
    IN p_fkidvendedor INT,
    IN p_productos TEXT,
    IN p_minimo_detalle INT,
    OUT p_resultado TEXT
)
BEGIN
    DECLARE v_numero INT;
    DECLARE v_codigo VARCHAR(10);
    DECLARE v_cantidad INT;
    DECLARE v_minimo INT;
    DECLARE v_count INT;
    DECLARE v_i INT DEFAULT 0;

    -- Validar minimo de productos
    SET v_minimo = COALESCE(NULLIF(p_minimo_detalle, 0), 1);

    IF p_productos IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La factura requiere minimo 1 producto(s).';
    END IF;

    SET v_count = JSON_LENGTH(p_productos);
    IF v_count < v_minimo THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La factura no tiene suficientes productos.';
    END IF;

    -- Crear factura con total 0 (el trigger actualiza el total)
    INSERT INTO factura (fkidcliente, fkidvendedor, total)
    VALUES (p_fkidcliente, p_fkidvendedor, 0);

    SET v_numero = LAST_INSERT_ID();

    -- Recorrer cada producto del JSON e insertar detalle
    WHILE v_i < v_count DO
        SET v_codigo = JSON_UNQUOTE(JSON_EXTRACT(p_productos, CONCAT('$[', v_i, '].codigo')));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_i, '].cantidad'));

        INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
        VALUES (v_numero, v_codigo, v_cantidad, 0);

        SET v_i = v_i + 1;
    END WHILE;

    -- Retornar resultado como JSON
    SET p_resultado = (
        SELECT JSON_OBJECT(
            'factura', JSON_OBJECT(
                'numero', f.numero,
                'fecha', f.fecha,
                'total', f.total,
                'fkidcliente', f.fkidcliente,
                'fkidvendedor', f.fkidvendedor
            ),
            'productos', (
                SELECT CONCAT('[', GROUP_CONCAT(
                    JSON_OBJECT(
                        'codigo_producto', pf.fkcodproducto,
                        'nombre_producto', pr.nombre,
                        'cantidad', pf.cantidad,
                        'valorunitario', pr.valorunitario,
                        'subtotal', pf.subtotal
                    )
                ), ']')
                FROM productosporfactura pf
                JOIN producto pr ON pr.codigo = pf.fkcodproducto
                WHERE pf.fknumfactura = v_numero
            )
        )
        FROM factura f WHERE f.numero = v_numero
    );
END//

-- ------------------------------------------------------------
-- 2. SP CONSULTAR FACTURA Y PRODUCTOSPORFACTURA
-- ------------------------------------------------------------
CREATE PROCEDURE sp_consultar_factura_y_productosporfactura(
    IN p_numero INT,
    OUT p_resultado TEXT
)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Factura no existe';
    END IF;

    SET p_resultado = (
        SELECT JSON_OBJECT(
            'factura', JSON_OBJECT(
                'numero', f.numero,
                'fecha', f.fecha,
                'total', f.total,
                'fkidcliente', f.fkidcliente,
                'nombre_cliente', pc.nombre,
                'fkidvendedor', f.fkidvendedor,
                'nombre_vendedor', pv.nombre
            ),
            'productos', (
                SELECT CONCAT('[', GROUP_CONCAT(
                    JSON_OBJECT(
                        'codigo_producto', pf.fkcodproducto,
                        'nombre_producto', pr.nombre,
                        'cantidad', pf.cantidad,
                        'valorunitario', pr.valorunitario,
                        'subtotal', pf.subtotal
                    )
                ), ']')
                FROM productosporfactura pf
                JOIN producto pr ON pr.codigo = pf.fkcodproducto
                WHERE pf.fknumfactura = p_numero
            )
        )
        FROM factura f
        JOIN cliente c ON c.id = f.fkidcliente
        JOIN persona pc ON pc.codigo = c.fkcodpersona
        JOIN vendedor v ON v.id = f.fkidvendedor
        JOIN persona pv ON pv.codigo = v.fkcodpersona
        WHERE f.numero = p_numero
    );
END//

-- ------------------------------------------------------------
-- 3. SP LISTAR FACTURAS Y PRODUCTOSPORFACTURA
-- ------------------------------------------------------------
CREATE PROCEDURE sp_listar_facturas_y_productosporfactura(
    OUT p_resultado TEXT
)
BEGIN
    SET p_resultado = (
        SELECT CONCAT('[', GROUP_CONCAT(
            JSON_OBJECT(
                'numero', f.numero,
                'fecha', f.fecha,
                'total', f.total,
                'fkidcliente', f.fkidcliente,
                'nombre_cliente', pc.nombre,
                'fkidvendedor', f.fkidvendedor,
                'nombre_vendedor', pv.nombre,
                'productos', (
                    SELECT CONCAT('[', GROUP_CONCAT(
                        JSON_OBJECT(
                            'codigo_producto', pf.fkcodproducto,
                            'nombre_producto', pr.nombre,
                            'cantidad', pf.cantidad,
                            'valorunitario', pr.valorunitario,
                            'subtotal', pf.subtotal
                        )
                    ), ']')
                    FROM productosporfactura pf
                    JOIN producto pr ON pr.codigo = pf.fkcodproducto
                    WHERE pf.fknumfactura = f.numero
                )
            )
        ), ']')
        FROM factura f
        JOIN cliente c ON c.id = f.fkidcliente
        JOIN persona pc ON pc.codigo = c.fkcodpersona
        JOIN vendedor v ON v.id = f.fkidvendedor
        JOIN persona pv ON pv.codigo = v.fkcodpersona
    );
END//

-- ------------------------------------------------------------
-- 4. SP ACTUALIZAR FACTURA Y PRODUCTOSPORFACTURA
-- Reemplaza los productos de una factura existente.
-- El trigger restaura stock (DELETE), descuenta stock (INSERT)
-- y recalcula subtotales/total.
-- ------------------------------------------------------------
CREATE PROCEDURE sp_actualizar_factura_y_productosporfactura(
    IN p_numero INT,
    IN p_fkidcliente INT,
    IN p_fkidvendedor INT,
    IN p_productos TEXT,
    IN p_minimo_detalle INT,
    OUT p_resultado TEXT
)
BEGIN
    DECLARE v_codigo VARCHAR(10);
    DECLARE v_cantidad INT;
    DECLARE v_minimo INT;
    DECLARE v_count INT;
    DECLARE v_i INT DEFAULT 0;

    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Factura no existe';
    END IF;

    SET v_minimo = COALESCE(NULLIF(p_minimo_detalle, 0), 1);

    IF p_productos IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La factura requiere minimo 1 producto(s).';
    END IF;

    SET v_count = JSON_LENGTH(p_productos);
    IF v_count < v_minimo THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La factura no tiene suficientes productos.';
    END IF;

    -- Eliminar detalle anterior (el trigger restaura stock y recalcula total)
    DELETE FROM productosporfactura WHERE fknumfactura = p_numero;

    -- Insertar nuevos productos (el trigger calcula subtotal, descuenta stock, actualiza total)
    WHILE v_i < v_count DO
        SET v_codigo = JSON_UNQUOTE(JSON_EXTRACT(p_productos, CONCAT('$[', v_i, '].codigo')));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_i, '].cantidad'));

        INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal)
        VALUES (p_numero, v_codigo, v_cantidad, 0);

        SET v_i = v_i + 1;
    END WHILE;

    -- Actualizar cliente y vendedor de la factura
    UPDATE factura
    SET fkidcliente = p_fkidcliente,
        fkidvendedor = p_fkidvendedor
    WHERE numero = p_numero;

    -- Retornar resultado como JSON
    SET p_resultado = (
        SELECT JSON_OBJECT(
            'factura', JSON_OBJECT(
                'numero', f.numero,
                'fecha', f.fecha,
                'total', f.total,
                'fkidcliente', f.fkidcliente,
                'fkidvendedor', f.fkidvendedor
            ),
            'productos', (
                SELECT CONCAT('[', GROUP_CONCAT(
                    JSON_OBJECT(
                        'codigo_producto', pf.fkcodproducto,
                        'nombre_producto', pr.nombre,
                        'cantidad', pf.cantidad,
                        'valorunitario', pr.valorunitario,
                        'subtotal', pf.subtotal
                    )
                ), ']')
                FROM productosporfactura pf
                JOIN producto pr ON pr.codigo = pf.fkcodproducto
                WHERE pf.fknumfactura = p_numero
            )
        )
        FROM factura f WHERE f.numero = p_numero
    );
END//

-- ------------------------------------------------------------
-- 5. SP BORRAR FACTURA Y PRODUCTOSPORFACTURA
-- NOTA: En MariaDB 10.4, ON DELETE CASCADE NO dispara triggers.
-- Por eso primero borramos el detalle manualmente (activa trigger
-- que restaura stock), y luego borramos la factura.
-- En MySQL 8.0+ y MariaDB 10.5+ CASCADE si dispara triggers.
-- ------------------------------------------------------------
CREATE PROCEDURE sp_borrar_factura_y_productosporfactura(
    IN p_numero INT,
    OUT p_resultado TEXT
)
BEGIN
    DECLARE v_total DECIMAL(18,2);
    DECLARE v_cantidad_productos INT;

    IF NOT EXISTS (SELECT 1 FROM factura WHERE numero = p_numero) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Factura no existe';
    END IF;

    -- Guardar info antes de borrar
    SELECT COUNT(*) INTO v_cantidad_productos
    FROM productosporfactura WHERE fknumfactura = p_numero;

    SELECT total INTO v_total FROM factura WHERE numero = p_numero;

    -- Borrar detalle PRIMERO (dispara trigger que restaura stock)
    -- No usar CASCADE porque MariaDB 10.4 no dispara triggers con CASCADE
    DELETE FROM productosporfactura WHERE fknumfactura = p_numero;

    -- Borrar factura (ya sin detalle)
    DELETE FROM factura WHERE numero = p_numero;

    SET p_resultado = JSON_OBJECT(
        'mensaje', 'Factura eliminada exitosamente',
        'numero_eliminado', p_numero,
        'total_eliminado', v_total,
        'productos_eliminados', v_cantidad_productos
    );
END//

DELIMITER ;

-- ============================================================
-- DATOS (mismos que SQL Server)
-- ============================================================

-- Empresas
INSERT INTO empresa (codigo, nombre) VALUES
('E001', 'Comercial Los Andes S.A.'),
('E002', 'Distribuciones El Centro S.A.'),
('E999', 'Empresa Test');

-- Personas
INSERT INTO persona (codigo, nombre, email, telefono) VALUES
('P001', 'Ana Torres', 'ana.torres@correo.com', '3011111111'),
('P002', 'Carlos Pérez', 'carlos.perez@correo.com', '3022222222'),
('P003', 'María Gómez', 'maria.gomez@correo.com', '3033333333'),
('P004', 'Juan Díaz', 'juan.diaz@correo.com', '3044444444'),
('P005', 'Laura Rojas', 'laura.rojas@correo.com', '3055555555'),
('P006', 'Pedro Castillo', 'pedro.castillo@correo.com', '3066666666');

-- Productos
INSERT INTO producto (codigo, nombre, stock, valorunitario) VALUES
('PR001', 'Laptop Lenovo IdeaPad', 17, 2500000),
('PR002', 'Monitor Samsung 24"', 27, 800000),
('PR003', 'Teclado Logitech K380', 42, 150000),
('PR004', 'Mouse HP', 55, 90000),
('PR005', 'Impresora Epson EcoTank1', 14, 1100000),
('PR006', 'Auriculares Sony WH-CH510', 23, 240000),
('PR007', 'Tablet Samsung Tab A9', 15, 950000),
('PR008', 'Disco Duro Seagate 1TB', 32, 280000);

-- Roles
INSERT INTO rol (id, nombre) VALUES
(1, 'Administrador'),
(2, 'Vendedor'),
(3, 'Cajero'),
(4, 'Contador'),
(5, 'Cliente');

-- Rutas
INSERT INTO ruta (ruta, descripcion) VALUES
('/home', 'Página principal - Dashboard'),
('/usuarios', 'Gestión de usuarios'),
('/facturas', 'Gestión de facturas'),
('/clientes', 'Gestión de clientes'),
('/vendedores', 'Gestión de vendedores'),
('/personas', 'Gestión de personas'),
('/empresas', 'Gestión de empresas'),
('/productos', 'Gestión de productos'),
('/roles', 'Gestión de roles'),
('/permisos', 'Gestión de permisos (asignación rol-ruta)'),
('/permisos/crear', 'Crear permiso (POST)'),
('/permisos/eliminar', 'Eliminar permiso (POST)'),
('/rutas', 'Gestión de rutas del sistema'),
('/rutas/crear', 'Crear ruta (POST)'),
('/rutas/eliminar', 'Eliminar ruta (POST)');

-- Usuarios (contrasenas hasheadas con BCrypt)
INSERT INTO usuario (email, contrasena) VALUES
('admin@correo.com', '$2a$12$3UgI.Eof.FhzsYUWESI9n.qFaqkV2JPhvW3L/1GTKowNJnGaD8F.G'),
('vendedor1@correo.com', '$2a$12$Dgog4VaHqMzhliPVJy1BcOMd6.izEGNeRDtZ.O7SPmBLc6UVthVTG'),
('jefe@correo.com', '$2y$10$7Fl0IeWz3at9XTsh5hrrjud7bl8VggywpnEdVMpK1A5Sv5MiqYMtC'),
('cliente1@correo.com', '$2y$10$kKcjbv3WJP9b.a46G2A5nOV1z1O52yE75xzozdwHr6A0lRSkMf7VC'),
('test_encript@correo.com', '$2a$11$Ci0J2yBltDgQHfjadgkl0OtbcF5pUf97vTq/4Xr0KEU/86l8ybjBe'),
('nuevo@correo.com', '$2a$11$cmtGBxllwc7MCzpnKVSWuumiOgCaG6PaKWcN1z9N0bjjnkobbFDzO');

-- Clientes
INSERT INTO cliente (id, credito, fkcodpersona, fkcodempresa) VALUES
(1, 520000, 'P001', 'E001'),
(2, 250000, 'P003', 'E002'),
(3, 400000, 'P005', 'E001'),
(5, 700000, 'P006', 'E001');

-- Vendedores
INSERT INTO vendedor (id, carnet, direccion, fkcodpersona) VALUES
(1, 1001, 'Calle 10 #5-33', 'P002'),
(2, 1002, 'Carrera 15 #7-20', 'P004'),
(3, 1003, 'Avenida 30 #18-09', 'P006');

-- Facturas y productos (con triggers activos, el trigger calcula subtotales y descuenta stock)
-- Primero insertamos facturas con total 0
INSERT INTO factura (numero, fecha, total, fkidcliente, fkidvendedor) VALUES
(1, '2025-10-15 00:00:00', 0, 1, 1),
(2, '2025-10-16 00:00:00', 0, 2, 2),
(3, '2025-10-17 00:00:00', 0, 3, 3);

-- Productos por factura (los triggers calculan subtotal, descuentan stock y actualizan total)
INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal) VALUES
(1, 'PR001', 2, 0),
(1, 'PR002', 1, 0),
(1, 'PR003', 3, 0);

INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal) VALUES
(2, 'PR004', 5, 0),
(2, 'PR005', 1, 0),
(2, 'PR006', 2, 0);

INSERT INTO productosporfactura (fknumfactura, fkcodproducto, cantidad, subtotal) VALUES
(3, 'PR007', 1, 0),
(3, 'PR008', 3, 0);

-- Roles por usuario
INSERT INTO rol_usuario (fkemail, fkidrol) VALUES
('admin@correo.com', 1),
('vendedor1@correo.com', 2),
('vendedor1@correo.com', 3),
('jefe@correo.com', 1),
('jefe@correo.com', 3),
('jefe@correo.com', 4),
('cliente1@correo.com', 5),
('test_encript@correo.com', 1),
('nuevo@correo.com', 1),
('nuevo@correo.com', 2),
('nuevo@correo.com', 3);

-- Rutas por rol (fkidruta referencia ruta.id segun orden de insercion)
-- IDs: 1=/home, 2=/usuarios, 3=/facturas, 4=/clientes, 5=/vendedores,
--       6=/personas, 7=/empresas, 8=/productos, 9=/roles, 10=/permisos,
--       11=/permisos/crear, 12=/permisos/eliminar, 13=/rutas, 14=/rutas/crear, 15=/rutas/eliminar
INSERT INTO rutarol (fkidruta, rol) VALUES
(1, 'Administrador'),
(2, 'Administrador'),
(3, 'Administrador'),
(4, 'Administrador'),
(5, 'Administrador'),
(6, 'Administrador'),
(7, 'Administrador'),
(8, 'Administrador'),
(9, 'Administrador'),
(10, 'Administrador'),
(11, 'Administrador'),
(12, 'Administrador'),
(13, 'Administrador'),
(14, 'Administrador'),
(15, 'Administrador'),
(1, 'Vendedor'),
(3, 'Vendedor'),
(4, 'Vendedor'),
(1, 'Cajero'),
(3, 'Cajero'),
(1, 'Contador'),
(4, 'Contador'),
(8, 'Contador'),
(1, 'Cliente'),
(8, 'Cliente');
