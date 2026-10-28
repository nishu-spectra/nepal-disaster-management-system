<?php
declare(strict_types=1);

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $path = ''): string {
    return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '/');
}
function redirect(string $path): never {
    header('Location: ' . url($path));
    exit;
}
function lang(): string {
    return ($_SESSION['lang'] ?? 'en') === 'ne' ? 'ne' : 'en';
}
function t(string $key): string {
    static $dict = [
        'en' => [
            'home'=>'Home','alerts'=>'Disaster reports','map'=>'Incident map','report'=>'Report incident',
            'request_help'=>'Request help','login'=>'Log in','register'=>'Create account','logout'=>'Log out',
            'dashboard'=>'Dashboard','my_reports'=>'My reports','my_requests'=>'My help requests',
            'operations'=>'Operations desk','admin'=>'Administration','welcome'=>'Preparedness starts together.',
            'hero'=>'A shared platform for safer, better coordinated disaster response across Nepal.',
            'view_reports'=>'View reports','report_now'=>'Report an incident','latest'=>'Recent public reports',
            'no_reports'=>'No public reports are available yet.','type'=>'Disaster type','severity'=>'Severity',
            'location'=>'Location / municipality','description'=>'Description','submit'=>'Submit report',
            'name'=>'Full name','email'=>'Email address','password'=>'Password','confirm_password'=>'Confirm password',
            'phone'=>'Phone (optional)','login_title'=>'Welcome back','register_title'=>'Create your account',
            'login_help'=>'Sign in to manage your reports and requests.','account_type'=>'Account type',
            'citizen'=>'Citizen / help seeker','responder'=>'Responder / volunteer','organization'=>'Organization member',
            'officer'=>'Operations officer','status'=>'Status','created'=>'Created','actions'=>'Actions',
            'open'=>'Open','under_review'=>'Under review','confirmed'=>'Confirmed','assigned'=>'Assigned',
            'in_progress'=>'In progress','resolved'=>'Resolved','rejected'=>'Rejected','low'=>'Low','medium'=>'Medium',
            'high'=>'High','critical'=>'Critical','flood'=>'Flood','landslide'=>'Landslide','earthquake'=>'Earthquake',
            'fire'=>'Fire','storm'=>'Storm / lightning','other'=>'Other','help_title'=>'Request emergency assistance',
            'help_type'=>'Type of assistance','people'=>'People affected','submit_help'=>'Submit help request',
            'shelter'=>'Shelter','medical'=>'Medical support','food_water'=>'Food and clean water','rescue'=>'Rescue / evacuation',
            'supplies'=>'Relief supplies','dashboard_intro'=>'Your workspace and case updates.',
            'review_queue'=>'Incident review queue','update_status'=>'Update status','save'=>'Save changes',
            'not_authorized'=>'You are not authorized to access this page.','required'=>'Please complete all required fields.',
            'invalid_login'=>'Email or password is incorrect.','too_many'=>'Too many login attempts. Please try again later.',
            'account_created'=>'Account created. You can now log in.','report_created'=>'Your report has been submitted for review.',
            'help_created'=>'Your assistance request has been submitted.','error'=>'Something went wrong. Please try again.',
            'public_notice'=>'For immediate danger, contact verified local emergency services. This website does not dispatch responders.',
            'language'=>'भाषा / Language','filter'=>'Filter','all'=>'All','priority'=>'Priority',
            'incident_detail'=>'Incident details','help_requests'=>'Help requests','no_items'=>'Nothing to show yet.',
        ],
        'ne' => [
            'home'=>'गृहपृष्ठ','alerts'=>'विपद् विवरण','map'=>'विपद् नक्सा','report'=>'घटना रिपोर्ट',
            'request_help'=>'सहयोग माग्नुहोस्','login'=>'लगइन','register'=>'खाता खोल्नुहोस्','logout'=>'लगआउट',
            'dashboard'=>'ड्यासबोर्ड','my_reports'=>'मेरा रिपोर्टहरू','my_requests'=>'मेरा सहयोग अनुरोध',
            'operations'=>'सञ्चालन कक्ष','admin'=>'प्रशासन','welcome'=>'सुरक्षा र तयारी हामी सबैको साझा जिम्मेवारी हो।',
            'hero'=>'नेपालभर विपद् प्रतिकार्य समन्वय र सहयोगका लागि साझा डिजिटल मञ्च।',
            'view_reports'=>'रिपोर्ट हेर्नुहोस्','report_now'=>'घटना रिपोर्ट गर्नुहोस्','latest'=>'हालका सार्वजनिक रिपोर्ट',
            'no_reports'=>'अहिलेसम्म सार्वजनिक रिपोर्ट छैन।','type'=>'विपद्को प्रकार','severity'=>'गम्भीरता',
            'location'=>'स्थान / नगरपालिका','description'=>'विवरण','submit'=>'रिपोर्ट पठाउनुहोस्',
            'name'=>'पूरा नाम','email'=>'इमेल ठेगाना','password'=>'पासवर्ड','confirm_password'=>'पासवर्ड पुष्टि गर्नुहोस्',
            'phone'=>'फोन (ऐच्छिक)','login_title'=>'फेरि स्वागत छ','register_title'=>'खाता खोल्नुहोस्',
            'login_help'=>'आफ्ना रिपोर्ट र अनुरोध व्यवस्थापन गर्न लगइन गर्नुहोस्।','account_type'=>'खाताको प्रकार',
            'citizen'=>'नागरिक / सहयोग चाहिने व्यक्ति','responder'=>'उद्धारकर्ता / स्वयंसेवक','organization'=>'संस्थाको सदस्य',
            'officer'=>'सञ्चालन अधिकृत','status'=>'अवस्था','created'=>'मिति','actions'=>'कार्य',
            'open'=>'खुला','under_review'=>'समीक्षामा','confirmed'=>'पुष्टि भएको','assigned'=>'तोकिएको',
            'in_progress'=>'प्रगतिमा','resolved'=>'समाधान भएको','rejected'=>'अस्वीकृत','low'=>'कम','medium'=>'मध्यम',
            'high'=>'उच्च','critical'=>'अति गम्भीर','flood'=>'बाढी','landslide'=>'पहिरो','earthquake'=>'भूकम्प',
            'fire'=>'आगलागी','storm'=>'हावाहुरी / चट्याङ','other'=>'अन्य','help_title'=>'आपतकालीन सहयोग माग्नुहोस्',
            'help_type'=>'आवश्यक सहयोग','people'=>'प्रभावित व्यक्तिहरू','submit_help'=>'सहयोग अनुरोध पठाउनुहोस्',
            'shelter'=>'आश्रय','medical'=>'स्वास्थ्य सहयोग','food_water'=>'खाना र स्वच्छ पानी','rescue'=>'उद्धार / सुरक्षित स्थानान्तरण',
            'supplies'=>'राहत सामग्री','dashboard_intro'=>'तपाईंको कार्यक्षेत्र र केस अद्यावधिक।',
            'review_queue'=>'घटना समीक्षा सूची','update_status'=>'अवस्था परिवर्तन','save'=>'परिवर्तन सुरक्षित गर्नुहोस्',
            'not_authorized'=>'यो पृष्ठ हेर्ने अनुमति छैन।','required'=>'कृपया आवश्यक सबै विवरण भर्नुहोस्।',
            'invalid_login'=>'इमेल वा पासवर्ड मिलेन।','too_many'=>'धेरै पटक लगइन प्रयास भयो। केही समयपछि पुनः प्रयास गर्नुहोस्।',
            'account_created'=>'खाता बन्यो। अब लगइन गर्न सक्नुहुन्छ।','report_created'=>'रिपोर्ट समीक्षाका लागि पठाइएको छ।',
            'help_created'=>'सहयोग अनुरोध पठाइएको छ।','error'=>'त्रुटि भयो। कृपया पुनः प्रयास गर्नुहोस्।',
            'public_notice'=>'तत्काल जोखिममा परेमा प्रमाणित स्थानीय आपतकालीन सेवामा सम्पर्क गर्नुहोस्। यो वेबसाइटले उद्धार टोली पठाउँदैन।',
            'language'=>'भाषा / Language','filter'=>'फिल्टर','all'=>'सबै','priority'=>'प्राथमिकता',
            'incident_detail'=>'घटनाको विवरण','help_requests'=>'सहयोग अनुरोध','no_items'=>'देखाउनका लागि केही छैन।',
        ],
    ];
    $l = lang();
    return $dict[$l][$key] ?? $dict['en'][$key] ?? $key;
}
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($f) ? $f : null;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function verify_csrf(): void {
    $posted = (string)($_POST['csrf'] ?? '');
    if ($posted === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $posted)) {
        http_response_code(419);
        exit('Your form expired. Please go back and try again.');
    }
}
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}
function require_login(): array {
    $user = current_user();
    if (!$user) { flash('warning', t('login_help')); redirect('login'); }
    return $user;
}
function require_role(array $roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit(t('not_authorized'));
    }
    return $user;
}
function log_security_event(string $event, ?int $userId = null, array $context = []): void {
    try {
        $stmt = db()->prepare('INSERT INTO security_events (user_id, event_type, ip_hash, details, created_at) VALUES (?, ?, ?, ?, NOW())');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $stmt->execute([$userId, substr(hash('sha256', $ip . 'ndms-local-salt'), 0, 64), $event, json_encode($context, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) {
        error_log('[NDMS] Security event write failed: ' . $e->getMessage());
    }
}
function safe_redirect_after_login(array $user): never {
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    log_security_event('login_success', (int)$user['id']);
    redirect('dashboard');
}
function status_label(string $status): string {
    $map = ['open'=>'open','under_review'=>'under_review','confirmed'=>'confirmed','assigned'=>'assigned','in_progress'=>'in_progress','resolved'=>'resolved','rejected'=>'rejected'];
    return t($map[$status] ?? $status);
}
function role_label(string $role): string {
    return ['admin'=>'Administrator','officer'=>'Operations officer','organization'=>'Organization member','responder'=>'Responder / volunteer','citizen'=>'Citizen'][$role] ?? $role;
}
