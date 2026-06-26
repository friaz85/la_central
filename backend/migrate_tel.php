<?php
require_once __DIR__ . '/db.php';

try {
    DB::execute("SET FOREIGN_KEY_CHECKS = 0");
    DB::execute("TRUNCATE TABLE tblTelefonia");
    
    DB::execute("INSERT INTO tblTelefonia (idTelefonia, Telefonia, SKU, Activo) VALUES (1, 'TELCEL', 'TEL', 1)");
    DB::execute("INSERT INTO tblTelefonia (idTelefonia, Telefonia, SKU, Activo) VALUES (2, 'AT&T', 'ATT', 1)");
    DB::execute("INSERT INTO tblTelefonia (idTelefonia, Telefonia, SKU, Activo) VALUES (3, 'UNEFON', 'UNE', 1)");
    DB::execute("INSERT INTO tblTelefonia (idTelefonia, Telefonia, SKU, Activo) VALUES (4, 'MOVISTAR', 'MOV', 1)");
    DB::execute("INSERT INTO tblTelefonia (idTelefonia, Telefonia, SKU, Activo) VALUES (5, 'VIRGIN', 'VIR', 1)");
    
    DB::execute("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration successful: tblTelefonia populated with 5 companies.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
