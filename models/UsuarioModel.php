<?php
class UsuarioModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Obtener usuario por username
    public function getByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios_admin WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener usuario por email
    public function getByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios_admin WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios_admin WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear nuevo usuario administrativo
    public function create($data) {
        $sql = "INSERT INTO usuarios_admin (username, email, password_hash, rol)
                VALUES (:username, :email, :password_hash, :rol)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'] ?? null,
            ':password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            ':rol' => $data['rol'] ?? 'admin'
        ]);
        return $this->pdo->lastInsertId();
    }

    public function updateAccount($id, $data) {
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
        $stmt = $this->pdo->prepare("UPDATE usuarios_admin SET email = :email WHERE id = :id");
        $stmt->execute([':email' => $email, ':id' => (int)$id]);
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
