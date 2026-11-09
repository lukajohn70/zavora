<?php
require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();
$error = '';
$notice = '';

// Handle Merchant Sign Out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    zavora_vendor_logout();
    header('Location: ' . zavora_asset('seller/login.php?logged_out=1'));
    exit;
}

if (!empty($_GET['logged_out'])) {
    $notice = 'You have safely logged out of your Merchant Hub.';
} elseif (!empty($_GET['timeout'])) {
    $error = 'Your merchant session timed out due to inactivity. Please sign in again.';
}

// Check if already logged in with active valid session
if (zavora_vendor_auth()) {
    $redirect = $_GET['redirect'] ?? zavora_asset('seller/index.php');
    header('Location: ' . $redirect);
    exit;
}

// Handle Merchant Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $redirect = trim($_POST['redirect'] ?? zavora_asset('seller/index.php'));

    if (empty($email) || empty($password)) {
        $error = 'Please enter your merchant email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE email = ?");
        $stmt->execute([$email]);
        $vendor = $stmt->fetch();

        if ($vendor && password_verify($password, $vendor['password_hash'])) {
            if ($vendor['status'] === 'suspended') {
                $error = 'Your merchant store account is currently suspended. Please contact operations support.';
            } else {
                // Session fixation defense: regenerate session ID
                @session_regenerate_id(true);

                $_SESSION['zavora_vendor_id'] = (int)$vendor['id'];
                $_SESSION['zavora_vendor_name'] = $vendor['store_name'];
                $_SESSION['zavora_vendor_last_activity'] = time();

                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $error = 'Invalid merchant email address or password.';
        }
    }
}

$title = 'Seller Portal Login';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-12 max-w-md">
  
  <div class="bg-white rounded-3xl p-8 border border-gray-200 shadow-sm text-center">
    
    <div class="w-16 h-16 bg-[#1B2299]/10 text-[#1B2299] rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
      <i class="fas fa-store"></i>
    </div>
    
    <span class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[11px] font-black uppercase tracking-wider mb-2 inline-block">
      Merchant Portal
    </span>
    <h1 class="text-2xl font-black text-[#0B132B] font-display mb-1">Seller Hub Sign In</h1>
    <p class="text-xs text-gray-500 mb-6">Manage your products, AI studio generations, orders, and bank payouts.</p>

    <?php if ($notice): ?>
      <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs font-bold text-left flex items-center gap-2">
        <i class="fas fa-circle-check text-emerald-600"></i>
        <span><?php echo htmlspecialchars($notice); ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-bold text-left flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-600"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4 text-left">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? zavora_asset('seller/index.php')); ?>">
      
      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Email Address</label>
        <div class="relative">
          <input type="email" name="email" required placeholder="merchant@yourdomain.com"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
          <i class="far fa-envelope absolute left-3.5 top-3.5 text-gray-400"></i>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Password</label>
        <div class="relative">
          <input type="password" name="password" required placeholder="••••••••••••"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
          <i class="fas fa-lock absolute left-3.5 top-3.5 text-gray-400"></i>
        </div>
      </div>

      <button type="submit" class="w-full py-3 bg-[#1B2299] hover:bg-[#0B132B] text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2">
        <i class="fas fa-arrow-right-to-bracket"></i>
        <span>Sign In to Merchant Hub</span>
      </button>
    </form>

    <div class="mt-8 pt-6 border-t border-gray-100 text-xs text-gray-500">
      Want to sell on ZAVORA? 
      <a href="<?php echo zavora_asset('seller/register.php'); ?>" class="text-[#1B2299] font-bold hover:underline">
        Register Your Store
      </a>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
