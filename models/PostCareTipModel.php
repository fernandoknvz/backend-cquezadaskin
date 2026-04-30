<?php
class PostCareTipModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ✅ Obtener todos los cuidados (activos o no)
    public function getAll() {
        $sql = "SELECT id, icono, titulo, texto, activo, orden
                FROM post_care_tip
                ORDER BY orden ASC, id ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ Obtener un cuidado por ID
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM post_care_tip WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ✅ Crear un nuevo cuidado
    public function create($data) {
        $sql = "INSERT INTO post_care_tip (icono, titulo, texto, activo, orden, actualizado_en)
                VALUES (:icono, :titulo, :texto, :activo, :orden, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    // ✅ Actualizar un cuidado existente
public function update($id, $data) {
    // Campos válidos
    $fields = ['icono', 'titulo', 'texto', 'activo', 'orden'];

    // Construir dinámicamente el SQL solo con los campos enviados
    $setParts = [];
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $setParts[] = "$field = :$field";
        }
    }

    // Si no hay campos válidos, error
    if (empty($setParts)) {
        throw new Exception("No se enviaron campos válidos para actualizar");
    }

    // Agregamos fecha de actualización
    $setParts[] = "actualizado_en = NOW()";
    $sql = "UPDATE post_care_tip SET " . implode(", ", $setParts) . " WHERE id = :id";

    $stmt = $this->pdo->prepare($sql);
    $data['id'] = $id;
    return $stmt->execute($data);
}


    // ✅ Eliminar un cuidado (físico)
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM post_care_tip WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
