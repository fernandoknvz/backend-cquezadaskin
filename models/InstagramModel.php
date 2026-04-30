<?php
class InstagramModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* ==========================================================
       🔹 Obtener todos los posts (activos o no) - ADMIN
       ========================================================== */
    public function getAll() {
        try {
            $sql = "SELECT id, embed_url, activo, orden, actualizado_en
                    FROM instagram_post
                    ORDER BY orden ASC, id ASC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener los posts: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Obtener solo posts activos - PUBLIC
       ========================================================== */
    public function getActive() {
        try {
            $sql = "SELECT id, embed_url, activo, orden, actualizado_en
                    FROM instagram_post
                    WHERE activo = 1
                    ORDER BY orden ASC, id ASC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener posts activos: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Obtener un post por ID - ADMIN
       ========================================================== */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM instagram_post WHERE id = ?");
            $stmt->execute([(int)$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new Exception("No se encontró el post con ID $id");
            }
            return $result;
        } catch (PDOException $e) {
            throw new Exception("Error al obtener el post: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Obtener un post activo por ID - PUBLIC
       ========================================================== */
    public function getByIdActive($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM instagram_post WHERE id = ? AND activo = 1");
            $stmt->execute([(int)$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                throw new Exception("No se encontró el post activo con ID $id");
            }
            return $result;
        } catch (PDOException $e) {
            throw new Exception("Error al obtener el post activo: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Crear un nuevo post - ADMIN
       ========================================================== */
    public function create($data) {
        try {
            $sql = "INSERT INTO instagram_post (embed_url, activo, orden, actualizado_en)
                    VALUES (:embed_url, :activo, :orden, NOW())";
            $stmt = $this->pdo->prepare($sql);

            $params = [
                ':embed_url' => trim($data['embed_url'] ?? ''),
                ':activo'    => !empty($data['activo']) ? 1 : 0,
                ':orden'     => isset($data['orden']) ? (int)$data['orden'] : 0,
            ];

            if (empty($params[':embed_url'])) {
                throw new Exception("La URL del post no puede estar vacía");
            }

            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al crear el post: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Actualizar un post existente - ADMIN
       ========================================================== */
    public function update($id, $data) {
        try {
            $fields = ['embed_url', 'activo', 'orden'];
            $setParts = [];
            $params = [':id' => (int)$id];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $setParts[] = "$field = :$field";
                    if ($field === 'activo') {
                        $params[":$field"] = !empty($data[$field]) ? 1 : 0;
                    } elseif ($field === 'orden') {
                        $params[":$field"] = (int)$data[$field];
                    } else {
                        $params[":$field"] = trim($data[$field]);
                    }
                }
            }

            if (empty($setParts)) {
                throw new Exception("No se proporcionaron campos válidos para actualizar.");
            }

            $sql = "UPDATE instagram_post
                    SET " . implode(", ", $setParts) . ", actualizado_en = NOW()
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar el post: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Eliminar un post (físico) - ADMIN
       ========================================================== */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM instagram_post WHERE id = ?");
            $stmt->execute([(int)$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error al eliminar el post: " . $e->getMessage());
        }
    }
}
?>