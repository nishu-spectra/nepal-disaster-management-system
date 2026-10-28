<?php
declare(strict_types=1);

function login_is_throttled(string $email): bool {
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_key = ? AND attempted_at >= ? AND success = 0');
    $stmt->execute([hash('sha256', strtolower(trim($email))), $since]);
    return (int)$stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}
function record_login_attempt(string $email, bool $success): void {
    $stmt = db()->prepare('INSERT INTO login_attempts (email_key, success, ip_hash, attempted_at) VALUES (?, ?, ?, NOW())');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $stmt->execute([
        hash('sha256', strtolower(trim($email))),
        $success ? 1 : 0,
        hash('sha256', $ip . 'ndms-local-salt')
    ]);
}
function attempt_login(string $email, string $password): bool {
    if (login_is_throttled($email)) return false;
    $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    $valid = $user && $user['status'] === 'active' && password_verify($password, $user['password_hash']);
    record_login_attempt($email, (bool)$valid);
    if (!$valid) {
        log_security_event('login_failed', $user ? (int)$user['id'] : null);
        return false;
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    unset($user['password_hash'], $user['status']);
    safe_redirect_after_login($user);
}
