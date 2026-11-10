<?php
/**
 * ZAVORA Marketplace — Core Configuration & Ecosystem Gateway
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        @session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

// ── Environment File Loader (.env) ──────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $envLines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        if (preg_match('/^"(.+)"$/', $val, $m) || preg_match("/^'(.+)'$/", $val, $m)) {
            $val = $m[1];
        }
        if (getenv($key) === false) {
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Development mode detection
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isDev = (PHP_SAPI === 'cli')
    || empty($httpHost)
    || $httpHost === 'localhost'
    || $httpHost === '127.0.0.1'
    || strpos($httpHost, 'localhost:') === 0
    || strpos($httpHost, '127.0.0.1:') === 0;

define('ZAVORA_DEV_MODE', $isDev);
define('ZAVORA_APP_NAME', 'ZAVORA');
define('ZAVORA_TAGLINE', 'Everything you need. One place.');

// Base URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
$projectRoot = realpath(__DIR__ . '/..');

if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
    $baseDir = rtrim(str_replace('\\', '/', substr($projectRoot, strlen($docRoot))), '/');
} else {
    $baseDir = '/zavora';
}

define('ZAVORA_BASE_URL', getenv('ZAVORA_SITE_URL') ?: ($protocol . ($httpHost ?: 'localhost') . ($baseDir ? $baseDir : '')));

// Ecosystem Inter-Service URLs & Keys
define('JLM_CORPORATE_URL', getenv('JLM_CORPORATE_URL') ?: ($isDev ? 'http://localhost/jlm' : 'https://jlm.com.ng'));
define('LEARNERIUM_URL', getenv('LEARNERIUM_URL') ?: 'https://learnerium.jlm.com.ng');
define('LEARNERIUM_API_URL', LEARNERIUM_URL . '/api/v1');
define('ECOSYSTEM_API_SECRET', getenv('ECOSYSTEM_API_SECRET') ?: 'eco_sec_jlm_zavora_01a2c11beee84a1bb6e5ce40a29769f4');

// Payment Gateway (Paystack)
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_sample_key_for_development');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_sample_key_for_development');

// Database bootstrap
require_once __DIR__ . '/database.php';

/**
 * Currency Formatter
 */
function zavora_format_price($amount) {
    return '₦' . number_format((float) $amount, 2);
}

function zavora_format_currency($amount) {
    return zavora_format_price($amount);
}

/**
 * Asset URL helper
 */
function zavora_asset($path) {
    return ZAVORA_BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Resolves product image URLs: handles both local uploads (uploads/products/...)
 * and external URLs (https://...) or data URIs seamlessly.
 */
function zavora_image_url($url) {
    if (empty($url)) {
        return 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800';
    }
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, 'data:') === 0) {
        return $url;
    }
    return zavora_asset($url);
}

/**
 * Shopping Cart Management
 */
function zavora_get_cart() {
    if (!isset($_SESSION['zavora_cart']) || !is_array($_SESSION['zavora_cart'])) {
        $_SESSION['zavora_cart'] = [];
    }
    return $_SESSION['zavora_cart'];
}

function zavora_add_to_cart($id, $type, $title, $price, $image, $quantity = 1, $extra = []) {
    $cart = zavora_get_cart();
    $varSuffix = !empty($extra['variant_id']) ? "_{$extra['variant_id']}" : "";
    $key = "{$type}_{$id}{$varSuffix}";

    if (isset($cart[$key])) {
        // Digital and courses are unique per customer (quantity remains 1)
        if ($type === 'physical') {
            $cart[$key]['quantity'] += (int) $quantity;
        }
    } else {
        $cart[$key] = [
            'id'       => $id,
            'type'     => $type, // physical | digital | course
            'title'    => $title,
            'price'    => (float) $price,
            'image'    => $image,
            'quantity' => ($type === 'physical') ? max(1, (int) $quantity) : 1,
            'extra'    => $extra,
        ];
    }

    $_SESSION['zavora_cart'] = $cart;
    return $cart;
}

function zavora_remove_from_cart($key) {
    if (isset($_SESSION['zavora_cart'][$key])) {
        unset($_SESSION['zavora_cart'][$key]);
    }
}

function zavora_clear_cart() {
    $_SESSION['zavora_cart'] = [];
}

function zavora_cart_totals($deliveryState = null) {
    $cart = zavora_get_cart();
    $subtotal = 0;
    $hasPhysical = false;
    $hasDigital = false;
    $hasCourses = false;
    $itemCount = 0;

    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $itemCount += $item['quantity'];
        if ($item['type'] === 'physical') $hasPhysical = true;
        if ($item['type'] === 'digital') $hasDigital = true;
        if ($item['type'] === 'course') $hasCourses = true;
    }

    // Standard Nigerian Delivery Matrix (Only applied if physical products are present)
    $deliveryFee = 0;
    if ($hasPhysical) {
        if ($deliveryState) {
            $state = strtolower(trim($deliveryState));
            if ($state === 'lagos' || $state === 'abuja' || $state === 'kaduna') {
                $deliveryFee = 2500.00;
            } else {
                $deliveryFee = 4500.00;
            }
        } else {
            $deliveryFee = 3500.00; // Flat estimated default
        }
    }

    $total = $subtotal + $deliveryFee;

    return [
        'subtotal'       => $subtotal,
        'delivery_fee'   => $deliveryFee,
        'total'          => $total,
        'item_count'     => $itemCount,
        'has_physical'   => $hasPhysical,
        'has_digital'    => $hasDigital,
        'has_courses'    => $hasCourses,
    ];
}

/**
 * Ecosystem Learnerium LMS API Client
 */
function zavora_call_lms_api($endpoint, $method = 'GET', $data = null) {
    $url = LEARNERIUM_API_URL . '/' . ltrim($endpoint, '/');
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . ECOSYSTEM_API_SECRET,
        'X-Ecosystem-Key: ' . ECOSYSTEM_API_SECRET,
        'Accept: application/json',
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    @curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error'   => 'network_error',
            'message' => $error ?: 'Unable to contact Learnerium LMS API',
        ];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'raw' => $response, 'http_code' => $httpCode];
}

/**
 * Generate a cryptographically signed HMAC SSO URL for seamless 1-click course launch into Learnerium
 */
function zavora_generate_lms_sso_url($email, $name = null, $courseId = null, $courseSlug = null, $redirectTo = null) {
    $payload = [
        'email'       => strtolower(trim($email)),
        'name'        => $name,
        'course_id'   => $courseId ? (int)$courseId : null,
        'course_slug' => $courseSlug,
        'redirect_to' => $redirectTo,
        'timestamp'   => time(),
        'nonce'       => bin2hex(random_bytes(8)),
    ];

    $b64 = base64_encode(json_encode($payload));
    $sig = hash_hmac('sha256', $b64, ECOSYSTEM_API_SECRET);

    return LEARNERIUM_URL . '/auth/ecosystem/sso?token=' . urlencode($b64 . '.' . $sig);
}

/**
 * Fetch published courses from Learnerium for the Academy section
 */
function zavora_get_academy_courses($perPage = 12) {
    $res = zavora_call_lms_api("/courses/catalogue?per_page={$perPage}");
    if (!empty($res['success']) && !empty($res['data'])) {
        return $res['data'];
    }

    // Fallback demonstration courses if LMS is temporarily offline
    return [
        [
            'id'                 => 101,
            'title'              => 'Mastering Full-Stack Laravel & React',
            'slug'               => 'mastering-fullstack-laravel-react',
            'description'        => 'Build enterprise-grade software applications, modern REST APIs, multi-tenant databases, and high-performance React frontends from scratch.',
            'price'              => 35000.00,
            'effective_price'    => 35000.00,
            'level'              => 'Intermediate',
            'category'           => 'Technology',
            'duration_formatted' => '24 hours',
            'thumbnail_url'      => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800&auto=format&fit=crop&q=80',
            'modules_count'      => 8,
            'lessons_count'      => 42,
            'instructor'         => ['name' => 'John Luka', 'avatar_url' => null],
            'is_preorder'        => false,
        ],
        [
            'id'                 => 102,
            'title'              => 'Live Broadcasting & Technical Media Direction',
            'slug'               => 'live-broadcasting-technical-direction',
            'description'        => 'The operational standard behind non-stop multi-camera livestreaming, audio mixing consoles, hardware redundancy, and OBS studio automation.',
            'price'              => 25000.00,
            'effective_price'    => 25000.00,
            'level'              => 'All Levels',
            'category'           => 'Media Production',
            'duration_formatted' => '16 hours',
            'thumbnail_url'      => 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?w=800&auto=format&fit=crop&q=80',
            'modules_count'      => 6,
            'lessons_count'      => 28,
            'instructor'         => ['name' => 'John Luka Media Team', 'avatar_url' => null],
            'is_preorder'        => false,
        ],
        [
            'id'                 => 103,
            'title'              => 'Advanced Graphic Design & Brand Identity Systems',
            'slug'               => 'advanced-graphic-design-brand-identity',
            'description'        => 'Create iconic, memorable commercial brand identities, scalable logo systems, brand guidelines, and typography hierarchies for international brands.',
            'price'              => 20000.00,
            'effective_price'    => 20000.00,
            'level'              => 'Beginner to Pro',
            'category'           => 'Design & Creative',
            'duration_formatted' => '18 hours',
            'thumbnail_url'      => 'https://images.unsplash.com/photo-1626785774573-4b799315345d?w=800&auto=format&fit=crop&q=80',
            'modules_count'      => 7,
            'lessons_count'      => 34,
            'instructor'         => ['name' => 'JLM Creative Studio', 'avatar_url' => null],
            'is_preorder'        => false,
        ]
    ];
}

// ── Session Control & Role-Based Access Guards ──────────────────────────────

/**
 * Check if the active session belongs to an authenticated administrator.
 * Enforces a 2-hour inactivity timeout.
 *
 * @return array|false Admin user array on success, false otherwise.
 */
function zavora_admin_auth() {
    if (empty($_SESSION['zavora_admin_logged_in']) || empty($_SESSION['zavora_admin_id'])) {
        return false;
    }

    $timeout = 7200; // 2 hours inactivity limit
    $lastActivity = (int)($_SESSION['zavora_admin_last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $timeout) {
        zavora_admin_logout();
        return false;
    }

    // Refresh last activity
    $_SESSION['zavora_admin_last_activity'] = time();

    try {
        $pdo = zavora_get_db();
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, is_active FROM admin_users WHERE id = ? AND is_active = 1");
        $stmt->execute([(int)$_SESSION['zavora_admin_id']]);
        $admin = $stmt->fetch();
        if (!$admin) {
            zavora_admin_logout();
            return false;
        }
        return $admin;
    } catch (\Throwable $e) {
        // Fallback for valid session if DB transient error
        return [
            'id'        => $_SESSION['zavora_admin_id'],
            'username'  => $_SESSION['zavora_admin_user'] ?? 'admin',
            'email'     => $_SESSION['zavora_admin_email'] ?? 'admin@jlm.com.ng',
            'role'      => 'superadmin',
            'is_active' => 1
        ];
    }
}

/**
 * Strict Admin Gatekeeper: requires valid admin session or halts and redirects to admin/login.php.
 *
 * @return array Authenticated admin record.
 */
function zavora_admin_require_auth() {
    $admin = zavora_admin_auth();
    if (!$admin) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $redirectParam = !empty($uri) ? '?redirect=' . urlencode($uri) : '';
        header('Location: ' . zavora_asset('admin/login.php' . $redirectParam));
        exit;
    }
    return $admin;
}

/**
 * Safe Admin Logout: clears admin session and regenerates session ID.
 */
function zavora_admin_logout() {
    unset(
        $_SESSION['zavora_admin_logged_in'],
        $_SESSION['zavora_admin_id'],
        $_SESSION['zavora_admin_user'],
        $_SESSION['zavora_admin_email'],
        $_SESSION['zavora_admin_last_activity'],
        $_SESSION['zavora_admin_ip']
    );
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
}

/**
 * Check if the active session belongs to an approved, active Vendor.
 * Enforces a 4-hour inactivity timeout.
 *
 * @return array|false Vendor record on success, false otherwise.
 */
function zavora_vendor_auth() {
    $vendorId = $_SESSION['zavora_vendor_id'] ?? null;
    if (!$vendorId) {
        return false;
    }

    $timeout = 14400; // 4 hours inactivity limit
    $lastActivity = (int)($_SESSION['zavora_vendor_last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $timeout) {
        zavora_vendor_logout();
        return false;
    }

    try {
        $pdo = zavora_get_db();
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([(int)$vendorId]);
        $vendor = $stmt->fetch();
        if (!$vendor || $vendor['status'] === 'suspended') {
            zavora_vendor_logout();
            return false;
        }

        $_SESSION['zavora_vendor_last_activity'] = time();
        return $vendor;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Strict Vendor Gatekeeper: requires valid vendor session or redirects to seller/login.php.
 *
 * @return array Authenticated vendor record.
 */
function zavora_vendor_require_auth() {
    $vendor = zavora_vendor_auth();
    if (!$vendor) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $redirectParam = !empty($uri) ? '?redirect=' . urlencode($uri) : '';
        header('Location: ' . zavora_asset('seller/login.php' . $redirectParam));
        exit;
    }
    return $vendor;
}

/**
 * Safe Vendor Logout: clears merchant session and regenerates session ID.
 */
function zavora_vendor_logout() {
    unset(
        $_SESSION['zavora_vendor_id'],
        $_SESSION['zavora_vendor_name'],
        $_SESSION['zavora_vendor_last_activity']
    );
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
}

/**
 * Check if the customer has an active authenticated session.
 *
 * @return string|false Customer email if authenticated, false otherwise.
 */
function zavora_customer_auth() {
    $email = $_SESSION['zavora_customer_email'] ?? null;
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return strtolower(trim($email));
}

/**
 * Safe Customer Logout.
 */
function zavora_customer_logout() {
    unset(
        $_SESSION['zavora_customer_email'],
        $_SESSION['zavora_customer_otp'],
        $_SESSION['zavora_customer_otp_time'],
        $_SESSION['zavora_customer_otp_email']
    );
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
}

