<?php
class FaqModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function publicList(): array {
        $stmt = $this->pdo->query(
            "SELECT id, pregunta, respuesta, categoria, orden
             FROM faq
             WHERE activo = 1
             ORDER BY orden ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminList(): array {
        $stmt = $this->pdo->query(
            "SELECT id, pregunta, respuesta, categoria, orden, activo, creado_en, updated_at
             FROM faq
             ORDER BY orden ASC, id ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT id, pregunta, respuesta, categoria, orden, activo, creado_en, updated_at
             FROM faq
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO faq (pregunta, respuesta, categoria, orden, activo)
             VALUES (:pregunta, :respuesta, :categoria, :orden, :activo)"
        );
        $stmt->execute([
            ':pregunta' => $data['pregunta'],
            ':respuesta' => $data['respuesta'],
            ':categoria' => $data['categoria'] ?? null,
            ':orden' => (int)($data['orden'] ?? 0),
            ':activo' => !empty($data['activo']) ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        foreach (['pregunta', 'respuesta', 'categoria'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $value = trim((string)$data[$field]);
                $params[":$field"] = $value !== '' ? $value : null;
            }
        }

        if (array_key_exists('orden', $data)) {
            $fields[] = "orden = :orden";
            $params[':orden'] = (int)$data['orden'];
        }

        if (array_key_exists('activo', $data)) {
            $fields[] = "activo = :activo";
            $params[':activo'] = filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (empty($fields)) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE faq SET " . implode(', ', $fields) . " WHERE id = :id");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE faq SET activo = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
