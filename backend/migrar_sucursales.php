<?php
// migrar_sucursales.php — Script para crear tblSucursal, alterar tblRegistro e importar tiendas desde el CSV
require_once __DIR__ . '/db.php';

echo "Iniciando migración de sucursales...\n";

try {
    $pdo = DB::connect();

    // 1. Crear tabla tblSucursal
    echo "Creando tabla tblSucursal si no existe...\n";
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS tblSucursal (
            idSucursal INT AUTO_INCREMENT PRIMARY KEY,
            idCadena INT NOT NULL,
            NumeroTienda VARCHAR(50) NOT NULL,
            Tienda VARCHAR(150) NOT NULL,
            Activo TINYINT(1) DEFAULT 1,
            FOREIGN KEY (idCadena) REFERENCES tblCadena(idCadena),
            UNIQUE KEY uq_cadena_tienda (idCadena, NumeroTienda)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($createTableQuery);
    echo "¡Tabla tblSucursal lista!\n";

    // 2. Alterar tblRegistro para agregar idSucursal
    echo "Verificando si la columna idSucursal existe en tblRegistro...\n";
    $checkColumn = DB::select("SHOW COLUMNS FROM tblRegistro LIKE 'idSucursal'");
    if (empty($checkColumn)) {
        echo "Agregando columna idSucursal a tblRegistro...\n";
        $pdo->exec("ALTER TABLE tblRegistro ADD COLUMN idSucursal INT DEFAULT NULL");
        echo "Agregando relación de llave foránea...\n";
        $pdo->exec("ALTER TABLE tblRegistro ADD CONSTRAINT fk_registro_sucursal FOREIGN KEY (idSucursal) REFERENCES tblSucursal(idSucursal)");
        echo "¡Columna y llave foránea añadidas con éxito!\n";
    } else {
        echo "La columna idSucursal ya existe en tblRegistro.\n";
    }

    // 3. Obtener IDs de las cadenas
    $oxxoRow = DB::selectOne("SELECT idCadena FROM tblCadena WHERE Nombre LIKE '%Oxxo%' OR Nombre LIKE '%OXXO%' LIMIT 1");
    $sevenRow = DB::selectOne("SELECT idCadena FROM tblCadena WHERE Nombre LIKE '%Seven%' OR Nombre LIKE '%7-Eleven%' OR Nombre LIKE '%7%' LIMIT 1");

    if (!$oxxoRow) {
        throw new Exception("No se encontró la cadena Oxxo en tblCadena");
    }
    if (!$sevenRow) {
        throw new Exception("No se encontró la cadena 7-Eleven en tblCadena");
    }

    $idOxxo = (int)$oxxoRow['idCadena'];
    $idSeven = (int)$sevenRow['idCadena'];

    echo "IDs obtenidos - Oxxo: {$idOxxo}, 7-Eleven: {$idSeven}\n";

    // 4. Leer archivo CSV
    $csvPath = __DIR__ . '/TIENDAS_GATORADE.csv';
    if (!file_exists($csvPath)) {
        $csvPath = dirname(__DIR__) . '/Recursos/TIENDAS_GATORADE.csv';
    }
    if (!file_exists($csvPath)) {
        throw new Exception("No se encontró el archivo CSV en: " . $csvPath);
    }

    echo "Procesando archivo CSV: {$csvPath}...\n";
    $file = fopen($csvPath, 'r');
    
    // Leer cabecera (Cadena,NúmeroTienda,Tienda)
    $header = fgetcsv($file);
    
    $inserted = 0;
    $updated = 0;

    $stmtInsert = $pdo->prepare("
        INSERT INTO tblSucursal (idCadena, NumeroTienda, Tienda, Activo)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE Tienda = VALUES(Tienda), Activo = 1
    ");

    while (($row = fgetcsv($file)) !== false) {
        if (count($row) < 3) continue;
        
        $cadenaStr = trim($row[0]);
        $numeroTienda = trim($row[1]);
        $tiendaNombre = trim($row[2]);

        $idCadena = null;
        if (strtoupper($cadenaStr) === 'OXXO') {
            $idCadena = $idOxxo;
        } elseif (strtoupper($cadenaStr) === 'SEVEN') {
            $idCadena = $idSeven;
        }

        if ($idCadena !== null && !empty($numeroTienda) && !empty($tiendaNombre)) {
            $stmtInsert->execute([$idCadena, $numeroTienda, $tiendaNombre]);
            $inserted++;
        }
    }

    fclose($file);
    echo "¡Proceso terminado con éxito! Total procesados/importados: {$inserted} sucursales.\n";

} catch (Exception $e) {
    echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
    exit(1);
}
