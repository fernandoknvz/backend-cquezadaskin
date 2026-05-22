<?php
class CitaModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ✅ Obtener todas las citas
    public function getAll() {
        $sql = "SELECT c.id, c.cliente_id, c.servicio_id,
                       cli.nombre AS cliente, cli.correo, cli.telefono,
                       s.nombre AS servicio, c.fecha, c.hora, c.estado
                FROM citas c
                INNER JOIN clientes cli ON cli.id = c.cliente_id
                INNER JOIN servicios s ON s.id = c.servicio_id
                ORDER BY c.fecha DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $sql = "SELECT c.id, c.cliente_id, c.servicio_id,
                       cli.nombre AS cliente, cli.correo, cli.telefono,
                       s.nombre AS servicio, c.fecha, c.hora, c.estado,
                       c.observacion_admin, c.creado_en, c.updated_at
                FROM citas c
                INNER JOIN clientes cli ON cli.id = c.cliente_id
                INNER JOIN servicios s ON s.id = c.servicio_id
                WHERE c.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listAdmin(array $filters): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($filters['estado'])) {
            $where[] = "c.estado = :estado";
            $params[':estado'] = $filters['estado'];
        }
        if (!empty($filters['fecha_desde'])) {
            $where[] = "DATE(c.fecha) >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "DATE(c.fecha) <= :fecha_hasta";
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['cliente_id'])) {
            $where[] = "c.cliente_id = :cliente_id";
            $params[':cliente_id'] = (int)$filters['cliente_id'];
        }
        if (!empty($filters['servicio_id'])) {
            $where[] = "c.servicio_id = :servicio_id";
            $params[':servicio_id'] = (int)$filters['servicio_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(cli.nombre LIKE :search_cliente OR cli.correo LIKE :search_correo OR cli.telefono LIKE :search_telefono OR s.nombre LIKE :search_servicio)";
            $like = '%' . trim((string)$filters['search']) . '%';
            $params[':search_cliente'] = $like;
            $params[':search_correo'] = $like;
            $params[':search_telefono'] = $like;
            $params[':search_servicio'] = $like;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM citas c
             INNER JOIN clientes cli ON cli.id = c.cliente_id
             INNER JOIN servicios s ON s.id = c.servicio_id
             {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT c.id, c.cliente_id, c.servicio_id,
                       cli.nombre AS cliente, cli.correo, cli.telefono,
                       s.nombre AS servicio,
                       DATE(c.fecha) AS fecha,
                       TIME_FORMAT(c.hora, '%H:%i') AS hora,
                       c.estado, c.observacion_admin, c.creado_en, c.updated_at
                FROM citas c
                INNER JOIN clientes cli ON cli.id = c.cliente_id
                INNER JOIN servicios s ON s.id = c.servicio_id
                {$whereSql}
                ORDER BY c.fecha DESC, c.hora DESC, c.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ];
    }

    public function getByClienteId($clienteId) {
        $sql = "SELECT c.id,
                       DATE(c.fecha) AS fecha,
                       TIME_FORMAT(c.hora, '%H:%i') AS hora,
                       c.estado,
                       c.creado_en,
                       c.servicio_id,
                       s.nombre AS servicio_nombre,
                       s.subtitulo AS servicio_subtitulo
                FROM citas c
                INNER JOIN servicios s ON s.id = c.servicio_id
                WHERE c.cliente_id = :cliente_id
                ORDER BY c.fecha DESC, c.hora DESC, c.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cliente_id' => (int)$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ Crear nueva cita
    public function create($clienteId, $servicioId, $fecha, $hora = null, $estado = null) {
        if ($estado !== null) {
            $sql = "INSERT INTO citas (cliente_id, servicio_id, fecha, hora, estado)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$clienteId, $servicioId, $fecha, $hora, $estado]);
            return $this->pdo->lastInsertId();
        }

        $sql = "INSERT INTO citas (cliente_id, servicio_id, fecha, hora)
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clienteId, $servicioId, $fecha, $hora]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data) {
        $fields = ['fecha', 'hora', 'estado', 'servicio_id', 'observacion_admin'];
        $setParts = [];
        $params = [':id' => (int)$id];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $setParts[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($setParts)) {
            throw new Exception("No se proporcionaron campos validos para actualizar.");
        }

        $sql = "UPDATE citas SET " . implode(", ", $setParts) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updateEstadoAdmin(int $id, string $estado, ?string $motivo = null): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE citas
             SET estado = :estado,
                 observacion_admin = :observacion_admin
             WHERE id = :id"
        );
        $stmt->execute([
            ':estado' => $estado,
            ':observacion_admin' => $motivo,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function reagendarAdmin(int $id, string $fecha, string $hora, ?string $motivo = null): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE citas
             SET fecha = :fecha,
                 hora = :hora,
                 estado = 'reagendada',
                 observacion_admin = :observacion_admin
             WHERE id = :id"
        );
        $stmt->execute([
            ':fecha' => $fecha,
            ':hora' => $hora,
            ':observacion_admin' => $motivo,
            ':id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    

    public function isFirstSlotOfConfirmedGroup($clienteId, $servicioId, $fecha, $hora) {
        $group = $this->getContinuousConfirmedGroup($clienteId, $servicioId, $fecha, $hora);
        if (empty($group)) {
            return false;
        }

        return ($group[0]['hora'] ?? null) === $hora;
    }

    public function getContinuousGroupByState($clienteId, $servicioId, $fecha, $hora, string $estado) {
        $fecha = date('Y-m-d', strtotime((string)$fecha));
        $hora = date('H:i:s', strtotime((string)$hora));

        $allowed = ['solicitada', 'pendiente', 'confirmada', 'cancelada', 'completada', 'reagendada'];
        if (!in_array($estado, $allowed, true)) {
            return [];
        }

        $sql = "SELECT c.id, c.cliente_id, c.servicio_id,
                       cli.nombre AS cliente, cli.correo, cli.telefono,
                       s.nombre AS servicio, c.fecha, c.hora, c.estado
                FROM citas c
                INNER JOIN clientes cli ON cli.id = c.cliente_id
                INNER JOIN servicios s ON s.id = c.servicio_id
                WHERE c.cliente_id = :cliente_id
                  AND c.servicio_id = :servicio_id
                  AND c.fecha = :fecha
                  AND c.estado = :estado
                ORDER BY c.hora ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'cliente_id' => (int)$clienteId,
            'servicio_id' => (int)$servicioId,
            'fecha' => $fecha,
            'estado' => $estado,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return [];
        }

        $targetTs = strtotime($fecha . ' ' . $hora);
        if ($targetTs === false) {
            return [];
        }

        $targetIndex = null;
        foreach ($rows as $index => $row) {
            $rowFecha = date('Y-m-d', strtotime((string)$row['fecha']));
            $rowHora = date('H:i:s', strtotime((string)$row['hora']));
            $rowTs = strtotime($rowFecha . ' ' . $rowHora);

            if ($rowTs === $targetTs) {
                $targetIndex = $index;
                break;
            }
        }

        if ($targetIndex === null) {
            return [];
        }

        $group = [$rows[$targetIndex]];

        $left = $targetIndex - 1;
        $expectedTs = $targetTs - (30 * 60);
        while ($left >= 0) {
            $rowFecha = date('Y-m-d', strtotime((string)$rows[$left]['fecha']));
            $rowHora = date('H:i:s', strtotime((string)$rows[$left]['hora']));
            $rowTs = strtotime($rowFecha . ' ' . $rowHora);

            if ($rowTs === $expectedTs) {
                array_unshift($group, $rows[$left]);
                $expectedTs -= (30 * 60);
                $left--;
            } else {
                break;
            }
        }

        $right = $targetIndex + 1;
        $expectedTs = $targetTs + (30 * 60);
        while ($right < count($rows)) {
            $rowFecha = date('Y-m-d', strtotime((string)$rows[$right]['fecha']));
            $rowHora = date('H:i:s', strtotime((string)$rows[$right]['hora']));
            $rowTs = strtotime($rowFecha . ' ' . $rowHora);

            if ($rowTs === $expectedTs) {
                $group[] = $rows[$right];
                $expectedTs += (30 * 60);
                $right++;
            } else {
                break;
            }
        }

        return $group;
    }

    public function getContinuousConfirmedGroup($clienteId, $servicioId, $fecha, $hora) {
    return $this->getContinuousGroupByState($clienteId, $servicioId, $fecha, $hora, 'confirmada');
}

    public function getContinuousPendingGroup($clienteId, $servicioId, $fecha, $hora) {
        return $this->getContinuousGroupByState($clienteId, $servicioId, $fecha, $hora, 'pendiente');
    }
    
    public function updateStatusByIds(array $ids, string $estado) {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($ids)) {
            return false;
        }
    
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE citas SET estado = ? WHERE id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
    
        $params = array_merge([$estado], $ids);
        $stmt->execute($params);
    
        return $stmt->rowCount() > 0;
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM citas WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->rowCount() > 0;
    }
}
