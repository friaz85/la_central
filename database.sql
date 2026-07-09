-- Base de datos para Bot WhatsApp Gatorade G15K

CREATE TABLE IF NOT EXISTS tblUsuario (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    Celular VARCHAR(20) UNIQUE NOT NULL,
    Nombre VARCHAR(100) DEFAULT NULL,
    Email VARCHAR(150) DEFAULT NULL,
    Estado VARCHAR(100) DEFAULT NULL,
    PasoBot VARCHAR(50) DEFAULT 'BIENVENIDA', -- BIENVENIDA, TERMINOS, INGRESO_NOMBRE, INGRESO_EMAIL, INGRESO_ESTADO, CONFIRMACION_DATOS, FOTO_PENDIENTE, COMPLETADO
    TerminosAceptados TINYINT(1) DEFAULT 0,
    TempNombre VARCHAR(100) DEFAULT NULL,
    TempEmail VARCHAR(150) DEFAULT NULL,
    TempEstado VARCHAR(100) DEFAULT NULL,
    CodigoParticipacion VARCHAR(50) DEFAULT NULL,
    FechaRegistro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FechaActualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblTelefonia (
    idTelefonia INT AUTO_INCREMENT PRIMARY KEY,
    Telefonia VARCHAR(50) NOT NULL,
    SKU VARCHAR(50) NOT NULL,
    Activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblCadena (
    idCadena INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    Activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblProducto (
    idProducto INT AUTO_INCREMENT PRIMARY KEY,
    Producto VARCHAR(255) NOT NULL,
    SKU VARCHAR(50) DEFAULT NULL,
    Activo TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblRegistro (
    idRegistro INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    Token VARCHAR(64) UNIQUE NOT NULL,
    Estatus INT DEFAULT 1, -- 1 = Pendiente validación, 2 = Aprobado, 3 = Rechazado
    EstatusDescarga TINYINT(1) DEFAULT 0,
    Monto DECIMAL(10, 2) DEFAULT 0.00,
    FotoCajas VARCHAR(255) DEFAULT NULL,
    CodigoUnico VARCHAR(50) DEFAULT NULL,
    MotivoRechazo VARCHAR(255) DEFAULT NULL,
    FolioTicket VARCHAR(100) DEFAULT NULL,
    FechaTicket DATE DEFAULT NULL,
    MontoTicket DECIMAL(10, 2) DEFAULT NULL,
    idCadena INT DEFAULT NULL,
    idProducto INT DEFAULT NULL,
    TelefonoRecarga VARCHAR(15) DEFAULT NULL,
    idTelefonia INT DEFAULT NULL,
    FolioRecarga VARCHAR(50) DEFAULT NULL,
    TransID VARCHAR(100) DEFAULT NULL,
    Saldo_Final DECIMAL(10, 2) DEFAULT NULL,
    FechaRegistro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FechaValidacion DATETIME DEFAULT NULL,
    FechaDescarga DATETIME DEFAULT NULL,
    Activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (idUsuario) REFERENCES tblUsuario(idUsuario),
    FOREIGN KEY (idTelefonia) REFERENCES tblTelefonia(idTelefonia),
    FOREIGN KEY (idCadena) REFERENCES tblCadena(idCadena),
    FOREIGN KEY (idProducto) REFERENCES tblProducto(idProducto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblLogRecarga (
    idLogRecarga INT AUTO_INCREMENT PRIMARY KEY,
    idRegistro INT NOT NULL,
    Mensaje TEXT DEFAULT NULL,
    Codigo VARCHAR(50) DEFAULT NULL,
    FechaRegistro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idRegistro) REFERENCES tblRegistro(idRegistro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblLog (
    idLog INT AUTO_INCREMENT PRIMARY KEY,
    idRegistro INT DEFAULT NULL,
    Accion VARCHAR(100) DEFAULT NULL,
    Descripcion TEXT DEFAULT NULL,
    Payload TEXT DEFAULT NULL,
    FechaRegistro DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar Telefonías por defecto
INSERT INTO tblTelefonia (Telefonia, SKU, Activo) VALUES
('Telcel', 'TELCEL', 1),
('Movistar', 'MOVISTAR', 1),
('AT&T', 'ATT', 1),
('Bait', 'BAIT', 1),
('Virgin Mobile', 'VIRGIN', 1),
('Unefon', 'UNEFON', 1)
ON DUPLICATE KEY UPDATE Telefonia=VALUES(Telefonia);

-- Insertar Cadenas por defecto
INSERT INTO tblCadena (Nombre, Activo) VALUES
('Oxxo', 1),
('Walmart', 1),
('Soriana', 1),
('Chedraui', 1),
('7-Eleven', 1),
('Farmacias Guadalajara', 1),
('Otros / Kioskos', 1);

-- Insertar Productos por defecto (Gatorade)
INSERT INTO tblProducto (Producto, SKU, Activo) VALUES
('Gatorade Ponche 600ml', 'GAT-PON-600', 1),
('Gatorade Lima Limón 600ml', 'GAT-LIM-600', 1),
('Gatorade Naranja 600ml', 'GAT-NAR-600', 1),
('Gatorade Ponche 1L', 'GAT-PON-1L', 1),
('Gatorade Lima Limón 1L', 'GAT-LIM-1L', 1),
('Gatorade Naranja 1L', 'GAT-NAR-1L', 1),
('Gatorade Blue Cherry 600ml', 'GAT-BLU-600', 1);
