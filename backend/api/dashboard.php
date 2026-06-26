<?php
// api/dashboard.php — Estadísticas e información del panel administrativo
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/auth_helper.php';
$userData = validateAuth(); // Proteger endpoint

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $startDate = $_GET['start_date'] ?? null;
        $endDate   = $_GET['end_date'] ?? null;
        $s         = $startDate ? $startDate . ' 00:00:00' : null;
        $e         = $endDate   ? $endDate   . ' 23:59:59' : null;

        // Totales globales
        $totalRegistros = (int)DB::selectOne("SELECT COUNT(*) as total FROM tblRegistro WHERE Activo = 1")['total'];
        $pendientes     = (int)DB::selectOne("SELECT COUNT(*) as total FROM tblRegistro WHERE Activo = 1 AND Estatus = 1")['total'];
        $rechazados     = (int)DB::selectOne("SELECT COUNT(*) as total FROM tblRegistro WHERE Activo = 1 AND Estatus = 3")['total'];
        $exitosas       = (int)DB::selectOne("SELECT COUNT(*) as total FROM tblRegistro WHERE Activo = 1 AND Estatus = 4")['total'];
        $enProceso      = (int)DB::selectOne("SELECT COUNT(*) as total FROM tblRegistro WHERE Activo = 1 AND Estatus = 5")['total'];
        $usuarios       = (int)DB::selectOne("SELECT COUNT(*) as total FROM tblUsuario")['total'];

        // Filtrado por fecha
        $filteredSql = "SELECT COUNT(*) as total, SUM(Estatus = 4) as exitosas, SUM(Estatus = 3) as rechazadas FROM tblRegistro WHERE Activo = 1";
        $params = [];
        if ($s && $e) {
            $filteredSql .= " AND FechaRegistro BETWEEN ? AND ?";
            $params = [$s, $e];
        }
        $filteredRow = DB::selectOne($filteredSql, $params);
        $filteredTotal = (int)($filteredRow['total'] ?? 0);
        $filteredExitosas = (int)($filteredRow['exitosas'] ?? 0);
        $filteredRechazadas = (int)($filteredRow['rechazadas'] ?? 0);

        // Chart: tickets por día (últimos 7 días o rango)
        if ($s && $e) {
            $chartSql = "SELECT DATE(FechaRegistro) as fecha,
                            COUNT(*) as total,
                            SUM(Estatus = 4) as aprobados,
                            SUM(Estatus = 3) as rechazados
                         FROM tblRegistro
                         WHERE Activo = 1 AND FechaRegistro BETWEEN ? AND ?
                         GROUP BY DATE(FechaRegistro) ORDER BY fecha ASC";
            $chartData = DB::select($chartSql, [$s, $e]);
        } else {
            $chartSql = "SELECT DATE(FechaRegistro) as fecha,
                            COUNT(*) as total,
                            SUM(Estatus = 4) as aprobados,
                            SUM(Estatus = 3) as rechazados
                         FROM tblRegistro
                         WHERE Activo = 1 AND FechaRegistro >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE(FechaRegistro) ORDER BY fecha ASC";
            $chartData = DB::select($chartSql);
        }

        // Top telefonías (basado en registros completados / aprobados)
        $topTelefonias = DB::select("
            SELECT t.Telefonia, COUNT(r.idTelefonia) as total
            FROM tblRegistro r
            JOIN tblTelefonia t ON t.idTelefonia = r.idTelefonia
            WHERE r.Estatus IN (4,5) AND r.Activo = 1
            GROUP BY r.idTelefonia ORDER BY total DESC LIMIT 5
        ");

        // Actividad reciente
        $recent = DB::select("
            SELECT r.idRegistro, r.Estatus, r.FechaRegistro, u.Nombre, u.Celular, t.Telefonia
            FROM tblRegistro r
            JOIN tblUsuario u ON u.idUsuario = r.idUsuario
            LEFT JOIN tblTelefonia t ON t.idTelefonia = r.idTelefonia
            WHERE r.Activo = 1
            ORDER BY r.FechaRegistro DESC
            LIMIT 10
        ");

        echo json_encode([
            "success" => true,
            "cards" => [
                "total"       => $totalRegistros,
                "pendientes"  => $pendientes,
                "rechazados"  => $rechazados,
                "canjes"      => $exitosas,
                "aprobados"   => $exitosas + $enProceso,
                "usuarios"    => $usuarios,
                "filtered"    => [
                    "total"     => $filteredTotal,
                    "aprobados" => $filteredExitosas,
                    "rechazados" => $filteredRechazadas
                ]
            ],
            "chart"          => $chartData,
            "top_telefonias" => $topTelefonias,
            "recent"         => $recent
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Error al obtener estadísticas del dashboard: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido"]);
}
