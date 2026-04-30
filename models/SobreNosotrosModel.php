<?php
class SobreNosotrosModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Obtener registro activo
    public function getActivo() {
        $sql = "SELECT id, titulo, texto, imagen_url, activo
                FROM about_content
                WHERE activo = 1
                LIMIT 1";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear nuevo registro
    public function create($data) {
        $sql = "INSERT INTO about_content (titulo, texto, imagen_url, activo)
                VALUES (:titulo, :texto, :imagen_url, :activo)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':titulo' => $data['titulo'],
            ':texto' => $data['texto'],
            ':imagen_url' => $data['imagen_url'] ?? null,
            ':activo' => $data['activo'] ?? 1
        ]);
        return $this->pdo->lastInsertId();
    }

    // Actualizar registro existente
    public function update($id, $data) {
        $fields = [];
        $params = [];
        foreach (['titulo', 'texto', 'imagen_url', 'activo'] as $campo) {
            if (isset($data[$campo])) {
                $fields[] = "$campo = :$campo";
                $params[":$campo"] = $data[$campo];
            }
        }
        if (empty($fields)) return false;

        $params[':id'] = $id;
        $sql = "UPDATE about_content SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
