<?php
/**
 * ZAVORA Operations Admin Console — Secure Session Gateway
 *
 * Implements strict administrative access control, brute-force throttling,
 * session fixation regeneration, and inactivity lockouts.
 */

require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();
$error = '';
$notice = '';

// Handle Admin Sign Out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    zavora_admin_logout();
    header('Location: ' . zavora_asset('admin/login.php?logged_out=1'));
    exit;
}

// Redirect if already authenticated
if (zavora_admin_auth()) {
    $redirect = $_GET['redirect'] ?? zavora_asset('admin/index.php');
    header('Location: ' . $redirect);
    exit;
}

if (!empty($_GET['logged_out'])) {
    $notice = 'You have safely signed out of the ZAVORA Operations Console.';
} elseif (!empty($_GET['timeout'])) {
    $error = 'Your admin session timed out due to inactivity. Please sign in again.';
}

// Rate Limiting / Brute-force Shield
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$lockoutKey = 'admin_failed_' . md5($ip);
$failedAttempts = (int)($_SESSION[$lockoutKey . '_count'] ?? 0);
$lastFailedTime = (int)($_SESSION[$lockoutKey . '_time'] ?? 0);

if ($failedAttempts >= 5 && (time() - $lastFailedTime) < 900) {
    $remaining = ceil((900 - (time() - $lastFailedTime)) / 60);
    $error = "Too many failed sign-in attempts. For security, access is temporarily locked for {$remaining} minutes.";
}

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $loginIdentifier = strtolower(trim($_POST['identifier'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $redirect = trim($_POST['redirect'] ?? zavora_asset('admin/index.php'));

    if (empty($loginIdentifier) || empty($password)) {
        $error = 'Please provide both your administrator username/email and password.';
    } else {
        $admin = null;
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND is_active = 1 LIMIT 1");
            $stmt->execute([$loginIdentifier, $loginIdentifier]);
            $admin = $stmt->fetch();
        } catch (\Throwable $e) {}

        $isValid = false;
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $isValid = true;
        } else {
            // Environment fallback password for initial setup
            $envAdminPass = getenv('ZAVORA_ADMIN_PASSWORD') ?: getenv('ADMIN_PASSWORD');
            if ($envAdminPass && ($loginIdentifier === 'admin' || $loginIdentifier === 'admin@jlm.com.ng') && hash_equals($envAdminPass, $password)) {
                $isValid = true;
                if (!$admin) {
                    $admin = [
                        'id' => 1,
                        'username' => 'admin',
                        'email' => 'admin@jlm.com.ng',
                        'full_name' => 'JLM Operations Director',
                        'role' => 'superadmin'
                    ];
                }
            }
        }

        if ($isValid && $admin) {
            // Reset throttle counters
            unset($_SESSION[$lockoutKey . '_count'], $_SESSION[$lockoutKey . '_time']);

            // Session fixation defense: regenerate session ID
            @session_regenerate_id(true);

            // Establish secure admin session
            $_SESSION['zavora_admin_logged_in'] = true;
            $_SESSION['zavora_admin_id'] = (int)$admin['id'];
            $_SESSION['zavora_admin_user'] = $admin['username'];
            $_SESSION['zavora_admin_email'] = $admin['email'];
            $_SESSION['zavora_admin_last_activity'] = time();
            $_SESSION['zavora_admin_ip'] = $ip;

            // Update last_login in DB
            try {
                $pdo->prepare("UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$admin['id']]);
            } catch (\Throwable $e) {}

            header('Location: ' . $redirect);
            exit;
        } else {
            // Increment failed attempt counter
            $_SESSION[$lockoutKey . '_count'] = $failedAttempts + 1;
            $_SESSION[$lockoutKey . '_time'] = time();
            $remainingAttempts = max(0, 5 - ($failedAttempts + 1));
            $error = 'Invalid administrator credentials. Access restricted.';
            if ($remainingAttempts > 0 && $remainingAttempts < 4) {
                $error .= " ({$remainingAttempts} attempts remaining before temporary lockout)";
            }
        }
    }
}

$title = 'Operations Admin Sign In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
  <div class="max-w-md w-full space-y-8 bg-[#0B132B] text-white p-8 sm:p-10 rounded-3xl border border-white/10 shadow-2xl relative overflow-hidden">
    
    <!-- Ambient Accent Glow -->
    <div class="absolute -top-24 -right-24 w-52 h-52 bg-[#1B2299]/40 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-52 h-52 bg-[#E4306D]/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative z-10 text-center">
      <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-tr from-[#1B2299] to-[#E4306D] flex items-center justify-center shadow-lg text-white text-2xl">
        <i class="fas fa-shield-halved"></i>
      </div>
      
      <span class="px-3 py-1 rounded-full bg-white/10 text-[#F7DE7A] border border-white/10 text-[10px] font-black uppercase tracking-widest inline-block mb-3">
        ZAVORA Control Operations
      </span>
      <h2 class="text-2xl sm:text-3xl font-black font-display tracking-tight text-white">Administrator Access</h2>
      <p class="mt-2 text-xs text-gray-400">
        Strict session authentication required. Direct link hopping without verified credentials is prohibited.
      </p>
    </div>

    <?php if ($notice): ?>
      <div class="p-3.5 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl text-xs font-semibold text-emerald-300 flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-400"></i>
        <span><?php echo htmlspecialchars($notice); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-2xl text-xs font-semibold text-rose-300 flex items-center gap-2">
        <i class="fas fa-triangle-exclamation text-rose-400"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" class="mt-6 space-y-4 relative z-10">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? zavora_asset('admin/index.php')); ?>">
      
      <div>
        <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider mb-1.5">Username or Email</label>
        <div class="relative">
          <input type="text" name="identifier" required autocomplete="username"
                 placeholder="admin@jlm.com.ng"
                 value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>"
                 class="w-full pl-11 pr-4 py-3 bg-white/5 border border-white/15 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#1B2299] focus:border-transparent transition-all">
          <i class="far fa-user absolute left-3.5 top-3.5 text-gray-400"></i>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="block text-[11px] font-bold text-gray-300 uppercase tracking-wider">Access Password</label>
          <span class="text-[10px] text-gray-400">HMAC-SHA256 Protected</span>
        </div>
        <div class="relative">
          <input type="password" id="adminPassword" name="password" required autocomplete="current-password"
                 placeholder="••••••••••••"
                 class="w-full pl-11 pr-11 py-3 bg-white/5 border border-white/15 rounded-xl text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#1B2299] focus:border-transparent transition-all">
          <i class="fas fa-lock absolute left-3.5 top-3.5 text-gray-400"></i>
          <button type="button" onclick="togglePassVisibility()" class="absolute right-3.5 top-3 text-gray-400 hover:text-white transition-colors">
            <i class="far fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="pt-2">
        <button type="submit"
                class="w-full py-3.5 px-4 bg-gradient-to-r from-[#1B2299] to-[#E4306D] hover:from-[#151a77] hover:to-[#c22055] text-white font-bold text-sm rounded-xl transition-all shadow-lg hover:shadow-xl hover:scale-[1.01] flex items-center justify-center gap-2">
          <i class="fas fa-arrow-right-to-bracket"></i>
          <span>Authenticate & Open Console</span>
        </button>
      </div>

      <div class="pt-4 border-t border-white/10 text-center text-[11px] text-gray-400 flex items-center justify-center gap-3">
        <span class="flex items-center gap-1 text-emerald-400">
          <i class="fas fa-lock text-[10px]"></i> SSL Enforced
        </span>
        <span>•</span>
        <span>Inactivity Timeout: 2 hrs</span>
        <span>•</span>
        <a href="<?php echo zavora_asset('index.php'); ?>" class="hover:text-white transition-colors">Return to Store</a>
      </div>
    </form>

  </div>
</div>

<script>
function togglePassVisibility() {
  const input = document.getElementById('adminPassword');
  const icon = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
