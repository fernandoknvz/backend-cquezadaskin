<?php
class TestimonioModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "SELECT id,
                       COALESCE(nombre_mostrado, nombre) AS nombre,
                       COALESCE(nombre_mostrado, nombre) AS nombre_cliente,
                       COALESCE(nombre_mostrado, nombre) AS cliente,
                       COALESCE(comentario, texto) AS texto,
                       COALESCE(comentario, texto) AS comentario,
                       foto_url,
                       puntuacion,
                       puntuacion AS rating,
                       respuesta_admin,
                       creado_en AS fecha,
                       creado_en AS created_at,
                       visible,
                       visible AS publicada,
                       estado,
                       orden
                FROM testimonio
                WHERE estado IN ('aprobado', 'aprobada')
                  AND visible = 1
                  AND activo = 1
                ORDER BY orden ASC, id ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM testimonio WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPublicById($id) {
        $sql = "SELECT id,
                       COALESCE(nombre_mostrado, nombre) AS nombre,
                       COALESCE(nombre_mostrado, nombre) AS nombre_cliente,
                       COALESCE(nombre_mostrado, nombre) AS cliente,
                       COALESCE(comentario, texto) AS texto,
                       COALESCE(comentario, texto) AS comentario,
                       foto_url,
                       puntuacion,
                       puntuacion AS rating,
                       respuesta_admin,
                       creado_en AS fecha,
                       creado_en AS created_at,
                       visible,
                       visible AS publicada,
                       estado,
                       orden
                FROM testimonio
                WHERE id = :id
                  AND estado IN ('aprobado', 'aprobada')
                  AND visible = 1
                  AND activo = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO testimonio
                    (nombre, texto, nombre_mostrado, comentario, foto_url, puntuacion, estado, visible, activo, orden)
                VALUES
                    (:nombre, :texto, :nombre_mostrado, :comentario, :foto_url, :puntuacion, :estado, :visible, :activo, :orden)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':texto' => $data['texto'],
            ':nombre_mostrado' => $data['nombre_mostrado'] ?? $data['nombre'],
            ':comentario' => $data['comentario'] ?? $data['texto'],
            ':foto_url' => $data['foto_url'] ?? null,
            ':puntuacion' => $data['puntuacion'] ?? 5,
            ':estado' => $data['estado'] ?? 'aprobado',
            ':visible' => !empty($data['visible']) ? 1 : 0,
            ':activo' => array_key_exists('activo', $data) ? (int)(bool)$data['activo'] : 1,
            ':orden' => $data['orden'] ?? 0,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function createClienteValoracion(int $clienteId, array $data): int {
        $sql = "INSERT INTO testimonio
                    (cliente_id, cita_id, nombre_mostrado, comentario, puntuacion, estado, visible, activo, orden)
                VALUES
                    (:cliente_id, :cita_id, :nombre_mostrado, :comentario, :puntuacion, 'pendiente', 0, 1, 0)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cliente_id' => $clienteId,
            ':cita_id' => $data['cita_id'] ?? null,
            ':nombre_mostrado' => $data['nombre_mostrado'],
            ':comentario' => $data['comentario'],
            ':puntuacion' => (int)$data['puntuacion'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listByCliente(int $clienteId): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, cita_id, nombre_mostrado, comentario, puntuacion, estado, visible, respuesta_admin, creado_en, updated_at
             FROM testimonio
             WHERE cliente_id = :cliente_id
             ORDER BY creado_en DESC, id DESC"
        );
        $stmt->execute([':cliente_id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAdmin(): array {
        $stmt = $this->pdo->query(
            "SELECT t.id, t.cliente_id, t.cita_id, t.nombre_mostrado, t.comentario,
                    t.puntuacion, t.estado, t.visible, t.respuesta_admin,
                    t.activo, t.orden, t.creado_en, t.updated_at,
                    c.nombre AS cliente_nombre
             FROM testimonio t
             LEFT JOIN clientes c ON c.id = t.cliente_id
             ORDER BY t.creado_en DESC, t.id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => (int)$id];
        $map = [
            'nombre' => 'nombre',
            'texto' => 'texto',
            'foto_url' => 'foto_url',
            'activo' => 'activo',
            'orden' => 'orden',
            'nombre_mostrado' => 'nombre_mostrado',
            'comentario' => 'comentario',
            'puntuacion' => 'puntuacion',
            'estado' => 'estado',
            'visible' => 'visible',
            'respuesta_admin' => 'respuesta_admin',
        ];

        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $fields[] = "$column = :$input";
                $params[":$input"] = $data[$input];
            }
        }

        if (array_key_exists('nombre_mostrado', $data)) {
            $fields[] = "nombre = :legacy_nombre";
            $params[':legacy_nombre'] = $data['nombre_mostrado'];
        }
        if (array_key_exists('comentario', $data)) {
            $fields[] = "texto = :legacy_texto";
            $params[':legacy_texto'] = $data['comentario'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE testimonio SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function softDelete($id): bool {
        $stmt = $this->pdo->prepare("UPDATE testimonio SET activo = 0, visible = 0 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    public function delete($id) {
        return $this->softDelete($id);
    }
}
