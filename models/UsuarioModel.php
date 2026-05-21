<?php
class UsuarioModel {
    private $pdo;
    private $hasEmailColumn = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function hasEmailColumn() {
        if ($this->hasEmailColumn !== null) {
            return $this->hasEmailColumn;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'usuarios_admin'
              AND COLUMN_NAME = 'email'
        ");
        $stmt->execute();
        $this->hasEmailColumn = ((int)$stmt->fetchColumn()) > 0;

        return $this->hasEmailColumn;
    }

    // Obtener usuario por username
    public function getByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios_admin WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener usuario por email
    public function getByEmail($email) {
        if (!$this->hasEmailColumn()) {
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM usuarios_admin WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByIdentifier($identifier) {
        $identifier = trim((string)$identifier);

        if ($identifier === '') {
            return false;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $this->getByEmail($identifier);
            if ($user) {
                return $user;
            }
        }

        return $this->getByUsername($identifier);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios_admin WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear nuevo usuario administrativo
    public function create($data) {
        if ($this->hasEmailColumn()) {
            $sql = "INSERT INTO usuarios_admin (username, email, password_hash, rol)
                    VALUES (:username, :email, :password_hash, :rol)";
            $params = [
                ':username' => $data['username'],
                ':email' => $data['email'] ?? null,
                ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':rol' => $data['rol'] ?? 'admin'
            ];
        } else {
            $sql = "INSERT INTO usuarios_admin (username, password_hash, rol)
                    VALUES (:username, :password_hash, :rol)";
            $params = [
                ':username' => $data['username'],
                ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':rol' => $data['rol'] ?? 'admin'
            ];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    public function updateAccount($id, $data) {
        if (!$this->hasEmailColumn()) {
            $sql = "UPDATE usuarios_admin
                    SET password_hash = :password_hash
                    WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => (int)$id,
                ':password_hash' => $data['password_hash']
            ]);
            return true;
        }

        $sql = "UPDATE usuarios_admin
            SET email = :email,
                password_hash = :password_hash
            WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => (int)$id,
            ':email' => $data['email'] ?? null,
            ':password_hash' => $data['password_hash']
        ]);
        return true;
    }

    public function updateEmail($id, $email) {
        if (!$this->hasEmailColumn()) {
            return false;
        }

        $stmt = $this->pdo->prepare("UPDATE usuarios_admin SET email = :email WHERE id = :id");
        $stmt->execute([':email' => $email, ':id' => (int)$id]);
        return true;
    }

    public function updateUsername($id, $username) {
        $stmt = $this->pdo->prepare("UPDATE usuarios_admin SET username = :username WHERE id = :id");
        $stmt->execute([':username' => $username, ':id' => (int)$id]);
        return true;
    }

    public function updatePassword($id, $password) {
        $stmt = $this->pdo->prepare("UPDATE usuarios_admin SET password_hash = :hash WHERE id = :id");
        $stmt->execute([
            ':hash' => password_hash($password, PASSWORD_BCRYPT),
            ':id' => (int)$id
        ]);
        return true;
    }
}
