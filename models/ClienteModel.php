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

    public function create($data) {
        $hasPassword = array_key_exists('password_hash', $data) || array_key_exists('password', $data);

        if ($hasPassword) {
            $sql = "INSERT INTO clientes (nombre, rut, correo, telefono, password_hash, acepta_politica, fecha_aceptacion)
                    VALUES (:nombre, :rut, :correo, :telefono, :password_hash, :acepta_politica, :fecha_aceptacion)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $data['nombre'],
                ':rut' => $data['rut'],
                ':correo' => $data['correo'],
                ':telefono' => $data['telefono'] ?? null,
                ':password_hash' => $data['password_hash'] ?? password_hash($data['password'], PASSWORD_BCRYPT),
                ':acepta_politica' => !empty($data['acepta_politica']) ? 1 : 0,
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

        foreach (['nombre', 'rut', 'telefono'] as $field) {
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
            'creado_en' => $cliente['creado_en'] ?? null,
        ];
    }
}
