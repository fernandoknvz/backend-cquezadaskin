<?php
class HomeContentModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        try {
            $sql = "SELECT id, titulo, subtitulo, imagen_url, video_embed, actualizado_en
                    FROM home_content
                    ORDER BY id ASC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener el contenido del home: " . $e->getMessage());
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM home_content WHERE id = ?");
            $stmt->execute([(int)$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new Exception("No se encontr\u00f3 el contenido con ID $id");
            }
            return $result;
        } catch (PDOException $e) {
            throw new Exception("Error al obtener el contenido: " . $e->getMessage());
        }
    }

    public function create($data) {
        try {
            $sql = "INSERT INTO home_content (titulo, subtitulo, imagen_url, video_embed, actualizado_en)
                    VALUES (:titulo, :subtitulo, :imagen_url, :video_embed, NOW())";
            $stmt = $this->pdo->prepare($sql);

            $params = [
                ':titulo' => trim($data['titulo'] ?? ''),
                ':subtitulo' => trim($data['subtitulo'] ?? ''),
                ':imagen_url' => trim($data['imagen_url'] ?? ''),
                ':video_embed' => trim($data['video_embed'] ?? ''),
            ];

            if (empty($params[':titulo']) || empty($params[':imagen_url'])) {
                throw new Exception("Titulo e imagen son obligatorios");
            }

            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al crear el contenido: " . $e->getMessage());
        }
    }

    public function update($id, $data) {
        try {
            $fields = ['titulo', 'subtitulo', 'imagen_url', 'video_embed'];
            $setParts = [];
            $params = [':id' => (int)$id];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $setParts[] = "$field = :$field";
                    $params[":$field"] = trim((string)$data[$field]);
                }
            }

            if (empty($setParts)) {
                throw new Exception("No se proporcionaron campos v\u00e1lidos para actualizar.");
            }

            $sql = "UPDATE home_content
                    SET " . implode(", ", $setParts) . ", actualizado_en = NOW()
                    WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar el contenido: " . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM home_content WHERE id = ?");
            $stmt->execute([(int)$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al eliminar el contenido: " . $e->getMessage());
        }
    }
}
?>
