<?php
class ClienteModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getByCorreo($correo) {
        $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE correo = ?");
        $stmt->execute([$correo]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByRut($rut) {
        $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE rut = ?");
        $stmt->execute([$rut]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listAdmin(array $filters): array {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;
        $search = trim((string)($filters['search'] ?? ''));
        $sort = (string)($filters['sort'] ?? 'creado_en');
        $direction = strtolower((string)($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $allowedSorts = [
            'id' => 'c.id',
            'nombre' => 'c.nombre',
            'correo' => 'c.correo',
            'telefono' => 'c.telefono',
            'creado_en' => 'c.creado_en',
            'updated_at' => 'c.updated_at',
            'total_reservas' => 'total_reservas',
        ];
        $sortColumn = $allowedSorts[$sort] ?? $allowedSorts['creado_en'];

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(c.nombre LIKE :search_nombre OR c.correo LIKE :search_correo OR c.telefono LIKE :search_telefono OR c.rut LIKE :search_rut)";
            $like = '%' . $search . '%';
            $params[':search_nombre'] = $like;
            $params[':search_correo'] = $like;
            $params[':search_telefono'] = $like;
            $params[':search_rut'] = $like;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM clientes c {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT c.id, c.nombre, c.rut, c.correo, c.telefono,
                       c.acepta_politica, c.fecha_aceptacion, c.acepta_promociones,
                       c.notas_admin, c.activo, c.creado_en, c.updated_at,
                       COUNT(ci.id) AS total_reservas,
                       MAX(CONCAT(DATE(ci.fecha), ' ', COALESCE(ci.hora, '00:00:00'))) AS ultima_reserva
                FROM clientes c
                LEFT JOIN citas ci ON ci.cliente_id = c.id
                {$whereSql}
                GROUP BY c.id
                ORDER BY {$sortColumn} {$direction}, c.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => array_map([$this, 'safeAdminArray'], $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ];
    }

    public function getAdminDetail($id): ?array {
        $cliente = $this->getById($id);
        if (!$cliente) {
            return null;
        }

        $statsStmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_reservas,
                SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) AS reservas_confirmadas,
                SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) AS reservas_canceladas,
                MAX(CASE WHEN CONCAT(DATE(fecha), ' ', COALESCE(hora, '00:00:00')) <= NOW()
                    THEN CONCAT(DATE(fecha), ' ', COALESCE(hora, '00:00:00')) ELSE NULL END) AS ultima_reserva,
                MIN(CASE WHEN CONCAT(DATE(fecha), ' ', COALESCE(hora, '00:00:00')) >= NOW()
                    THEN CONCAT(DATE(fecha), ' ', COALESCE(hora, '00:00:00')) ELSE NULL END) AS proxima_reserva
             FROM citas
             WHERE cliente_id = :cliente_id"
        );
        $statsStmt->execute([':cliente_id' => (int)$id]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $historyStmt = $this->pdo->prepare(
            "SELECT ci.id, ci.cliente_id, ci.servicio_id, s.nombre AS servicio,
                    DATE(ci.fecha) AS fecha, TIME_FORMAT(ci.hora, '%H:%i') AS hora,
                    ci.estado, ci.observacion_admin, ci.creado_en, ci.updated_at
             FROM citas ci
             INNER JOIN servicios s ON s.id = ci.servicio_id
             WHERE ci.cliente_id = :cliente_id
             ORDER BY ci.fecha DESC, ci.hora DESC, ci.id DESC
             LIMIT 10"
        );
        $historyStmt->execute([':cliente_id' => (int)$id]);

        return [
            'cliente' => $this->safeAdminArray($cliente),
            'estadisticas' => [
                'total_reservas' => (int)($stats['total_reservas'] ?? 0),
                'reservas_confirmadas' => (int)($stats['reservas_confirmadas'] ?? 0),
                'reservas_canceladas' => (int)($stats['reservas_canceladas'] ?? 0),
                'ultima_reserva' => $stats['ultima_reserva'] ?? null,
                'proxima_reserva' => $stats['proxima_reserva'] ?? null,
            ],
            'reservas_recientes' => $historyStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function updateAdmin($id, array $data): bool {
        $fields = [];
        $params = [':id' => (int)$id];

        foreach (['nombre', 'correo', 'telefono', 'notas_admin'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $value = trim((string)$data[$field]);
                $params[":$field"] = $value !== '' ? $value : null;
            }
        }

        if (array_key_exists('activo', $data)) {
            $fields[] = "activo = :activo";
            $params[':activo'] = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE clientes SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function create($data) {
        $hasPassword = array_key_exists('password_hash', $data) || array_key_exists('password', $data);

        if ($hasPassword) {
            $sql = "INSERT INTO clientes (nombre, rut, correo, telefono, password_hash, acepta_politica, acepta_promociones, fecha_aceptacion)
                    VALUES (:nombre, :rut, :correo, :telefono, :password_hash, :acepta_politica, :acepta_promociones, :fecha_aceptacion)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':rut' => $data['rut'],
                ':correo' => $data['correo'],
                ':telefono' => $data['telefono'] ?? null,
                ':password_hash' => $data['password_hash'] ?? password_hash($data['password'], PASSWORD_BCRYPT),
                ':acepta_politica' => !empty($data['acepta_politica']) ? 1 : 0,
                ':acepta_promociones' => !empty($data['acepta_promociones']) ? 1 : 0,
                ':fecha_aceptacion' => $data['fecha_aceptacion'] ?? null,
            ]);
            return $this->pdo->lastInsertId();
        }

        $sql = "INSERT INTO clientes (nombre, correo, telefono)
                VALUES (:nombre, :correo, :telefono)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function updateProfile($id, array $data) {
        $fields = [];
        $params = [':id' => (int)$id];

        foreach (['nombre', 'rut', 'correo', 'telefono', 'acepta_promociones'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE clientes SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function updatePrivacyAcceptance($id, bool $accepted, ?string $acceptedAt = null) {
        $stmt = $this->pdo->prepare(
            "UPDATE clientes
             SET acepta_politica = :acepta_politica,
                 fecha_aceptacion = :fecha_aceptacion
             WHERE id = :id"
        );
        $stmt->execute([
            ':acepta_politica' => $accepted ? 1 : 0,
            ':fecha_aceptacion' => $accepted ? ($acceptedAt ?? date('Y-m-d H:i:s')) : null,
            ':id' => (int)$id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function updatePasswordHash($id, string $hash) {
        $stmt = $this->pdo->prepare("UPDATE clientes SET password_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $hash, ':id' => (int)$id]);
        return $stmt->rowCount() > 0;
    }

    public function anonymize($id) {
        $anonEmail = 'cliente_anon_' . (int)$id . '_' . bin2hex(random_bytes(4)) . '@anon.local';
        $stmt = $this->pdo->prepare(
            "UPDATE clientes
             SET nombre = :nombre,
                 correo = :correo,
                 rut = NULL,
                 telefono = NULL,
                 password_hash = NULL,
                 acepta_politica = 0,
                 fecha_aceptacion = NULL
             WHERE id = :id"
        );
        $stmt->execute([
            ':nombre' => 'Cliente anonimizado',
            ':correo' => $anonEmail,
            ':id' => (int)$id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function safeArray(array $cliente) {
        return [
            'id' => (int)$cliente['id'],
            'nombre' => $cliente['nombre'],
            'rut' => $cliente['rut'] ?? null,
            'correo' => $cliente['correo'],
            'telefono' => $cliente['telefono'] ?? null,
            'acepta_politica' => (bool)($cliente['acepta_politica'] ?? false),
            'fecha_aceptacion' => $cliente['fecha_aceptacion'] ?? null,
            'acepta_promociones' => (bool)($cliente['acepta_promociones'] ?? false),
            'creado_en' => $cliente['creado_en'] ?? null,
            'updated_at' => $cliente['updated_at'] ?? null,
        ];
    }

    public function safeAdminArray(array $cliente): array {
        return [
            'id' => (int)$cliente['id'],
            'nombre' => $cliente['nombre'] ?? null,
            'rut' => $cliente['rut'] ?? null,
            'correo' => $cliente['correo'] ?? null,
            'telefono' => $cliente['telefono'] ?? null,
            'acepta_politica' => (bool)($cliente['acepta_politica'] ?? false),
            'fecha_aceptacion' => $cliente['fecha_aceptacion'] ?? null,
            'acepta_promociones' => (bool)($cliente['acepta_promociones'] ?? false),
            'notas_admin' => $cliente['notas_admin'] ?? null,
            'activo' => (bool)($cliente['activo'] ?? true),
            'creado_en' => $cliente['creado_en'] ?? null,
            'updated_at' => $cliente['updated_at'] ?? null,
            'total_reservas' => isset($cliente['total_reservas']) ? (int)$cliente['total_reservas'] : null,
            'ultima_reserva' => $cliente['ultima_reserva'] ?? null,
        ];
    }
}
