-- Base de datos para Bot WhatsApp La Central "Clásicos de la Fe"

CREATE TABLE IF NOT EXISTS tblUsuario (
    idUsuario INT AUTO_INCREMENT PRIMARY KEY,
    Celular VARCHAR(20) UNIQUE NOT NULL,
    Nombre VARCHAR(100) DEFAULT NULL,
    PasoBot VARCHAR(50) DEFAULT 'BIENVENIDA', -- BIENVENIDA, TERMINOS, VIDEO, FOTO_PENDIENTE, COMPLETADO
    TerminosAceptados TINYINT(1) DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS tblRegistro (
    idRegistro INT AUTO_INCREMENT PRIMARY KEY,
    idUsuario INT NOT NULL,
    Token VARCHAR(64) UNIQUE NOT NULL,
    Estatus INT DEFAULT 1, -- 1 = Pendiente validación, 2 = Aprobado listo para canje, 3 = Rechazado, 4 = Canjeado (recarga exitosa), 5 = Recarga en proceso (Taecel)
    EstatusDescarga TINYINT(1) DEFAULT 0,
    Monto DECIMAL(10, 2) DEFAULT 20.00,
    FotoCajas VARCHAR(255) DEFAULT NULL,
    CodigoUnico VARCHAR(50) DEFAULT NULL,
    MotivoRechazo VARCHAR(255) DEFAULT NULL,
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
    FOREIGN KEY (idTelefonia) REFERENCES tblTelefonia(idTelefonia)
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
