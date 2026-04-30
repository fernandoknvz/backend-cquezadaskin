<?php
class DashboardModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ✅ Resumen general completo
    public function getOverview() {
        $result = [
            "resumen" => [],
            "citas_por_estado_mes" => [],
            "clientes_por_semana_8w" => [],
            "top_servicios_30d" => [],
            "citas_hoy" => []
        ];

        // 1️⃣ Total de citas del mes actual
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS total_citas_mes
            FROM citas
            WHERE MONTH(fecha) = MONTH(CURDATE())
              AND YEAR(fecha) = YEAR(CURDATE())
        ");
        $result["resumen"]["total_citas_mes"] = (int)$stmt->fetchColumn();

        // 2️⃣ Nuevos clientes últimas 8 semanas (usa creado_en)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) AS total_clientes_8w
            FROM clientes
            WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
        ");
        $result["resumen"]["total_clientes_8w"] = (int)$stmt->fetchColumn();

        // 3️⃣ Citas por estado del mes actual
        $stmt = $this->pdo->query("
            SELECT estado, COUNT(*) AS total
            FROM citas
            WHERE MONTH(fecha) = MONTH(CURDATE())
              AND YEAR(fecha) = YEAR(CURDATE())
            GROUP BY estado
        ");
        $result["citas_por_estado_mes"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4️⃣ Clientes nuevos por semana (últimas 8 semanas)
        $stmt = $this->pdo->query("
            SELECT YEARWEEK(creado_en, 1) AS semana,
                   COUNT(*) AS total
            FROM clientes
            WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
            GROUP BY YEARWEEK(creado_en, 1)
            ORDER BY semana ASC
        ");
        $result["clientes_por_semana_8w"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5️⃣ Citas de hoy
        $stmt = $this->pdo->query("
            SELECT c.id,
                   TIME_FORMAT(c.hora, '%H:%i') AS hora,
                   cli.nombre AS cliente,
                   s.nombre AS servicio,
                   c.estado
            FROM citas c
            INNER JOIN clientes cli ON c.cliente_id = cli.id
            INNER JOIN servicios s ON c.servicio_id = s.id
            WHERE DATE(c.fecha) = CURDATE()
            ORDER BY c.hora ASC
        ");
        $result["citas_hoy"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6️⃣ Top servicios últimos 30 días
        $stmt = $this->pdo->query("
            SELECT s.id AS servicio_id,
                   s.nombre AS servicio_nombre,
                   COUNT(*) AS total
            FROM citas c
            INNER JOIN servicios s ON c.servicio_id = s.id
            WHERE c.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY s.id
            ORDER BY total DESC
            LIMIT 5
        ");
        $result["top_servicios_30d"] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    // ✅ Citas de hoy (detalle)
    public function getCitasHoy() {
        $sql = "
            SELECT c.id,
                   TIME_FORMAT(c.hora, '%H:%i') AS hora,
                   cli.nombre AS cliente,
                   s.nombre AS servicio,
                   c.estado
            FROM citas c
            INNER JOIN clientes cli ON c.cliente_id = cli.id
            INNER JOIN servicios s ON c.servicio_id = s.id
            WHERE DATE(c.fecha) = CURDATE()
            ORDER BY c.hora ASC
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ Servicios más agendados últimos 30 días
    public function getTopServicios() {
        $sql = "
            SELECT s.id AS servicio_id,
                   s.nombre AS servicio_nombre,
                   COUNT(*) AS total
            FROM citas c
            INNER JOIN servicios s ON c.servicio_id = s.id
            WHERE c.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY s.id
            ORDER BY total DESC
            LIMIT 5
        ";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
