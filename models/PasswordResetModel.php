<?php
class PasswordResetModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($userId, $tokenHash, $expiresAt) {
        $sql = "INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (:user_id, :token_hash, :expires_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => (int)$userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);
        return $this->pdo->lastInsertId();
    }

    public function findValid($tokenHash) {
        $sql = "SELECT * FROM password_resets
                WHERE token_hash = :token_hash
                  AND used_at IS NULL
                  AND expires_at >= NOW()
                ORDER BY id DESC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':token_hash' => $tokenHash]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markUsed($id) {
        $stmt = $this->pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => (int)$id]);
        return true;
    }
}
