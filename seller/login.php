<?php
require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();
$error = '';
$success = '';

// Check if already logged in
if (!empty($_SESSION['zavora_vendor_id'])) {
    header('Location: ' . zavora_asset('seller/index.php'));
    exit;
}

// Handle Login or Demo Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'demo_login') {
        $vendorId = intval($_POST['vendor_id'] ?? 1);
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch();
        if ($vendor) {
            $_SESSION['zavora_vendor_id'] = $vendor['id'];
            $_SESSION['zavora_vendor_name'] = $vendor['store_name'];
            header('Location: ' . zavora_asset('seller/index.php'));
            exit;
        }
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter your vendor email and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM vendors WHERE email = ?");
            $stmt->execute([$email]);
            $vendor = $stmt->fetch();

            if ($vendor && password_verify($password, $vendor['password_hash'])) {
                if ($vendor['status'] === 'suspended') {
                    $error = 'Your merchant store account is currently suspended. Contact vendor support.';
                } else {
                    $_SESSION['zavora_vendor_id'] = $vendor['id'];
                    $_SESSION['zavora_vendor_name'] = $vendor['store_name'];
                    header('Location: ' . zavora_asset('seller/index.php'));
                    exit;
                }
            } else {
                $error = 'Invalid merchant credentials. Try "vendor123" with emeka@apexaudio.ng or click Demo Merchant Login.';
            }
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
      ZAVORA Merchant Central
    </span>
    <h1 class="text-2xl font-black text-[#0B132B] font-display mb-1">Seller Hub Sign In</h1>
    <p class="text-xs text-gray-500 mb-6">Manage your product catalog, orders, and automated bank payouts.</p>

    <?php if ($error): ?>
      <div class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-bold text-left flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-600"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4 text-left">
      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Registered Email</label>
        <div class="relative">
          <input type="email" name="email" required placeholder="store@domain.com"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
          <i class="far fa-envelope absolute left-3.5 top-3 text-gray-400"></i>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Password</label>
        <div class="relative">
          <input type="password" name="password" required placeholder="••••••••"
                 class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
          <i class="fas fa-lock absolute left-3.5 top-3 text-gray-400"></i>
        </div>
      </div>

      <button type="submit" class="w-full py-3 bg-[#1B2299] hover:bg-[#0B132B] text-white font-bold rounded-xl text-sm transition-all shadow-md">
        Access Merchant Portal
      </button>
    </form>

    <!-- 1-Click Quick Demo Sign In -->
    <div class="mt-8 pt-6 border-t border-gray-100">
      <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Instant Demo Merchant Login</p>
      <div class="grid grid-cols-2 gap-2">
        <form method="POST">
          <input type="hidden" name="action" value="demo_login">
          <input type="hidden" name="vendor_id" value="1">
          <button type="submit" class="w-full py-2 px-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 transition-colors">
            Apex Audio & Tech
          </button>
        </form>

        <form method="POST">
          <input type="hidden" name="action" value="demo_login">
          <input type="hidden" name="vendor_id" value="2">
          <button type="submit" class="w-full py-2 px-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 transition-colors">
            JLM Creative Studio
          </button>
        </form>
      </div>
    </div>

    <div class="mt-6 text-xs text-gray-500">
      Want to sell on ZAVORA? 
      <a href="<?php echo zavora_asset('seller/register.php'); ?>" class="text-[#1B2299] font-bold hover:underline">
        Register Your Store
      </a>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>