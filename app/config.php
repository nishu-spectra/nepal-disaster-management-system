<?php
declare(strict_types=1);

/*
 * Local XAMPP defaults only. For production, provide environment variables:
 * NDMS_DB_HOST, NDMS_DB_NAME, NDMS_DB_USER, NDMS_DB_PASS, NDMS_BASE_PATH,
 * NDMS_APP_ENV, NDMS_SETUP_KEY.
 */
define('APP_NAME', 'Nepal Disaster Management System');
define('APP_ENV', getenv('NDMS_APP_ENV') ?: 'development');
define('BASE_PATH', rtrim(getenv('NDMS_BASE_PATH') ?: '/nepal-disaster-management/public', '/'));
define('DB_HOST', getenv('NDMS_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('NDMS_DB_NAME') ?: 'ndms');
define('DB_USER', getenv('NDMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('NDMS_DB_PASS') !== false ? getenv('NDMS_DB_PASS') : '');
define('SETUP_KEY', getenv('NDMS_SETUP_KEY') ?: 'CHANGE-ME-SETUP-KEY');
define('SESSION_NAME', 'ndms_session');
define('MAX_LOGIN_ATTEMPTS', 8);
define('LOGIN_WINDOW_MINUTES', 15);
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
