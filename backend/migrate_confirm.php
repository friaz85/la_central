<?php
require_once __DIR__ . '/db.php';

try {
    // Add TempTelefonoRecarga column to tblUsuario if it doesn't exist
    DB::execute("ALTER TABLE tblUsuario ADD COLUMN TempTelefonoRecarga VARCHAR(15) DEFAULT NULL AFTER TempIdTelefonia");
    echo "Migration successful: TempTelefonoRecarga column added to tblUsuario.\n";
} catch (Exception $e) {
    echo "Migration note/failed: " . $e->getMessage() . "\n";
}
