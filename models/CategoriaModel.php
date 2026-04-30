<?php
class CategoriaModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $sql = "SELECT id, nombre, descripcion, imagen_url, activo, orden
                FROM categorias_servicio
                ORDER BY orden ASC, id ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM categorias_servicio WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO categorias_servicio (nombre, descripcion, imagen_url, activo, orden)
                VALUES (:nombre, :descripcion, :imagen_url, :activo, :orden)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE categorias_servicio
                SET nombre = :nombre, descripcion = :descripcion, imagen_url = :imagen_url,
                    activo = :activo, orden = :orden
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $data['id'] = $id;
        return $stmt->execute($data);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM categorias_servicio WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
