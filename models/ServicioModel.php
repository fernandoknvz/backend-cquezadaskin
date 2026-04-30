<?php
class ServicioModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /* =======================================================
       🔹 Obtener todos los servicios
       ======================================================= */
    public function getAll() {
        $stmt = $this->pdo->prepare("SELECT * FROM servicios ORDER BY orden ASC, id ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPublicActive() {
        $stmt = $this->pdo->prepare(
            "SELECT id, categoria_id, nombre, etiqueta, subtitulo, descripcion, beneficios,
                    imagen_url, precio, cta_primary_label, cta_primary_url,
                    cta_secondary_label, cta_secondary_url, orden
             FROM servicios
             WHERE activo = 1
             ORDER BY orden ASC, id ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =======================================================
       🔹 Obtener servicios por categoría
       ======================================================= */
    public function getByCategoria($categoria_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM servicios WHERE categoria_id = :categoria_id ORDER BY orden ASC, id ASC");
        $stmt->execute(['categoria_id' => $categoria_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =======================================================
       🔹 Obtener un servicio por ID
       ======================================================= */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM servicios WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* =======================================================
       🔹 Crear nuevo servicio
       ======================================================= */
    public function create($data) {
        $sql = "INSERT INTO servicios (categoria_id, nombre, etiqueta, subtitulo, descripcion, beneficios, imagen_url, precio,
                cta_primary_label, cta_primary_url, cta_secondary_label, cta_secondary_url, mostrar_servicios, mostrar_empresas,
                orden, activo)
                VALUES (:categoria_id, :nombre, :etiqueta, :subtitulo, :descripcion, :beneficios, :imagen_url, :precio,
                :cta_primary_label, :cta_primary_url, :cta_secondary_label, :cta_secondary_url, :mostrar_servicios, :mostrar_empresas,
                :orden, :activo)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':categoria_id' => $this->normalizeCategory($data['categoria_id'] ?? null),
            ':nombre' => $data['nombre'] ?? '',
            ':etiqueta' => $data['etiqueta'] ?? null,
            ':subtitulo' => $data['subtitulo'] ?? null,
            ':descripcion' => $data['descripcion'] ?? '',
            ':beneficios' => $this->normalizeBenefits($data['beneficios'] ?? null),
            ':imagen_url' => $data['imagen_url'] ?? '',
            ':precio' => isset($data['precio']) ? (float)$data['precio'] : 0,
            ':cta_primary_label' => $data['cta_primary_label'] ?? null,
            ':cta_primary_url' => $data['cta_primary_url'] ?? null,
            ':cta_secondary_label' => $data['cta_secondary_label'] ?? null,
            ':cta_secondary_url' => $data['cta_secondary_url'] ?? null,
            ':mostrar_servicios' => !empty($data['mostrar_servicios']) ? 1 : 0,
            ':mostrar_empresas' => !empty($data['mostrar_empresas']) ? 1 : 0,
            ':orden' => $data['orden'] ?? 0,
            ':activo' => isset($data['activo']) && $data['activo'] ? 1 : 0
        ]);
        return $this->pdo->lastInsertId();
    }

    /* =======================================================
       🔹 Actualizar servicio
       ======================================================= */
    public function update($id, $data) {
        // Validar existencia
        $existing = $this->getById($id);
        if (!$existing) {
            throw new Exception("Servicio no encontrado (ID $id)");
        }

        $categoriaId = array_key_exists('categoria_id', $data)
            ? $this->normalizeCategory($data['categoria_id'])
            : $existing['categoria_id'];

        // Campos actualizables
        $sql = "UPDATE servicios
                SET categoria_id = :categoria_id,
                    nombre = :nombre,
                    etiqueta = :etiqueta,
                    subtitulo = :subtitulo,
                    descripcion = :descripcion,
                    beneficios = :beneficios,
                    imagen_url = :imagen_url,
                    precio = :precio,
                    cta_primary_label = :cta_primary_label,
                    cta_primary_url = :cta_primary_url,
                    cta_secondary_label = :cta_secondary_label,
                    cta_secondary_url = :cta_secondary_url,
                    mostrar_servicios = :mostrar_servicios,
                    mostrar_empresas = :mostrar_empresas,
                    orden = :orden,
                    activo = :activo
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => (int)$id,
            ':categoria_id' => $categoriaId,
            ':nombre' => $data['nombre'] ?? $existing['nombre'],
            ':etiqueta' => $data['etiqueta'] ?? $existing['etiqueta'],
            ':subtitulo' => $data['subtitulo'] ?? $existing['subtitulo'],
            ':descripcion' => $data['descripcion'] ?? $existing['descripcion'],
            ':beneficios' => $this->normalizeBenefits($data['beneficios'] ?? $existing['beneficios']),
            ':imagen_url' => $data['imagen_url'] ?? $existing['imagen_url'],
            ':precio' => isset($data['precio']) ? (float)$data['precio'] : (float)$existing['precio'],
            ':cta_primary_label' => $data['cta_primary_label'] ?? $existing['cta_primary_label'],
            ':cta_primary_url' => $data['cta_primary_url'] ?? $existing['cta_primary_url'],
            ':cta_secondary_label' => $data['cta_secondary_label'] ?? $existing['cta_secondary_label'],
            ':cta_secondary_url' => $data['cta_secondary_url'] ?? $existing['cta_secondary_url'],
            ':mostrar_servicios' => isset($data['mostrar_servicios'])
                ? ($data['mostrar_servicios'] ? 1 : 0)
                : (int)($existing['mostrar_servicios'] ?? 0),
            ':mostrar_empresas' => isset($data['mostrar_empresas'])
                ? ($data['mostrar_empresas'] ? 1 : 0)
                : (int)($existing['mostrar_empresas'] ?? 0),
            ':orden' => $data['orden'] ?? $existing['orden'],
            ':activo' => isset($data['activo']) && $data['activo'] ? 1 : 0
        ]);

        return true;
    }

    /* =======================================================
       🔹 Eliminar servicio
       ======================================================= */
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM servicios WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return true;
    }

    private function normalizeBenefits($benefits) {
        if (is_array($benefits)) {
            return json_encode(array_values($benefits), JSON_UNESCAPED_UNICODE);
        }
        if (is_string($benefits)) {
            $trimmed = trim($benefits);
            if ($trimmed === '') {
                return null;
            }
            return $trimmed;
        }
        return null;
    }

    private function normalizeCategory($value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return null;
    }
}
