<?php
class ConfigModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* ==========================================================
       🔹 Obtener configuración del sitio (solo 1 registro)
       ========================================================== */
    public function getConfig() {
        try {
            $sql = "SELECT id, whatsapp, whatsapp_message, instagram_profile_url, actualizado_en
                    FROM site_config
                    ORDER BY id ASC
                    LIMIT 1";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener configuración: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Crear configuración (si no existe)
       ========================================================== */
    public function createConfig($data) {
        try {
            $sql = "INSERT INTO site_config (whatsapp, whatsapp_message, instagram_profile_url, actualizado_en)
                    VALUES (:whatsapp, :whatsapp_message, :instagram_profile_url, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':whatsapp' => $data['whatsapp'] ?? '',
                ':whatsapp_message' => $data['whatsapp_message'] ?? '',
                ':instagram_profile_url' => $data['instagram_profile_url'] ?? '',
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al crear configuración: " . $e->getMessage());
        }
    }

    /* ==========================================================
       🔹 Actualizar configuración existente
       ========================================================== */
public function updateConfig($data) {
    try {
        // Mapeo seguro de parámetros (aunque falten en el body)
        $whatsapp = $data['whatsapp'] ?? '';
        $whatsapp_message = $data['whatsapp_message'] ?? '';
        $instagram_profile_url = $data['instagram_profile_url'] ?? '';

        $sql = "UPDATE site_config
                SET whatsapp = :whatsapp,
                    whatsapp_message = :whatsapp_message,
                    instagram_profile_url = :instagram_profile_url,
                    actualizado_en = NOW()
                WHERE id = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':whatsapp', $whatsapp);
        $stmt->bindParam(':whatsapp_message', $whatsapp_message);
        $stmt->bindParam(':instagram_profile_url', $instagram_profile_url);

        $stmt->execute();

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception("Error al actualizar configuración: " . $e->getMessage());
    }
}

    /* ==========================================================
       🔹 Verifica si existe un registro
       ========================================================== */
    public function exists() {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM site_config");
        return $stmt->fetchColumn() > 0;
    }
}
