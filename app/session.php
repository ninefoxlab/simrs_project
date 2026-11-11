<?php
// Penyimpanan session di tabel simrs_sessions.
// Dipakai di serverless (Vercel): tiap request bisa jalan di instance berbeda,
// sehingga session berbasis file akan hilang dan pengguna ter-logout.
class SimrsDbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $pdo, private int $lifetime = 28800) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $q = $this->pdo->prepare('SELECT data FROM simrs_sessions WHERE id = ? AND last_activity > ?');
        $q->execute([$id, time() - $this->lifetime]);
        $data = $q->fetchColumn();
        return $data === false ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        $q = $this->pdo->prepare('INSERT INTO simrs_sessions (id, data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)');
        return $q->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        return $this->pdo->prepare('DELETE FROM simrs_sessions WHERE id = ?')->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $q = $this->pdo->prepare('DELETE FROM simrs_sessions WHERE last_activity < ?');
        $q->execute([time() - $this->lifetime]);
        return $q->rowCount();
    }

    public function validateId(string $id): bool
    {
        $q = $this->pdo->prepare('SELECT 1 FROM simrs_sessions WHERE id = ? AND last_activity > ?');
        $q->execute([$id, time() - $this->lifetime]);
        return (bool)$q->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->pdo->prepare('UPDATE simrs_sessions SET last_activity = ? WHERE id = ?')->execute([time(), $id]);
    }
}
