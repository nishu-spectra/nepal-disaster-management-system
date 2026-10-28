<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

$action = trim((string)($_GET['page'] ?? 'home'));
$allowedPages = ['home','reports','map','login','register','logout','dashboard','report','help','my-reports','my-requests','operations','admin'];
if (!in_array($action, $allowedPages, true)) $action = 'home';

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('[NDMS] Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    echo '<!doctype html><html><meta charset="utf-8"><title>Setup required</title><body style="font-family:system-ui;padding:3rem"><h1>Database connection unavailable</h1><p>Import database/schema.sql and check app/config.php. Detailed errors are stored in the server log.</p></body></html>';
    exit;
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en','ne'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    $return = (string)($_GET['return'] ?? 'home');
    if (!in_array($return, $allowedPages, true)) $return = 'home';
    redirect($return);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ($action === 'register') {
            $name = trim((string)($_POST['full_name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $role = (string)($_POST['role'] ?? 'citizen');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($password) < 12 || strlen($password) > 200 || $password !== $confirm
                || !in_array($role, ['citizen','responder','organization'], true)) {
                flash('error', 'Please check your name, email, account type, and password (minimum 12 characters; confirmation must match).');
                redirect('register');
            }
            $stmt = $pdo->prepare('INSERT INTO users (full_name,email,phone,password_hash,role,status) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$name,$email,$phone !== '' ? $phone : null,password_hash($password, PASSWORD_DEFAULT),$role,'active']);
            log_security_event('account_registered', (int)$pdo->lastInsertId(), ['role'=>$role]);
            flash('success', t('account_created'));
            redirect('login');
        }

        if ($action === 'login') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 200) {
                flash('error', t('invalid_login'));
                redirect('login');
            }
            if (login_is_throttled($email)) {
                flash('error', t('too_many'));
                redirect('login');
            }
            $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $valid = $user && $user['status'] === 'active' && password_verify($password, $user['password_hash']);
            record_login_attempt($email, (bool)$valid);
            if (!$valid) {
                log_security_event('login_failed', $user ? (int)$user['id'] : null);
                flash('error', t('invalid_login'));
                redirect('login');
            }
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT),$user['id']]);
            }
            unset($user['password_hash'], $user['status']);
            safe_redirect_after_login($user);
        }

        if ($action === 'report') {
            $user = require_login();
            $title = trim((string)($_POST['title'] ?? ''));
            $type = (string)($_POST['disaster_type'] ?? '');
            $severity = (string)($_POST['severity'] ?? 'medium');
            $province = trim((string)($_POST['province'] ?? ''));
            $district = trim((string)($_POST['district'] ?? ''));
            $municipality = trim((string)($_POST['municipality'] ?? ''));
            $ward = trim((string)($_POST['ward'] ?? ''));
            $address = trim((string)($_POST['address_details'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $validTypes = ['flood','landslide','earthquake','fire','storm','other'];
            $validSeverities = ['low','medium','high','critical'];
            $latRaw = trim((string)($_POST['latitude'] ?? ''));
            $lngRaw = trim((string)($_POST['longitude'] ?? ''));
            $lat = $latRaw === '' ? null : filter_var($latRaw, FILTER_VALIDATE_FLOAT);
            $lng = $lngRaw === '' ? null : filter_var($lngRaw, FILTER_VALIDATE_FLOAT);
            if (mb_strlen($title) < 4 || mb_strlen($title) > 160 || !in_array($type,$validTypes,true)
                || !in_array($severity,$validSeverities,true) || $province === '' || $district === ''
                || $municipality === '' || mb_strlen($description) < 10 || mb_strlen($description) > 5000
                || ($latRaw !== '' && ($lat === false || $lat < 26 || $lat > 31.5))
                || ($lngRaw !== '' && ($lng === false || $lng < 80 || $lng > 89))) {
                flash('error', t('required') . ' Check coordinates are within Nepal if entered.');
                redirect('report');
            }
            $stmt = $pdo->prepare('INSERT INTO incidents (user_id,title,disaster_type,severity,province,district,municipality,ward,address_details,latitude,longitude,description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([(int)$user['id'],$title,$type,$severity,$province,$district,$municipality,$ward ?: null,$address ?: null,$lat,$lng,$description]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)')->execute([(int)$user['id'],'incident_created','incident',$id,json_encode(['status'=>'open'])]);
            flash('success', t('report_created'));
            redirect('my-reports');
        }

        if ($action === 'help') {
            $user = require_login();
            $type = (string)($_POST['assistance_type'] ?? '');
            $people = filter_var($_POST['people_affected'] ?? 1, FILTER_VALIDATE_INT);
            $province = trim((string)($_POST['province'] ?? ''));
            $district = trim((string)($_POST['district'] ?? ''));
            $municipality = trim((string)($_POST['municipality'] ?? ''));
            $ward = trim((string)($_POST['ward'] ?? ''));
            $details = trim((string)($_POST['details'] ?? ''));
            if (!in_array($type,['rescue','medical','shelter','food_water','supplies','other'],true)
                || $people === false || $people < 1 || $people > 10000 || $province === '' || $district === ''
                || $municipality === '' || mb_strlen($details) < 10 || mb_strlen($details) > 5000) {
                flash('error', t('required'));
                redirect('help');
            }
            $stmt = $pdo->prepare('INSERT INTO assistance_requests (user_id,assistance_type,people_affected,province,district,municipality,ward,details) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([(int)$user['id'],$type,$people,$province,$district,$municipality,$ward ?: null,$details]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)')->execute([(int)$user['id'],'assistance_created','assistance_request',$id,json_encode(['status'=>'open'])]);
            flash('success', t('help_created'));
            redirect('my-requests');
        }

        if ($action === 'admin' && isset($_POST['incident_id'], $_POST['new_status'])) {
            $actor = require_role(['admin','officer']);
            $id = filter_var($_POST['incident_id'], FILTER_VALIDATE_INT);
            $status = (string)$_POST['new_status'];
            $allowedStatuses = ['under_review','confirmed','assigned','in_progress','resolved','rejected'];
            if (!$id || !in_array($status,$allowedStatuses,true)) {
                flash('error', t('error')); redirect('admin');
            }
            $stmt = $pdo->prepare('UPDATE incidents SET status=?, verified_by=?, verified_at=IF(?="confirmed",NOW(),verified_at) WHERE id=?');
            $stmt->execute([$status,(int)$actor['id'],$status,$id]);
            $pdo->prepare('INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,details) VALUES (?,?,?,?,?)')->execute([(int)$actor['id'],'incident_status_updated','incident',$id,json_encode(['status'=>$status])]);
            log_security_event('incident_status_updated',(int)$actor['id'],['incident_id'=>$id,'status'=>$status]);
            flash('success', t('save'));
            redirect('admin');
        }
    } catch (PDOException $e) {
        error_log('[NDMS] Database operation failed: ' . $e->getMessage());
        flash('error', t('error'));
        redirect($action);
    } catch (Throwable $e) {
        error_log('[NDMS] Request failed: ' . $e->getMessage());
        flash('error', t('error'));
        redirect($action);
    }
}

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Use the logout form.'); }
    verify_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $params['path'], '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . url());
    exit;
}

$user = current_user();
$flash = get_flash();
$publicReports = [];
$stats = ['total'=>0,'confirmed'=>0,'open_help'=>0];
try {
    $publicReports = $pdo->query("SELECT i.id,i.title,i.disaster_type,i.severity,i.province,i.district,i.municipality,i.status,i.created_at,i.latitude,i.longitude FROM incidents i WHERE i.status IN ('confirmed','resolved') ORDER BY i.created_at DESC LIMIT 6")->fetchAll();
    $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status <> 'rejected'")->fetchColumn();
    $stats['confirmed'] = (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE status='confirmed'")->fetchColumn();
    $stats['open_help'] = (int)$pdo->query("SELECT COUNT(*) FROM assistance_requests WHERE status IN ('open','under_review','assigned','in_progress')")->fetchColumn();
} catch (Throwable $e) { error_log('[NDMS] Dashboard metrics failed: ' . $e->getMessage()); }

function render_header(string $title): void {
    global $user, $flash, $action;
    ?><!doctype html>
<html lang="<?= e(lang()==='ne'?'ne':'en') ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Nepal Disaster Management System — disaster reporting, assistance coordination and preparedness information.">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/app.css')) ?>">
<?php if (($action ?? '') === 'map'): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<?php endif; ?>
</head><body>
<div class="topbar"><div class="topbar-inner"><span>🇳🇵 Nepal · Preparedness · Response · Recovery</span><span><?= e(t('public_notice')) ?></span></div></div>
<nav class="nav"><div class="nav-inner">
<a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">⌂</span><span>NDMS <small>Nepal Disaster Management</small></span></a>
<div class="navlinks">
<a href="<?= e(url()) ?>"><?= e(t('home')) ?></a>
<a href="<?= e(url('reports')) ?>"><?= e(t('alerts')) ?></a>
<a href="<?= e(url('map')) ?>"><?= e(t('map')) ?></a>
<?php if ($user): ?><a href="<?= e(url('dashboard')) ?>"><?= e(t('dashboard')) ?></a><?php else: ?><a href="<?= e(url('register')) ?>"><?= e(t('register')) ?></a><?php endif; ?>
</div>
<div class="nav-actions">
<a class="btn btn-secondary btn-sm" href="<?= e(url(($action ?? 'home') . '?lang=' . (lang()==='en'?'ne':'en') . '&return=' . ($action ?? 'home'))) ?>"><?= lang()==='en'?'नेपाली':'English' ?></a>
<?php if ($user): ?><span class="user-pill"><?= e($user['full_name']) ?></span><form method="post" action="<?= e(url('logout')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-secondary" type="submit"><?= e(t('logout')) ?></button></form>
<?php else: ?><a class="btn btn-sm" href="<?= e(url('login')) ?>"><?= e(t('login')) ?></a><?php endif; ?>
</div></div></nav>
<div class="top-alert"><?= e(t('public_notice')) ?></div>
<main class="container">
<?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php
}
function render_footer(): void {
    ?></main><footer class="footer"><div class="footer-inner"><div><strong>NDMS · Nepal Disaster Management System</strong><p>Built for coordination, clarity and community resilience.</p></div><div><p>Information may be incomplete or delayed.</p><p>Always follow verified official instructions.</p></div></div></footer><script src="<?= e(url('assets/app.js')) ?>"></script></body></html><?php
}

switch ($action) {
    case 'home':
        render_header(t('home')); ?>
        <section class="hero">
          <div><div class="eyebrow">Nepal · Community resilience</div><h1><?= e(t('welcome')) ?></h1><p class="lead"><?= e(t('hero')) ?></p>
          <div class="hero-actions"><a class="btn btn-accent" href="<?= e(url($user?'report':'register')) ?>"><?= e(t('report_now')) ?> ↗</a><a class="btn btn-secondary" href="<?= e(url('reports')) ?>"><?= e(t('view_reports')) ?></a></div>
          <p class="muted" style="margin-top:22px">Bilingual · Mobile-friendly · Community-centred</p></div>
          <aside class="hero-panel"><h3>Operations overview / सञ्चालन अवलोकन</h3><p>Illustrative live system counters. Reports remain unconfirmed until reviewed.</p>
          <div class="panel-stat"><span>All submitted incidents</span><strong><?= $stats['total'] ?></strong></div>
          <div class="panel-stat"><span>Confirmed incidents</span><strong><?= $stats['confirmed'] ?></strong></div>
          <div class="panel-stat"><span>Open assistance cases</span><strong><?= $stats['open_help'] ?></strong></div></aside>
        </section>
        <section class="section"><div class="section-head"><div><div class="eyebrow">Response tools</div><h2>What do you need today?</h2></div></div>
        <div class="grid">
          <article class="card"><div class="feature-icon">⚑</div><h3><?= e(t('report')) ?></h3><p>Report a flood, landslide, earthquake, fire or other incident with location details.</p><a class="btn btn-secondary" href="<?= e(url($user?'report':'login')) ?>"><?= e(t('report_now')) ?> →</a></article>
          <article class="card"><div class="feature-icon">✚</div><h3><?= e(t('request_help')) ?></h3><p>Submit a request for rescue, medical support, shelter, food, water or supplies.</p><a class="btn btn-secondary" href="<?= e(url($user?'help':'login')) ?>"><?= e(t('request_help')) ?> →</a></article>
          <article class="card"><div class="feature-icon">⌖</div><h3><?= e(t('map')) ?></h3><p>Explore mapped, published incident reports. Verify information before acting.</p><a class="btn btn-secondary" href="<?= e(url('map')) ?>"><?= e(t('map')) ?> →</a></article>
        </div></section>
        <section class="section"><div class="section-head"><div><div class="eyebrow">Reviewed information</div><h2><?= e(t('latest')) ?></h2></div><a href="<?= e(url('reports')) ?>"><?= e(t('view_reports')) ?> →</a></div>
        <?php if (!$publicReports): ?><div class="card empty"><?= e(t('no_reports')) ?></div><?php else: ?><div class="grid"><?php foreach ($publicReports as $r): ?><article class="card"><div class="section-head"><span class="badge <?= e($r['severity']) ?>"><?= e(t($r['severity'])) ?></span><span class="badge <?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></div><h3><?= e($r['title']) ?></h3><p><?= e(t($r['disaster_type'])) ?> · <?= e($r['municipality']) ?>, <?= e($r['district']) ?></p><p class="muted"><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?> NPT</p></article><?php endforeach; ?></div><?php endif; ?></section>
        <section class="section notice"><strong>Emergency notice:</strong> This platform is not an emergency dispatch service. In immediate danger, contact verified local emergency services and follow official instructions.</section>
        <?php render_footer(); break;

    case 'login':
        render_header(t('login_title')); ?>
        <div class="form-wrap"><div class="form-card"><div class="eyebrow">Secure access</div><h1><?= e(t('login_title')) ?></h1><p class="muted"><?= e(t('login_help')) ?></p>
        <form method="post" action="<?= e(url('login')) ?>" data-validate><?= csrf_field() ?>
          <div class="field"><label for="email"><?= e(t('email')) ?></label><input id="email" name="email" type="email" maxlength="190" required autocomplete="username"></div>
          <div class="field"><label for="password"><?= e(t('password')) ?></label><div class="password-wrap"><input id="password" name="password" type="password" minlength="1" maxlength="200" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
          <button class="btn btn-accent" type="submit"><?= e(t('login')) ?></button> <a class="btn btn-secondary" href="<?= e(url('register')) ?>"><?= e(t('register')) ?></a>
        </form></div></div><?php render_footer(); break;

    case 'register':
        render_header(t('register_title')); ?>
        <div class="form-wrap"><div class="form-card"><div class="eyebrow">Join the response network</div><h1><?= e(t('register_title')) ?></h1><p class="muted">Create an account to submit reports and follow your requests. Organizational and responder privileges require additional approval before live operations.</p>
        <form method="post" action="<?= e(url('register')) ?>" data-validate><?= csrf_field() ?>
          <div class="field"><label for="full_name"><?= e(t('name')) ?></label><input id="full_name" name="full_name" required minlength="2" maxlength="120" autocomplete="name"></div>
          <div class="form-row"><div class="field"><label for="email"><?= e(t('email')) ?></label><input id="email" name="email" type="email" required maxlength="190" autocomplete="email"></div><div class="field"><label for="phone"><?= e(t('phone')) ?></label><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel"></div></div>
          <div class="field"><label for="role"><?= e(t('account_type')) ?></label><select id="role" name="role"><option value="citizen"><?= e(t('citizen')) ?></option><option value="responder"><?= e(t('responder')) ?></option><option value="organization"><?= e(t('organization')) ?></option></select><small>Public registration cannot create officer or administrator accounts.</small></div>
          <div class="form-row"><div class="field"><label for="password"><?= e(t('password')) ?></label><div class="password-wrap"><input id="password" name="password" type="password" minlength="12" maxlength="200" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div><small>Use at least 12 characters.</small></div><div class="field"><label for="confirm_password"><?= e(t('confirm_password')) ?></label><div class="password-wrap"><input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="200" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="confirm_password" aria-label="Show password" aria-pressed="false" title="Show password"><svg class="eye-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div></div>
          <button class="btn btn-accent" type="submit"><?= e(t('register')) ?></button>
        </form></div></div><?php render_footer(); break;

    case 'report':
        require_login(); render_header(t('report')); ?>
        <div class="form-wrap"><div class="form-card"><div class="eyebrow">Community incident intake</div><h1><?= e(t('report')) ?></h1><p class="muted">Reports are not public until authorized review. Do not include passwords or unnecessary sensitive information.</p>
        <form method="post" action="<?= e(url('report')) ?>" data-validate><?= csrf_field() ?>
          <div class="field"><label for="title">Short incident title</label><input id="title" name="title" required minlength="4" maxlength="160" placeholder="e.g. Landslide blocking road"></div>
          <div class="form-row"><div class="field"><label for="disaster_type"><?= e(t('type')) ?></label><select id="disaster_type" name="disaster_type" required><?php foreach(['flood','landslide','earthquake','fire','storm','other'] as $v): ?><option value="<?= e($v) ?>"><?= e(t($v)) ?></option><?php endforeach; ?></select></div><div class="field"><label for="severity"><?= e(t('severity')) ?></label><select id="severity" name="severity" required><?php foreach(['low','medium','high','critical'] as $v): ?><option value="<?= e($v) ?>"><?= e(t($v)) ?></option><?php endforeach; ?></select></div></div>
          <div class="form-row"><div class="field"><label for="province">Province / प्रदेश</label><select id="province" name="province" required><option value="">Select province</option><?php foreach(['Koshi','Madhesh','Bagmati','Gandaki','Lumbini','Karnali','Sudurpashchim'] as $v): ?><option><?= e($v) ?></option><?php endforeach; ?></select></div><div class="field"><label for="district">District / जिल्ला</label><input id="district" name="district" required maxlength="100"></div></div>
          <div class="form-row"><div class="field"><label for="municipality"><?= e(t('location')) ?></label><input id="municipality" name="municipality" required maxlength="150"></div><div class="field"><label for="ward">Ward / वडा</label><input id="ward" name="ward" maxlength="30"></div></div>
          <div class="field"><label for="address_details">Nearby landmark (optional)</label><input id="address_details" name="address_details" maxlength="255"></div>
          <div class="form-row"><div class="field"><label for="latitude">Latitude (optional)</label><input id="latitude" name="latitude" type="number" step="any" min="26" max="31.5"></div><div class="field"><label for="longitude">Longitude (optional)</label><input id="longitude" name="longitude" type="number" step="any" min="80" max="89"></div></div>
          <div class="field"><label for="description"><?= e(t('description')) ?></label><textarea id="description" name="description" required minlength="10" maxlength="5000"></textarea></div>
          <button class="btn btn-accent" type="submit"><?= e(t('submit')) ?></button></form></div></div><?php render_footer(); break;

    case 'help':
        require_login(); render_header(t('help_title')); ?>
        <div class="form-wrap"><div class="form-card"><div class="eyebrow">Assistance coordination</div><h1><?= e(t('help_title')) ?></h1><p class="notice"><?= e(t('public_notice')) ?></p>
        <form method="post" action="<?= e(url('help')) ?>" data-validate><?= csrf_field() ?>
          <div class="field"><label for="assistance_type"><?= e(t('help_type')) ?></label><select id="assistance_type" name="assistance_type" required><?php foreach(['rescue','medical','shelter','food_water','supplies','other'] as $v): ?><option value="<?= e($v) ?>"><?= e(t($v)) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="people_affected"><?= e(t('people')) ?></label><input id="people_affected" name="people_affected" type="number" min="1" max="10000" value="1" required></div>
          <div class="form-row"><div class="field"><label for="province">Province / प्रदेश</label><select id="province" name="province" required><option value="">Select province</option><?php foreach(['Koshi','Madhesh','Bagmati','Gandaki','Lumbini','Karnali','Sudurpashchim'] as $v): ?><option><?= e($v) ?></option><?php endforeach; ?></select></div><div class="field"><label for="district">District / जिल्ला</label><input id="district" name="district" required maxlength="100"></div></div>
          <div class="form-row"><div class="field"><label for="municipality"><?= e(t('location')) ?></label><input id="municipality" name="municipality" required maxlength="150"></div><div class="field"><label for="ward">Ward / वडा</label><input id="ward" name="ward" maxlength="30"></div></div>
          <div class="field"><label for="details"><?= e(t('description')) ?></label><textarea id="details" name="details" required minlength="10" maxlength="5000"></textarea></div>
          <button class="btn btn-accent" type="submit"><?= e(t('submit_help')) ?></button></form></div></div><?php render_footer(); break;

    case 'reports':
        render_header(t('alerts'));
        $stmt = $pdo->query("SELECT id,title,disaster_type,severity,province,district,municipality,status,created_at FROM incidents WHERE status IN ('confirmed','resolved') ORDER BY created_at DESC LIMIT 100");
        $rows = $stmt->fetchAll(); ?>
        <div class="section-head"><div><div class="eyebrow">Public information</div><h1><?= e(t('alerts')) ?></h1><p class="muted">Only reviewed and confirmed reports appear here. This is not an official alert feed.</p></div><?php if($user): ?><a class="btn btn-accent" href="<?= e(url('report')) ?>"><?= e(t('report_now')) ?></a><?php endif; ?></div>
        <?php if(!$rows): ?><div class="card empty"><?= e(t('no_reports')) ?></div><?php else: ?><div class="grid"><?php foreach($rows as $r): ?><article class="card"><div class="section-head"><span class="badge <?= e($r['severity']) ?>"><?= e(t($r['severity'])) ?></span><span class="badge <?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></div><h3><?= e($r['title']) ?></h3><p><?= e(t($r['disaster_type'])) ?> · <?= e($r['municipality']) ?>, <?= e($r['district']) ?>, <?= e($r['province']) ?></p><p class="muted"><?= e(date('Y-m-d H:i',strtotime($r['created_at']))) ?> NPT</p></article><?php endforeach; ?></div><?php endif; ?>
        <?php render_footer(); break;

    case 'map':
        render_header(t('map')); ?>
        <div class="section-head"><div><div class="eyebrow">Open-source mapping</div><h1><?= e(t('map')) ?></h1><p class="muted">Map displays published reports with coordinates. Base map tiles require an internet connection and follow the tile provider's usage policy.</p></div></div>
        <div id="incident-map" class="map" aria-label="Map of published incidents"></div>
        <section class="section"><div class="card"><strong>Map limitations</strong><p class="muted">No markers are shown unless a report has been confirmed and has valid coordinates. OpenStreetMap public tiles are suitable for limited development use but are not a guaranteed emergency-grade service.</p></div></section>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
        (() => {
          const el=document.getElementById('incident-map'); if(!el || !window.L){if(el)el.textContent='Map library could not load. Check your internet connection.';return;}
          const map=L.map(el).setView([28.3949,84.1240],7);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
          const points=<?= json_encode(array_values(array_filter($publicReports,fn($r)=>$r['latitude']!==null && $r['longitude']!==null)), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
          const esc = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
          points.forEach(p=>{const marker=L.marker([Number(p.latitude),Number(p.longitude)]).addTo(map);marker.bindPopup('<strong>'+esc(p.title)+'</strong><br>'+esc(p.municipality)+', '+esc(p.district));});
        })();
        </script>
        <?php render_footer(); break;

    case 'dashboard':
        $u = require_login(); render_header(t('dashboard')); ?>
        <div class="section-head"><div><div class="eyebrow"><?= e(role_label($u['role'])) ?></div><h1><?= e(t('dashboard')) ?></h1><p class="muted"><?= e(t('dashboard_intro')) ?></p></div></div>
        <div class="grid">
          <article class="card"><div class="feature-icon">⚑</div><h3><?= e(t('my_reports')) ?></h3><p>View your incident reports and review status.</p><a class="btn btn-secondary" href="<?= e(url('my-reports')) ?>">Open reports →</a></article>
          <article class="card"><div class="feature-icon">✚</div><h3><?= e(t('my_requests')) ?></h3><p>Track your assistance requests.</p><a class="btn btn-secondary" href="<?= e(url('my-requests')) ?>">Open requests →</a></article>
          <?php if(in_array($u['role'],['admin','officer'],true)): ?><article class="card"><div class="feature-icon">▦</div><h3><?= e(t('operations')) ?></h3><p>Review incidents and coordinate status updates.</p><a class="btn btn-accent" href="<?= e(url('admin')) ?>"><?= e(t('operations')) ?> →</a><?php endif; ?></article>
        </div>
        <section class="section card"><h2>Account information</h2><p><strong><?= e(t('name')) ?>:</strong> <?= e($u['full_name']) ?></p><p><strong><?= e(t('email')) ?>:</strong> <?= e($u['email']) ?></p><p><strong><?= e(t('account_type')) ?>:</strong> <?= e(role_label($u['role'])) ?></p></section>
        <?php render_footer(); break;

    case 'my-reports':
        $u = require_login(); render_header(t('my_reports'));
        $stmt = $pdo->prepare('SELECT id,title,disaster_type,severity,province,district,municipality,status,created_at FROM incidents WHERE user_id=? ORDER BY created_at DESC LIMIT 200');
        $stmt->execute([(int)$u['id']]); $rows=$stmt->fetchAll(); ?>
        <div class="section-head"><div><div class="eyebrow">Private workspace</div><h1><?= e(t('my_reports')) ?></h1><p class="muted">Only your reports are shown here.</p></div><a class="btn btn-accent" href="<?= e(url('report')) ?>"><?= e(t('report_now')) ?></a></div>
        <?php if(!$rows): ?><div class="card empty"><?= e(t('no_items')) ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>ID</th><th>Incident</th><th>Location</th><th><?= e(t('severity')) ?></th><th><?= e(t('status')) ?></th><th><?= e(t('created')) ?></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td>#<?= (int)$r['id'] ?></td><td><?= e($r['title']) ?></td><td><?= e($r['municipality']) ?>, <?= e($r['district']) ?></td><td><span class="badge <?= e($r['severity']) ?>"><?= e(t($r['severity'])) ?></span></td><td><span class="badge <?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td><td><?= e(date('Y-m-d',strtotime($r['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php render_footer(); break;

    case 'my-requests':
        $u = require_login(); render_header(t('my_requests'));
        $stmt=$pdo->prepare('SELECT id,assistance_type,people_affected,province,district,municipality,status,created_at FROM assistance_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 200');
        $stmt->execute([(int)$u['id']]); $rows=$stmt->fetchAll(); ?>
        <div class="section-head"><div><div class="eyebrow">Private workspace</div><h1><?= e(t('my_requests')) ?></h1><p class="muted">Only your assistance requests are shown here.</p></div><a class="btn btn-accent" href="<?= e(url('help')) ?>"><?= e(t('request_help')) ?></a></div>
        <?php if(!$rows): ?><div class="card empty"><?= e(t('no_items')) ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>ID</th><th>Type</th><th><?= e(t('people')) ?></th><th>Location</th><th><?= e(t('status')) ?></th><th><?= e(t('created')) ?></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td>#<?= (int)$r['id'] ?></td><td><?= e(t($r['assistance_type'])) ?></td><td><?= (int)$r['people_affected'] ?></td><td><?= e($r['municipality']) ?>, <?= e($r['district']) ?></td><td><span class="badge <?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td><td><?= e(date('Y-m-d',strtotime($r['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php render_footer(); break;

    case 'admin':
        $actor=require_role(['admin','officer']); render_header(t('operations'));
        $stmt=$pdo->query("SELECT i.*,u.full_name AS reporter_name FROM incidents i JOIN users u ON u.id=i.user_id ORDER BY CASE i.severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, i.created_at DESC LIMIT 300");
        $rows=$stmt->fetchAll(); ?>
        <div class="section-head"><div><div class="eyebrow">Restricted operations workspace</div><h1><?= e(t('review_queue')) ?></h1><p class="muted">Access is restricted to authorized operations officers and administrators. Confirm incident information before public publication.</p></div></div>
        <?php if(!$rows): ?><div class="card empty"><?= e(t('no_items')) ?></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Incident</th><th>Reporter</th><th>Location</th><th>Priority</th><th>Status</th><th>Update</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong>#<?= (int)$r['id'] ?> · <?= e($r['title']) ?></strong><br><span class="muted"><?= e(t($r['disaster_type'])) ?> — <?= e(mb_strimwidth($r['description'],0,120,'…')) ?></span></td><td><?= e($r['reporter_name']) ?></td><td><?= e($r['municipality']) ?>, <?= e($r['district']) ?><br><span class="muted"><?= e($r['province']) ?></span></td><td><span class="badge <?= e($r['severity']) ?>"><?= e(t($r['severity'])) ?></span></td><td><span class="badge <?= e($r['status']) ?>"><?= e(status_label($r['status'])) ?></span></td><td><form method="post" action="<?= e(url('admin')) ?>" class="inline-form" data-validate><?= csrf_field() ?><input type="hidden" name="incident_id" value="<?= (int)$r['id'] ?>"><select name="new_status" aria-label="New status"><?php foreach(['under_review','confirmed','assigned','in_progress','resolved','rejected'] as $s): ?><option value="<?= e($s) ?>" <?= $r['status']===$s?'selected':'' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select><button class="btn btn-sm" type="submit"><?= e(t('save')) ?></button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <?php render_footer(); break;
}
