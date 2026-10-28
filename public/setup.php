<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$installed = false;
try {
    $count = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    $installed = $count > 0;
} catch (Throwable $e) {
    http_response_code(503);
    exit('Database is not ready. Create the ndms database and import database/schema.sql first.');
}
$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if ($installed) {
    http_response_code(403);
    exit('Initial administrator already exists. Delete public/setup.php now.');
}
if (SETUP_KEY === 'CHANGE-ME-SETUP-KEY' || !hash_equals(SETUP_KEY, $key)) {
    http_response_code(403);
    exit('Setup key is not configured or is invalid. Configure NDMS_SETUP_KEY and open this page with the key.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (mb_strlen($name)<2 || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<14 || strlen($password)>200) {
        $error = 'Enter a valid name and email. Administrator password must be at least 14 characters.';
    } else {
        try {
            $stmt = db()->prepare("INSERT INTO users (full_name,email,password_hash,role,status) VALUES (?,?,?,'admin','active')");
            $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            log_security_event('initial_admin_created',(int)db()->lastInsertId());
            echo '<!doctype html><html><meta charset="utf-8"><body style="font:16px system-ui;max-width:680px;margin:4rem auto;padding:1rem"><h1>Administrator created</h1><p>Delete <code>public/setup.php</code> now, remove the setup key from configuration, then log in.</p><p><a href="'.e(url('login')).'">Go to login</a></p></body></html>';
            exit;
        } catch (Throwable $e) {
            error_log('[NDMS] Setup failed: '.$e->getMessage());
            $error = 'Could not create the administrator. Check server logs and ensure the schema is imported.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Initial administrator setup</title><link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>"></head><body><main class="container"><div class="form-wrap"><div class="form-card"><div class="eyebrow">One-time installation</div><h1>Create first administrator</h1><p class="muted">This page is intended for local installation only. Delete it immediately after use.</p><?php if($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?><form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($key) ?>"><div class="field"><label>Full name</label><input name="full_name" required minlength="2" maxlength="120"></div><div class="field"><label>Email</label><input name="email" type="email" required maxlength="190"></div><div class="field"><label for="setup_password">Password (14+ characters)</label><div class="password-wrap"><input id="setup_password" name="password" type="password" required minlength="14" maxlength="200" autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="setup_password" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div><button class="btn btn-accent">Create administrator</button></form></div></div></main><script src="<?= e(url('assets/app.js')) ?>"></script></body></html>
