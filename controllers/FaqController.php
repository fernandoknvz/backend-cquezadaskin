<?php
require_once __DIR__ . '/../models/FaqModel.php';
require_once __DIR__ . '/../utils/Response.php';

class FaqController {
    private FaqModel $model;

    public function __construct($pdo) {
        $this->model = new FaqModel($pdo);
    }

    public function publicIndex(): void {
        Response::json([
            'success' => true,
            'data' => $this->model->publicList(),
        ]);
    }

    public function adminIndex(): void {
        Response::json([
            'success' => true,
            'data' => $this->model->adminList(),
        ]);
    }

    public function adminShow(int $id): void {
        $faq = $this->model->getById($id);
        if (!$faq) {
            Response::error("FAQ no encontrada", 404);
            return;
        }
        Response::json(['success' => true, 'data' => $faq]);
    }

    public function adminCreate(array $body): void {
        $data = $this->validate($body, true);
        if (isset($data['error'])) {
            Response::error($data['error'], 400);
            return;
        }

        $id = $this->model->create($data);
        Response::json([
            'success' => true,
            'message' => 'FAQ creada',
            'data' => $this->model->getById($id),
        ], 201);
    }

    public function adminUpdate(int $id, array $body): void {
        if (!$this->model->getById($id)) {
            Response::error("FAQ no encontrada", 404);
            return;
        }

        $data = $this->validate($body, false);
        if (isset($data['error'])) {
            Response::error($data['error'], 400);
            return;
        }

        if (empty($data)) {
            Response::error("No hay campos válidos para actualizar", 400);
            return;
        }

        $this->model->update($id, $data);
        Response::json([
            'success' => true,
            'message' => 'FAQ actualizada',
            'data' => $this->model->getById($id),
        ]);
    }

    public function adminDelete(int $id): void {
        if (!$this->model->getById($id)) {
            Response::error("FAQ no encontrada", 404);
            return;
        }
        $this->model->deactivate($id);
        Response::json(['success' => true, 'message' => 'FAQ desactivada']);
    }

    private function validate(array $body, bool $requireAll): array {
        $data = [];

        if ($requireAll || array_key_exists('pregunta', $body)) {
            $pregunta = trim((string)($body['pregunta'] ?? ''));
            if ($pregunta === '') {
                return ['error' => 'Pregunta requerida'];
            }
            $data['pregunta'] = $pregunta;
        }

        if ($requireAll || array_key_exists('respuesta', $body)) {
            $respuesta = trim((string)($body['respuesta'] ?? ''));
            if ($respuesta === '') {
                return ['error' => 'Respuesta requerida'];
            }
            $data['respuesta'] = $respuesta;
        }

        if (array_key_exists('categoria', $body)) {
            $categoria = trim((string)$body['categoria']);
            $data['categoria'] = $categoria !== '' ? $categoria : null;
        }

        if (array_key_exists('orden', $body)) {
            if (!is_numeric($body['orden'])) {
                return ['error' => 'Orden inválido'];
            }
            $data['orden'] = (int)$body['orden'];
        } elseif ($requireAll) {
            $data['orden'] = 0;
        }

        if (array_key_exists('activo', $body)) {
            $data['activo'] = filter_var($body['activo'], FILTER_VALIDATE_BOOLEAN);
        } elseif ($requireAll) {
            $data['activo'] = true;
        }

        return $data;
    }
}
