<?php
class DisponibilidadModel {
    private $pdo;
    private const ESTADOS_OCUPADOS = ['solicitada', 'pendiente', 'confirmada', 'reagendada'];

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
                $values[] = "(?, ?, ?, ?, NULL)";
                $params[] = $fecha;
                $params[] = $hora;
                $params[] = $activo;
                $params[] = $activo === 1 ? 'disponible' : 'bloqueo';
            }
        }

        if (empty($values)) {
            return 0;
        }

        $sql = "INSERT INTO horarios_disponibles (fecha, hora, activo, tipo, motivo) VALUES "
            . implode(", ", $values)
            . " ON DUPLICATE KEY UPDATE activo = VALUES(activo), tipo = VALUES(tipo), motivo = VALUES(motivo)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function listAdmin(array $filters): array {
        $desde = $filters['fecha_desde'] ?? date('Y-m-d');
        $hasta = $filters['fecha_hasta'] ?? date('Y-m-d', strtotime('+30 days'));
        $tipo = $filters['tipo'] ?? null;
        $activo = array_key_exists('activo', $filters) ? (int)$filters['activo'] : null;

        $where = ["h.fecha BETWEEN :desde AND :hasta"];
        $params = [':desde' => $desde, ':hasta' => $hasta];

        if ($tipo !== null) {
            $where[] = "h.tipo = :tipo";
            $params[':tipo'] = $tipo;
        }

        if ($activo !== null) {
            $where[] = "h.activo = :activo";
            $params[':activo'] = $activo;
        }

        $sql = "SELECT h.id, h.fecha, TIME_FORMAT(h.hora, '%H:%i') AS hora,
                       h.activo AS disponible, h.tipo, h.motivo, h.creado_en, h.updated_at,
                       MAX(CASE WHEN c.id IS NULL THEN 0 ELSE 1 END) AS ocupada
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON DATE(c.fecha) = h.fecha
                 AND c.hora = h.hora
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada')
                WHERE " . implode(' AND ', $where) . "
                GROUP BY h.id, h.fecha, h.hora, h.activo, h.tipo, h.motivo, h.creado_en, h.updated_at
                ORDER BY h.fecha ASC, h.hora ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT id, fecha, TIME_FORMAT(hora, '%H:%i') AS hora, activo AS disponible,
                    tipo, motivo, creado_en, updated_at
             FROM horarios_disponibles
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByDateTime(string $fecha, string $hora) {
        $stmt = $this->pdo->prepare(
            "SELECT id, fecha, TIME_FORMAT(hora, '%H:%i') AS hora, activo AS disponible,
                    tipo, motivo, creado_en, updated_at
             FROM horarios_disponibles
             WHERE fecha = :fecha AND hora = :hora"
        );
        $stmt->execute([':fecha' => $fecha, ':hora' => $hora]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function upsertSlot(string $fecha, string $hora, bool $disponible, ?string $motivo = null, ?string $tipo = null): int {
        $tipo = $tipo ?: ($disponible ? 'disponible' : 'bloqueo');
        $stmt = $this->pdo->prepare(
            "INSERT INTO horarios_disponibles (fecha, hora, activo, tipo, motivo)
             VALUES (:fecha, :hora, :activo, :tipo, :motivo)
             ON DUPLICATE KEY UPDATE
                activo = VALUES(activo),
                tipo = VALUES(tipo),
                motivo = VALUES(motivo)"
        );
        $stmt->execute([
            ':fecha' => $fecha,
            ':hora' => $hora,
            ':activo' => $disponible ? 1 : 0,
            ':tipo' => $tipo,
            ':motivo' => $motivo,
        ]);

        $slot = $this->getByDateTime($fecha, $hora);
        return (int)($slot['id'] ?? 0);
    }

    public function updateSlot(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        foreach (['fecha', 'hora', 'tipo', 'motivo'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (array_key_exists('disponible', $data)) {
            $fields[] = "activo = :activo";
            $params[':activo'] = $data['disponible'] ? 1 : 0;
        }

        if (empty($fields)) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE horarios_disponibles SET " . implode(', ', $fields) . " WHERE id = :id");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deleteSlot(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM horarios_disponibles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function hasActiveBooking(string $fecha, string $hora, ?int $excludeId = null): bool {
        $params = ['fecha' => $fecha, 'hora' => $hora];
        $sql = "SELECT COUNT(*) FROM citas
                WHERE DATE(fecha) = :fecha
                  AND hora = :hora
                  AND estado IN ('solicitada','pendiente','confirmada','reagendada')";
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getAvailableTimesByDate(string $fecha, ?string $minHora = null): array {
        $sql = "SELECT TIME_FORMAT(h.hora, '%H:%i') AS hora
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON DATE(c.fecha) = h.fecha
                 AND c.hora = h.hora
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada')
                WHERE h.fecha = :fecha
                  AND h.activo = 1
                  AND h.tipo = 'disponible'
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
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada')
                WHERE h.activo = 1
                  AND h.tipo = 'disponible'
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
                       h.tipo,
                       h.motivo,
                       MAX(CASE WHEN c.id IS NULL THEN 0 ELSE 1 END) AS ocupada
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON DATE(c.fecha) = h.fecha
                 AND c.hora = h.hora
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada')
                WHERE h.fecha BETWEEN :desde AND :hasta";
        if (!$includeInactive) {
            $sql .= " AND h.activo = 1 AND h.tipo = 'disponible'";
        }
        $sql .= " GROUP BY h.id, h.fecha, h.hora, h.activo, h.tipo, h.motivo
                 ORDER BY h.fecha ASC, h.hora ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isSlotAvailable(string $fecha, string $hora, ?int $excludeId = null): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM horarios_disponibles
             WHERE fecha = :fecha AND hora = :hora AND activo = 1 AND tipo = 'disponible'"
        );
        $stmt->execute(['fecha' => $fecha, 'hora' => $hora]);
        if ((int)$stmt->fetchColumn() === 0) {
            return false;
        }

        return !$this->hasActiveBooking($fecha, $hora, $excludeId);
    }
}
