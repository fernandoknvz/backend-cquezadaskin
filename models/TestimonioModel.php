<?php
class TestimonioModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "SELECT id, nombre, texto, foto_url, activo, orden 
                FROM testimonio 
                ORDER BY orden ASC, id ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM testimonio WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO testimonio (nombre, texto, foto_url, activo, orden)
                VALUES (:nombre, :texto, :foto_url, :activo, :orden)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':texto' => $data['texto'],
            ':foto_url' => $data['foto_url'],
            ':activo' => $data['activo'] ?? 1,
            ':orden' => $data['orden'] ?? 0
        ]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data) {
        $fields = [];
        $params = [];
        foreach (['nombre', 'texto', 'foto_url', 'activo', 'orden'] as $campo) {
            if (isset($data[$campo])) {
                $fields[] = "$campo = :$campo";
                $params[":$campo"] = $data[$campo];
            }
        }
        if (empty($fields)) return false;

        $params[':id'] = $id;
        $sql = "UPDATE testimonio SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM testimonio WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
