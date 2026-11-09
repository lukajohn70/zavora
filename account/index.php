<?php
/**
 * ZAVORA Customer Account Portal — Secure Session Guarded
 *
 * Enforces session-only customer access. Query parameter bypasses (?email=...)
 * are strictly disabled. Order tracking requires identity verification
 * (matching email or phone number).
 */

require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();
$error = '';
$notice = '';
$otpSent = false;
$pendingEmail = '';

// Handle Sign Out
if ((isset($_GET['action']) && $_GET['action'] === 'signout') || 
    ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'signout')) {
    zavora_customer_logout();
    header('Location: ' . zavora_asset('account/index.php?signed_out=1'));
    exit;
}

if (!empty($_GET['signed_out'])) {
    $notice = 'You have safely signed out of your customer account.';
}

// 1. Check Authenticated Session
$customerEmail = zavora_customer_auth();

// 2. Handle Unauthenticated Verification Actions
if (!$customerEmail && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Send 6-digit One-Time Access Passcode (OTP)
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['zavora_customer_otp'] = $otp;
            $_SESSION['zavora_customer_otp_email'] = $email;
            $_SESSION['zavora_customer_otp_time'] = time();

            $otpSent = true;
            $pendingEmail = $email;
            $notice = "A 6-digit security code has been generated for {$email}. (Verification Code: {$otp})";
        }
    }
    // Action B: Verify 6-digit OTP
    elseif ($action === 'verify_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $code = trim($_POST['otp_code'] ?? '');
        $savedOtp = $_SESSION['zavora_customer_otp'] ?? '';
        $savedEmail = $_SESSION['zavora_customer_otp_email'] ?? '';
        $savedTime = (int)($_SESSION['zavora_customer_otp_time'] ?? 0);

        if (empty($code) || empty($email)) {
            $error = 'Please enter the 6-digit verification code.';
            $otpSent = true;
            $pendingEmail = $email;
        } elseif ($email !== $savedEmail || $code !== $savedOtp || (time() - $savedTime) > 900) {
            $error = 'The verification code is invalid or has expired. Please request a new code.';
            $otpSent = true;
            $pendingEmail = $email;
        } else {
            // Success: establish customer session
            @session_regenerate_id(true);
            $_SESSION['zavora_customer_email'] = $email;
            unset($_SESSION['zavora_customer_otp'], $_SESSION['zavora_customer_otp_email'], $_SESSION['zavora_customer_otp_time']);
            header('Location: ' . zavora_asset('account/index.php'));
            exit;
        }
    }
    // Action C: Order Lookup with Phone or Email Verification
    elseif ($action === 'verify_order') {
        $orderNumber = trim($_POST['order_number'] ?? '');
        $verifyPhoneOrEmail = trim($_POST['verify_identifier'] ?? '');

        if (empty($orderNumber) || empty($verifyPhoneOrEmail)) {
            $error = 'Please enter both your Order Number and billing Phone or Email.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
            $stmt->execute([$orderNumber]);
            $foundOrder = $stmt->fetch();

            if (!$foundOrder) {
                $error = 'No order found matching that reference number.';
            } else {
                $enteredClean = strtolower(trim($verifyPhoneOrEmail));
                $orderEmailClean = strtolower(trim($foundOrder['customer_email']));
                $orderPhoneClean = preg_replace('/\D/', '', $foundOrder['customer_phone'] ?? '');
                $enteredPhoneClean = preg_replace('/\D/', '', $verifyPhoneOrEmail);

                $matched = ($enteredClean === $orderEmailClean) 
                    || (!empty($enteredPhoneClean) && strlen($enteredPhoneClean) >= 7 && strpos($orderPhoneClean, $enteredPhoneClean) !== false);

                if ($matched) {
                    @session_regenerate_id(true);
                    $_SESSION['zavora_customer_email'] = $foundOrder['customer_email'];
                    $_SESSION['zavora_recent_orders'][$orderNumber] = true;
                    header('Location: ' . zavora_asset('account/index.php?order=' . urlencode($orderNumber)));
                    exit;
                } else {
                    $error = 'Verification details do not match the customer record for this order.';
                }
            }
        }
    }
}

// 3. Fetch Orders & Assets if authenticated
$orders = [];
$coursesEnrolled = [];
$digitalDownloads = [];
$targetOrderNumber = trim($_GET['order'] ?? '');

if ($customerEmail) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY id DESC");
    $stmt->execute([$customerEmail]);
    $orders = $stmt->fetchAll();

    if (!empty($orders)) {
        $orderIds = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        
        $itemStmt = $pdo->prepare("SELECT oi.*, o.order_number, o.created_at as order_date 
                                  FROM order_items oi 
                                  JOIN orders o ON o.id = oi.order_id 
                                  WHERE oi.order_id IN ($placeholders) 
                                  ORDER BY oi.id DESC");
        $itemStmt->execute($orderIds);
        $allItems = $itemStmt->fetchAll();

        foreach ($allItems as $item) {
            if ($item['product_type'] === 'course') {
                $coursesEnrolled[] = $item;
            } elseif ($item['product_type'] === 'digital') {
                $digitalDownloads[] = $item;
            }
        }
    }
}

$title = 'My Account & Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-8 max-w-6xl">
  
  <!-- Breadcrumbs -->
  <nav class="flex items-center gap-2 text-xs text-gray-500 mb-6">
    <a href="<?php echo zavora_asset('index.php'); ?>" class="hover:text-[#1B2299]">Home</a>
    <span>/</span>
    <span class="text-gray-900 font-semibold">Customer Account</span>
  </nav>

  <?php if (!$customerEmail): ?>
  <!-- Unauthenticated Access / Session Gateway -->
  <div class="max-w-xl mx-auto">
    
    <div class="bg-white rounded-3xl p-8 sm:p-10 border border-gray-200 shadow-sm text-center">
      <div class="w-16 h-16 bg-[#1B2299]/10 text-[#1B2299] rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
        <i class="fas fa-user-lock"></i>
      </div>
      
      <span class="px-3 py-1 rounded-full bg-blue-50 text-[#1B2299] border border-blue-200 text-[10px] font-black uppercase tracking-widest inline-block mb-2">
        Verified Customer Sign In
      </span>
      <h1 class="text-2xl sm:text-3xl font-black text-[#0B132B] font-display mb-2">Access Your Account</h1>
      <p class="text-xs text-gray-500 mb-6">
        To protect customer privacy, session authentication is required. Direct URL access without verification is blocked.
      </p>

      <?php if ($notice): ?>
        <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs font-semibold text-emerald-800 text-left flex items-start gap-2">
          <i class="fas fa-circle-check text-emerald-600 mt-0.5"></i>
          <span><?php echo htmlspecialchars($notice); ?></span>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-xs font-semibold text-rose-800 text-left flex items-start gap-2">
          <i class="fas fa-triangle-exclamation text-rose-600 mt-0.5"></i>
          <span><?php echo htmlspecialchars($error); ?></span>
        </div>
      <?php endif; ?>

      <!-- Flow Option 1: One-Time Passcode (OTP) -->
      <?php if (!$otpSent): ?>
        <form method="POST" class="space-y-4 text-left">
          <input type="hidden" name="action" value="send_otp">
          <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Checkout Email Address</label>
            <div class="relative">
              <input type="email" name="email" required placeholder="Enter the email used during checkout"
                     class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
              <i class="far fa-envelope absolute left-3.5 top-3.5 text-gray-400"></i>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">We will send a secure 6-digit access code to this address.</p>
          </div>
          <button type="submit" class="w-full py-3.5 bg-[#1B2299] hover:bg-[#0B132B] text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2">
            <i class="fas fa-paper-plane text-xs"></i>
            <span>Send 6-Digit Access Code</span>
          </button>
        </form>
      <?php else: ?>
        <form method="POST" class="space-y-4 text-left">
          <input type="hidden" name="action" value="verify_otp">
          <input type="hidden" name="email" value="<?php echo htmlspecialchars($pendingEmail); ?>">
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">6-Digit Code for <?php echo htmlspecialchars($pendingEmail); ?></label>
              <a href="<?php echo zavora_asset('account/index.php'); ?>" class="text-[11px] text-[#1B2299] font-bold hover:underline">Change Email</a>
            </div>
            <div class="relative">
              <input type="text" name="otp_code" required maxlength="6" pattern="[0-9]{6}" autofocus
                     placeholder="123456"
                     class="w-full text-center tracking-widest font-mono text-xl py-3 bg-gray-50 border border-gray-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
            </div>
          </div>
          <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm transition-all shadow-md flex items-center justify-center gap-2">
            <i class="fas fa-shield-check"></i>
            <span>Verify & Open My Account</span>
          </button>
        </form>
      <?php endif; ?>

      <!-- Flow Option 2: Order Reference Verification (Phone or Email) -->
      <div class="mt-8 pt-6 border-t border-gray-100 text-left">
        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
          <i class="fas fa-receipt text-[#1B2299]"></i> Instant Order Verification
        </h3>
        <p class="text-[11px] text-gray-500 mb-3">Track a specific purchase with your Order Number and verified phone or email:</p>
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="verify_order">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <input type="text" name="order_number" required placeholder="Order ID (e.g. ZAV-1731...)" 
                   value="<?php echo htmlspecialchars($_GET['order'] ?? ''); ?>"
                   class="px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            <input type="text" name="verify_identifier" required placeholder="Billing Phone or Email" 
                   class="px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          </div>
          <button type="submit" class="w-full py-2.5 bg-gray-800 hover:bg-black text-white rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1.5">
            <i class="fas fa-magnifying-glass"></i>
            <span>Verify & View Order Details</span>
          </button>
        </form>
      </div>

    </div>

  </div>

  <?php else: ?>
  <!-- Authenticated Customer Hub -->
  <div class="bg-gradient-to-r from-[#0B132B] to-[#1B2299] rounded-3xl p-6 sm:p-8 text-white mb-8 shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-6">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-2xl font-black text-[#F7DE7A]">
        <?php echo strtoupper(substr($customerEmail, 0, 1)); ?>
      </div>
      <div>
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#16865B]/20 text-emerald-300 border border-[#16865B]/30 text-[11px] font-bold mb-1">
          <i class="fas fa-shield-check"></i> Session Authenticated Customer
        </div>
        <h1 class="text-xl sm:text-2xl font-black font-display"><?php echo htmlspecialchars($customerEmail); ?></h1>
        <p class="text-xs text-gray-300">ZAVORA Ecosystem Member • Synchronized with Learnerium LMS</p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?php echo zavora_asset('account/index.php?action=signout'); ?>" 
         class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-xl border border-white/20 transition-all flex items-center gap-1.5">
        <i class="fas fa-arrow-right-from-bracket"></i> Sign Out
      </a>
    </div>
  </div>

  <!-- Metric Badges -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#1B2299] flex items-center justify-center text-xl">
        <i class="fas fa-box"></i>
      </div>
      <div>
        <p class="text-xs text-gray-500 font-medium">Total Orders</p>
        <p class="text-xl font-black text-[#0B132B]"><?php echo count($orders); ?></p>
      </div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
        <i class="fas fa-graduation-cap"></i>
      </div>
      <div>
        <p class="text-xs text-gray-500 font-medium">Enrolled Courses</p>
        <p class="text-xl font-black text-[#0B132B]"><?php echo count($coursesEnrolled); ?></p>
      </div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-rose-50 text-[#E4306D] flex items-center justify-center text-xl">
        <i class="fas fa-cloud-arrow-down"></i>
      </div>
      <div>
        <p class="text-xs text-gray-500 font-medium">Digital Assets</p>
        <p class="text-xl font-black text-[#0B132B]"><?php echo count($digitalDownloads); ?></p>
      </div>
    </div>
  </div>

  <!-- Account Navigation Tabs -->
  <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden">
    
    <div class="border-b border-gray-200 px-6 pt-4 flex gap-6 overflow-x-auto" id="accountTabs">
      <button onclick="switchTab('courses')" id="tab-btn-courses" class="tab-btn pb-4 font-bold text-sm border-b-2 border-[#1B2299] text-[#1B2299] flex items-center gap-2">
        <i class="fas fa-graduation-cap"></i>
        <span>My Courses (Academy)</span>
        <span class="px-2 py-0.5 rounded-full bg-blue-100 text-[#1B2299] text-[11px] font-black"><?php echo count($coursesEnrolled); ?></span>
      </button>

      <button onclick="switchTab('orders')" id="tab-btn-orders" class="tab-btn pb-4 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-900 flex items-center gap-2">
        <i class="fas fa-bag-shopping"></i>
        <span>Order History</span>
        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] font-bold"><?php echo count($orders); ?></span>
      </button>

      <button onclick="switchTab('downloads')" id="tab-btn-downloads" class="tab-btn pb-4 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-900 flex items-center gap-2">
        <i class="fas fa-download"></i>
        <span>Digital Downloads</span>
        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] font-bold"><?php echo count($digitalDownloads); ?></span>
      </button>
    </div>

    <!-- Tab 1: Academy Courses -->
    <div id="tab-courses" class="p-6">
      <?php if (empty($coursesEnrolled)): ?>
        <div class="py-12 text-center">
          <i class="fas fa-graduation-cap text-4xl text-gray-300 mb-3"></i>
          <h3 class="text-base font-bold text-gray-800 mb-1">No Courses Enrolled Yet</h3>
          <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">You haven't enrolled in any online courses on ZAVORA Academy yet. Explore high-impact tech and business courses.</p>
          <a href="<?php echo zavora_asset('academy.php'); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1B2299] text-white rounded-xl text-xs font-bold hover:bg-[#0B132B] transition-colors">
            <i class="fas fa-compass"></i> Explore ZAVORA Academy
          </a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <?php foreach ($coursesEnrolled as $course): ?>
            <div class="bg-gray-50 rounded-2xl p-5 border border-gray-200 flex flex-col justify-between hover:shadow-md transition-shadow">
              <div>
                <div class="flex items-center justify-between gap-2 mb-2">
                  <span class="px-2.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                    <i class="fas fa-check-circle text-emerald-600"></i> Active Access
                  </span>
                  <span class="text-[11px] text-gray-500">Order: <?php echo htmlspecialchars($course['order_number']); ?></span>
                </div>
                <h3 class="text-base font-bold text-[#0B132B] mb-2 leading-snug">
                  <?php echo htmlspecialchars($course['title']); ?>
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                  Enrolled via ZAVORA Commerce on <?php echo date('M d, Y', strtotime($course['order_date'])); ?>
                </p>
              </div>

              <div class="pt-4 border-t border-gray-200/60 flex items-center justify-between gap-2">
                <?php $ssoLaunchUrl = zavora_generate_lms_sso_url($customerEmail, null, $course['course_id']); ?>
                <span class="text-xs font-bold text-[#1B2299] flex items-center gap-1">
                  <i class="fas fa-bolt text-amber-500"></i> Instant 1-Click Access
                </span>
                <a href="<?php echo htmlspecialchars($ssoLaunchUrl); ?>" target="_blank" rel="noopener"
                   class="px-4 py-2 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-1.5 shadow-sm">
                  <span>Launch Course</span>
                  <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Tab 2: All Orders -->
    <div id="tab-orders" class="p-6 hidden">
      <?php if (empty($orders)): ?>
        <div class="py-12 text-center text-gray-500">
          <i class="fas fa-shopping-bag text-4xl text-gray-300 mb-3"></i>
          <p class="text-sm font-semibold">No orders found.</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($orders as $order): ?>
            <div class="border border-gray-200 rounded-2xl p-5 hover:border-gray-300 transition-colors bg-white">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 pb-3 mb-3">
                <div>
                  <span class="text-xs text-gray-400">Order Number</span>
                  <p class="text-sm font-bold text-[#0B132B]"><?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
                <div>
                  <span class="text-xs text-gray-400">Date Placed</span>
                  <p class="text-xs font-medium text-gray-700"><?php echo date('M d, Y - h:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <div>
                  <span class="text-xs text-gray-400">Total Paid</span>
                  <p class="text-sm font-black text-[#1B2299]"><?php echo zavora_format_currency($order['total_amount']); ?></p>
                </div>
                <div>
                  <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $order['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                    <?php echo ucfirst($order['payment_status']); ?>
                  </span>
                </div>
              </div>

              <div class="text-xs text-gray-600 flex flex-wrap gap-4 items-center justify-between">
                <div>
                  <?php if ($order['has_physical_items']): ?>
                    <span class="inline-flex items-center gap-1 text-gray-700 mr-3">
                      <i class="fas fa-truck text-[#1B2299]"></i> Delivery to: <?php echo htmlspecialchars($order['delivery_state'] ?: 'Lagos'); ?>
                    </span>
                  <?php endif; ?>
                  <?php if ($order['has_courses']): ?>
                    <span class="inline-flex items-center gap-1 text-purple-700 mr-3">
                      <i class="fas fa-graduation-cap"></i> LMS Provisioned
                    </span>
                  <?php endif; ?>
                  <?php if ($order['has_digital_items']): ?>
                    <span class="inline-flex items-center gap-1 text-rose-700">
                      <i class="fas fa-download"></i> Instant Digital Access
                    </span>
                  <?php endif; ?>
                </div>

                <a href="<?php echo zavora_asset('order_confirmation.php?order=' . urlencode($order['order_number'])); ?>" class="text-[#1B2299] hover:underline font-bold inline-flex items-center gap-1">
                  <span>View Full Receipt</span>
                  <i class="fas fa-chevron-right text-[10px]"></i>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Tab 3: Digital Downloads -->
    <div id="tab-downloads" class="p-6 hidden">
      <?php if (empty($digitalDownloads)): ?>
        <div class="py-12 text-center">
          <i class="fas fa-cloud-arrow-down text-4xl text-gray-300 mb-3"></i>
          <h3 class="text-base font-bold text-gray-800 mb-1">No Digital Downloads Available</h3>
          <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">When you purchase digital templates, code starters, or e-books, their secure download links will appear here.</p>
          <a href="<?php echo zavora_asset('shop.php?type=digital'); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#E4306D] text-white rounded-xl text-xs font-bold hover:bg-[#0B132B] transition-colors">
            <i class="fas fa-store"></i> Browse Digital Products
          </a>
        </div>
      <?php else: ?>
        <div class="space-y-3">
          <?php foreach ($digitalDownloads as $dItem): ?>
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-200">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#E4306D]/10 text-[#E4306D] flex items-center justify-center text-lg">
                  <i class="fas fa-file-zipper"></i>
                </div>
                <div>
                  <h4 class="text-sm font-bold text-[#0B132B]"><?php echo htmlspecialchars($dItem['title']); ?></h4>
                  <p class="text-[11px] text-gray-500">Order: <?php echo htmlspecialchars($dItem['order_number']); ?> • Unlimited Redownloads</p>
                </div>
              </div>
              <?php 
                $downloadUrl = !empty($dItem['digital_file_url']) ? $dItem['digital_file_url'] : zavora_asset('order_confirmation.php?order=' . urlencode($dItem['order_number']));
              ?>
              <a href="<?php echo htmlspecialchars($downloadUrl); ?>"
                 class="px-4 py-2 bg-[#E4306D] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                <i class="fas fa-download"></i>
                <span>Download Asset</span>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <?php endif; ?>

</div>

<script>
function switchTab(tabId) {
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('border-[#1B2299]', 'text-[#1B2299]');
    btn.classList.add('border-transparent', 'text-gray-500');
  });
  const activeBtn = document.getElementById('tab-btn-' + tabId);
  if (activeBtn) {
    activeBtn.classList.add('border-[#1B2299]', 'text-[#1B2299]');
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
  }

  document.getElementById('tab-courses').classList.add('hidden');
  document.getElementById('tab-orders').classList.add('hidden');
  document.getElementById('tab-downloads').classList.add('hidden');

  const target = document.getElementById('tab-' + tabId);
  if (target) {
    target.classList.remove('hidden');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
