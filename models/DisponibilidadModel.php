<?php
class DisponibilidadModel {
    private $pdo;
    private const ESTADOS_OCUPADOS = ['solicitada', 'pendiente', 'confirmada', 'reagendada', 'aprobada'];

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

    public function listExistingTimes(string $fecha, array $horas): array {
        if (empty($horas)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($horas), '?'));
        $sql = "SELECT TIME_FORMAT(hora, '%H:%i:%s') AS hora
                FROM horarios_disponibles
                WHERE fecha = ? AND hora IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$fecha], $horas));

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'hora');
    }

    public function listTimesWithActiveBookings(string $fecha, array $horas): array {
        if (empty($horas)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($horas), '?'));
        $estadosPlaceholders = implode(',', array_fill(0, count(self::ESTADOS_OCUPADOS), '?'));
        $sql = "SELECT DISTINCT TIME_FORMAT(hora, '%H:%i:%s') AS hora
                FROM citas
                WHERE DATE(fecha) = ?
                  AND hora IN ($placeholders)
                  AND estado IN ($estadosPlaceholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$fecha], $horas, self::ESTADOS_OCUPADOS));

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'hora');
    }

    public function listSlotsByTimes(string $fecha, array $horas): array {
        if (empty($horas)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($horas), '?'));
        $sql = "SELECT id, fecha, TIME_FORMAT(hora, '%H:%i:%s') AS hora,
                       activo, tipo, motivo
                FROM horarios_disponibles
                WHERE fecha = ? AND hora IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$fecha], $horas));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertSlots(string $fecha, array $horas, bool $disponible, ?string $motivo = null, ?string $tipo = null): int {
        if (empty($horas)) {
            return 0;
        }

        $tipo = $tipo ?: ($disponible ? 'disponible' : 'bloqueo');
        $values = [];
        $params = [];

        foreach ($horas as $hora) {
            $values[] = "(?, ?, ?, ?, ?)";
            $params[] = $fecha;
            $params[] = $hora;
            $params[] = $disponible ? 1 : 0;
            $params[] = $tipo;
            $params[] = $motivo;
        }

        $sql = "INSERT IGNORE INTO horarios_disponibles (fecha, hora, activo, tipo, motivo)
                VALUES " . implode(', ', $values);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function enableSlots(string $fecha, array $horas, ?string $motivo = null): int {
        if (empty($horas)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($horas), '?'));
        $sql = "UPDATE horarios_disponibles
                SET activo = 1, tipo = 'disponible', motivo = ?
                WHERE fecha = ?
                  AND hora IN ($placeholders)
                  AND (activo <> 1 OR tipo <> 'disponible')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$motivo, $fecha], $horas));

        return $stmt->rowCount();
    }

    public function listDaySlots(string $fecha): array {
        $sql = "SELECT h.id, h.fecha, TIME_FORMAT(h.hora, '%H:%i:%s') AS hora,
                       h.activo, h.tipo, h.motivo,
                       MAX(CASE WHEN c.id IS NULL THEN 0 ELSE 1 END) AS ocupada
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(c.fecha), c.hora)
                 AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(c.duracion_min, 30), TIMESTAMP(DATE(c.fecha), c.hora))
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
                WHERE h.fecha = :fecha
                GROUP BY h.id, h.fecha, h.hora, h.activo, h.tipo, h.motivo
                ORDER BY h.hora ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['fecha' => $fecha]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setDayAvailability(string $fecha, bool $disponible, ?string $motivo = null): int {
        $activo = $disponible ? 1 : 0;
        $tipo = $disponible ? 'disponible' : 'bloqueo';
        $sql = "UPDATE horarios_disponibles h
                SET h.activo = :activo,
                    h.tipo = :tipo,
                    h.motivo = :motivo
                WHERE h.fecha = :fecha
                  AND (h.activo <> :activo_check OR h.tipo <> :tipo_check)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM citas c
                      WHERE TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(c.fecha), c.hora)
                        AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(c.duracion_min, 30), TIMESTAMP(DATE(c.fecha), c.hora))
                        AND c.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
                  )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'activo' => $activo,
            'tipo' => $tipo,
            'motivo' => $motivo,
            'fecha' => $fecha,
            'activo_check' => $activo,
            'tipo_check' => $tipo,
        ]);

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
            if ($activo === 1) {
                $where[] = "h.activo = 1";
                $where[] = "h.tipo = 'disponible'";
                $where[] = "NOT EXISTS (
                    SELECT 1 FROM citas cx
                    WHERE TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(cx.fecha), cx.hora)
                      AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(cx.duracion_min, 30), TIMESTAMP(DATE(cx.fecha), cx.hora))
                      AND cx.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
                )";
            } else {
                $where[] = "(h.activo <> 1 OR h.tipo <> 'disponible' OR EXISTS (
                    SELECT 1 FROM citas cx
                    WHERE TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(cx.fecha), cx.hora)
                      AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(cx.duracion_min, 30), TIMESTAMP(DATE(cx.fecha), cx.hora))
                      AND cx.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
                ))";
            }
        }

        $sql = "SELECT h.id, h.fecha, TIME_FORMAT(h.hora, '%H:%i') AS hora,
                       CASE WHEN COUNT(c.id) > 0 THEN 0 ELSE h.activo END AS disponible,
                       h.tipo, h.motivo, h.creado_en, h.updated_at,
                       CASE
                           WHEN COUNT(c.id) > 0 THEN 'reservado'
                           WHEN h.activo = 1 AND h.tipo = 'disponible' THEN 'disponible'
                           ELSE 'bloqueo'
                       END AS estado,
                       MIN(c.id) AS reserva_id,
                       MAX(CASE WHEN c.id IS NULL THEN 0 ELSE 1 END) AS ocupada
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(c.fecha), c.hora)
                 AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(c.duracion_min, 30), TIMESTAMP(DATE(c.fecha), c.hora))
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
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
        $slotTs = strtotime($fecha . ' ' . $hora);
        if ($slotTs === false) {
            return true;
        }

        $slotAt = date('Y-m-d H:i:s', $slotTs);
        $params = ['slot_at_start' => $slotAt, 'slot_at_end' => $slotAt];
        $sql = "SELECT COUNT(*) FROM citas
                WHERE :slot_at_start >= CONCAT(DATE(fecha), ' ', TIME_FORMAT(hora, '%H:%i:%s'))
                  AND :slot_at_end < TIMESTAMPADD(MINUTE, COALESCE(duracion_min, 30), TIMESTAMP(DATE(fecha), hora))
                  AND estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')";
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
                  ON TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(c.fecha), c.hora)
                 AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(c.duracion_min, 30), TIMESTAMP(DATE(c.fecha), c.hora))
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
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
                  ON TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(c.fecha), c.hora)
                 AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(c.duracion_min, 30), TIMESTAMP(DATE(c.fecha), c.hora))
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
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
                       CASE WHEN COUNT(c.id) > 0 THEN 0 ELSE h.activo END AS activo,
                       h.tipo,
                       h.motivo,
                       CASE
                           WHEN COUNT(c.id) > 0 THEN 'reservado'
                           WHEN h.activo = 1 AND h.tipo = 'disponible' THEN 'disponible'
                           ELSE 'bloqueo'
                       END AS estado,
                       MIN(c.id) AS reserva_id,
                       MAX(CASE WHEN c.id IS NULL THEN 0 ELSE 1 END) AS ocupada
                FROM horarios_disponibles h
                LEFT JOIN citas c
                  ON TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(c.fecha), c.hora)
                 AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(c.duracion_min, 30), TIMESTAMP(DATE(c.fecha), c.hora))
                 AND c.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
                WHERE h.fecha BETWEEN :desde AND :hasta";
        if (!$includeInactive) {
            $sql .= " AND h.activo = 1 AND h.tipo = 'disponible'
                      AND NOT EXISTS (
                          SELECT 1 FROM citas cx
                          WHERE TIMESTAMP(h.fecha, h.hora) >= TIMESTAMP(DATE(cx.fecha), cx.hora)
                            AND TIMESTAMP(h.fecha, h.hora) < TIMESTAMPADD(MINUTE, COALESCE(cx.duracion_min, 30), TIMESTAMP(DATE(cx.fecha), cx.hora))
                            AND cx.estado IN ('solicitada','pendiente','confirmada','reagendada','aprobada')
                      )";
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

