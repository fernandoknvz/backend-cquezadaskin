<?php
class DisponibilidadModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createBulk(array $fechas, array $horas): int {
        return $this->setActiveBulk($fechas, $horas, 1);
    }

    public function deleteBulk(array $fechas, array $horas): int {
        return $this->setActiveBulk($fechas, $horas, 0);
    }

    public function setActiveBulk(array $fechas, array $horas, int $activo): int {
        if (empty($fechas) || empty($horas)) {
            return 0;
        }

        $values = [];
        $params = [];
        foreach ($fechas as $fecha) {
            foreach ($horas as $hora) {
                $values[] = "(?, ?, ?)";
                $params[] = $fecha;
                $params[] = $hora;
                $params[] = $activo;
            }
        }

        if (empty($values)) {
            return 0;
        }

        $sql = "INSERT INTO horarios_disponibles (fecha, hora, activo) VALUES "
            . implode(", ", $values)
            . " ON DUPLICATE KEY UPDATE activo = VALUES(activo)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function getAvailableTimesByDate(string $fecha, ?string $minHora = null): array {
        $sql = "SELECT TIME_FORMAT(h.hora, '%H:%i') AS hora
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON DATE(c.fecha) = h.fecha
                 AND c.hora = h.hora
                 AND c.estado != 'cancelada'
                WHERE h.fecha = :fecha
                  AND h.activo = 1
                  AND c.id IS NULL";
    
        $params = ['fecha' => $fecha];
    
        if ($minHora !== null && $minHora !== '') {
            $sql .= " AND h.hora >= :min_hora";
            $params['min_hora'] = $minHora;
        }
    
        $sql .= " ORDER BY h.hora ASC";
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'hora');
    }

    public function getAvailableDaysByRange(string $desde, string $hasta): array {
        $sql = "SELECT DISTINCT h.fecha
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON DATE(c.fecha) = h.fecha
                 AND c.hora = h.hora
                 AND c.estado != 'cancelada'
                WHERE h.activo = 1
                  AND h.fecha BETWEEN :desde AND :hasta
                  AND c.id IS NULL
                ORDER BY h.fecha ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'fecha');
    }

    public function listSlotsByRange(string $desde, string $hasta, bool $includeInactive = false): array {
        $sql = "SELECT h.fecha,
                       TIME_FORMAT(h.hora, '%H:%i') AS hora,
                       h.activo,
                       MAX(CASE WHEN c.id IS NULL THEN 0 ELSE 1 END) AS ocupada
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON DATE(c.fecha) = h.fecha
                 AND c.hora = h.hora
                 AND c.estado != 'cancelada'
                WHERE h.fecha BETWEEN :desde AND :hasta";
        if (!$includeInactive) {
            $sql .= " AND h.activo = 1";
        }
        $sql .= " GROUP BY h.id, h.fecha, h.hora, h.activo
                 ORDER BY h.fecha ASC, h.hora ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isSlotAvailable(string $fecha, string $hora, ?int $excludeId = null): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM horarios_disponibles
             WHERE fecha = :fecha AND hora = :hora AND activo = 1"
        );
        $stmt->execute(['fecha' => $fecha, 'hora' => $hora]);
        if ((int)$stmt->fetchColumn() === 0) {
            return false;
        }

        $params = ['fecha' => $fecha, 'hora' => $hora];
        $sql = "SELECT COUNT(*) FROM citas
                WHERE DATE(fecha) = :fecha
                  AND hora = :hora
                  AND estado != 'cancelada'";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() === 0;
    }
}
