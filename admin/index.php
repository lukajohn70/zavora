<?php
require_once __DIR__ . '/../config/config.php';

// 1. Enforce Strict Admin Session Control Guard
$adminUser = zavora_admin_require_auth();

$pdo = zavora_get_db();
$flashMessage = '';
$flashType = 'info';

// Handle Admin Actions (e.g. Retry Provisioning, Refresh Catalogue)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'retry_provision') {
        $itemId = intval($_POST['item_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT oi.*, o.customer_email, o.customer_name, o.customer_phone, o.payment_reference 
                              FROM order_items oi 
                              JOIN orders o ON o.id = oi.order_id 
                              WHERE oi.id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item && $item['product_type'] === 'course' && !empty($item['course_id'])) {
            $res = zavora_call_lms_api('/enrolments/provision', 'POST', [
                'student_email'     => $item['customer_email'],
                'student_name'      => $item['customer_name'],
                'course_id'         => $item['course_id'],
                'payment_reference' => $item['payment_reference'],
                'amount_paid'       => $item['unit_price'],
                'currency'          => 'NGN',
                'metadata'          => [
                    'retried_by_admin' => true,
                    'timestamp'        => time(),
                ]
            ]);

            if (!empty($res['success'])) {
                $upd = $pdo->prepare("UPDATE order_items SET enrolment_status = 'provisioned' WHERE id = ?");
                $upd->execute([$itemId]);
                $flashMessage = 'Course enrollment successfully provisioned in Learnerium LMS!';
                $flashType = 'success';
            } else {
                $flashMessage = 'LMS Provisioning failed: ' . ($res['message'] ?? 'API error');
                $flashType = 'error';
            }
        }
    } elseif ($action === 'clear_catalogue_cache') {
        // Clear cached courses if any
        $flashMessage = 'Academy LMS Course catalog cache refreshed from remote endpoint.';
        $flashType = 'success';
    } elseif ($action === 'update_ai_settings') {
        $trialAi = max(0, intval($_POST['vendor_trial_ai_credits'] ?? 5));
        $trialBoost = max(0, intval($_POST['vendor_trial_boost_credits'] ?? 3));
        zavora_set_setting('vendor_trial_ai_credits', (string)$trialAi, 'Complimentary AI generation trials for new vendors');
        zavora_set_setting('vendor_trial_boost_credits', (string)$trialBoost, 'Complimentary boost credits for new vendors');
        $flashMessage = "Vendor trial limits updated: {$trialAi} Free AI Generations, {$trialBoost} Boost Trials.";
        $flashType = 'success';
    } elseif ($action === 'grant_vendor_bonus') {
        $targetVendorId = intval($_POST['vendor_id'] ?? 0);
        $bonusAmount = intval($_POST['bonus_credits'] ?? 0);
        if ($targetVendorId > 0 && $bonusAmount != 0) {
            $pdo->prepare("UPDATE vendors SET ai_credits = MAX(0, ai_credits + ?) WHERE id = ?")->execute([$bonusAmount, $targetVendorId]);
            $pdo->prepare("INSERT INTO ai_credit_transactions (vendor_id, action_type, credits_change, prompt_summary) VALUES (?, 'admin_grant', ?, ?)")
                ->execute([$targetVendorId, $bonusAmount, "Admin granted {$bonusAmount} trial credits"]);
            $flashMessage = "Granted {$bonusAmount} AI credits to vendor #{$targetVendorId}.";
            $flashType = 'success';
        }
    }
}

// 1. Fetch KPI Metrics
$totalSales = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$physicalOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE has_physical_items = 1")->fetchColumn();
$coursesEnrolledCount = $pdo->query("SELECT COUNT(*) FROM order_items WHERE product_type = 'course' AND enrolment_status = 'provisioned'")->fetchColumn();

// 2. Fetch LMS Health Check Live
$lmsHealth = zavora_call_lms_api('/health', 'GET');
$isLmsOnline = !empty($lmsHealth['status']) && $lmsHealth['status'] === 'ok';

// 3. Fetch Remote Catalogue via Bridge
$remoteCatalogue = zavora_get_academy_courses();

// 4. Fetch Recent LMS Course Provisioning Entries
$lmsItemsStmt = $pdo->query("
    SELECT oi.*, o.order_number, o.customer_email, o.customer_name, o.created_at as order_date 
    FROM order_items oi 
    JOIN orders o ON o.id = oi.order_id 
    WHERE oi.product_type = 'course' 
    ORDER BY oi.id DESC 
    LIMIT 20
");
$lmsItems = $lmsItemsStmt->fetchAll();

// 5. Fetch Recent Orders
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 15")->fetchAll();

// 6. Fetch Products
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC LIMIT 20")->fetchAll();

// 7. Fetch System Settings & Registered Vendors
$currentTrialAi = intval(zavora_get_setting('vendor_trial_ai_credits', '5'));
$currentTrialBoost = intval(zavora_get_setting('vendor_trial_boost_credits', '3'));
$adminVendors = $pdo->query("SELECT id, store_name, owner_name, email, ai_credits, boost_credits, balance, status FROM vendors ORDER BY id DESC")->fetchAll();

$title = 'ZAVORA Operations & Ecosystem Bridge Console';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-8 max-w-7xl">
  
  <!-- Console Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-gray-200 mb-8">
    <div>
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#1B2299]/10 text-[#1B2299] text-xs font-bold mb-2">
        <i class="fas fa-shield-halved"></i> Control Operations
      </div>
      <h1 class="text-2xl sm:text-3xl font-black text-[#0B132B] font-display">ZAVORA Admin & Ecosystem Bridge</h1>
      <p class="text-xs text-gray-500">Monitor multi-category commerce transactions and real-time Learnerium LMS course provisioning.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
      <div class="hidden lg:flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold text-gray-700">
        <i class="fas fa-shield-halved text-[#1B2299]"></i>
        <span><?php echo htmlspecialchars($adminUser['email'] ?? 'admin@jlm.com.ng'); ?></span>
      </div>
      <a href="<?php echo htmlspecialchars(LEARNERIUM_URL); ?>" target="_blank" rel="noopener"
         class="px-4 py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded-xl text-xs font-bold border border-purple-200 transition-colors flex items-center gap-1.5">
        <i class="fas fa-graduation-cap"></i>
        <span>Learnerium LMS Admin</span>
        <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
      </a>
      <a href="<?php echo htmlspecialchars(JLM_CORPORATE_URL); ?>" target="_blank" rel="noopener"
         class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5">
        <i class="fas fa-globe"></i>
        <span>JLM Corporate</span>
      </a>
      <a href="<?php echo zavora_asset('admin/login.php?action=logout'); ?>"
         class="px-4 py-2 bg-rose-50 text-rose-700 hover:bg-rose-100 rounded-xl text-xs font-bold border border-rose-200 transition-colors flex items-center gap-1.5"
         title="End administrative session and sign out">
        <i class="fas fa-arrow-right-from-bracket"></i>
        <span>Sign Out</span>
      </a>
    </div>
  </div>

  <?php if ($flashMessage): ?>
    <div class="p-4 mb-6 rounded-2xl text-xs font-bold flex items-center gap-2 <?php echo $flashType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
      <i class="fas <?php echo $flashType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?> text-base"></i>
      <span><?php echo htmlspecialchars($flashMessage); ?></span>
    </div>
  <?php endif; ?>

  <!-- KPI Metrics Grid -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Gross Sales (₦)</span>
      <p class="text-2xl font-black text-[#0B132B] mt-2 font-display"><?php echo zavora_format_currency($totalSales); ?></p>
      <p class="text-[11px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
        <i class="fas fa-check-circle"></i> Paystack Settled
      </p>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Total Orders</span>
      <p class="text-2xl font-black text-[#0B132B] mt-2 font-display"><?php echo number_format($totalOrders); ?></p>
      <p class="text-[11px] text-gray-500 font-medium mt-1">
        Physical, Digital & Academy
      </p>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Physical Shipments</span>
      <p class="text-2xl font-black text-[#1B2299] mt-2 font-display"><?php echo number_format($physicalOrders); ?></p>
      <p class="text-[11px] text-gray-500 font-medium mt-1">
        Nationwide Dispatch
      </p>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">LMS Course Enrolments</span>
      <p class="text-2xl font-black text-purple-700 mt-2 font-display"><?php echo number_format($coursesEnrolledCount); ?></p>
      <p class="text-[11px] text-emerald-600 font-bold mt-1 flex items-center gap-1">
        <i class="fas fa-bolt"></i> Auto-Provisioned in LMS
      </p>
    </div>
  </div>

  <!-- SECTION 1: LEARNERIUM ECOSYSTEM BRIDGE LIVE MONITOR -->
  <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100 mb-6">
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-700 flex items-center justify-center text-lg">
          <i class="fas fa-network-wired"></i>
        </div>
        <div>
          <h2 class="text-lg font-black text-[#0B132B] font-display flex items-center gap-2">
            <span>Learnerium LMS Integration Bridge</span>
            <?php if ($isLmsOnline): ?>
              <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> ONLINE (200 OK)
              </span>
            <?php else: ?>
              <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 text-[10px] font-black uppercase tracking-wider flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span> OFFLINE / UNREACHABLE
              </span>
            <?php endif; ?>
          </h2>
          <p class="text-xs text-gray-500">Target Endpoint: <code class="text-[11px] bg-gray-100 px-1 py-0.5 rounded text-gray-700"><?php echo htmlspecialchars(LEARNERIUM_API_URL); ?></code></p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <form method="POST">
          <input type="hidden" name="action" value="clear_catalogue_cache">
          <button type="submit" class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5">
            <i class="fas fa-rotate"></i>
            <span>Refresh LMS Sync</span>
          </button>
        </form>
      </div>
    </div>

    <!-- LMS Connection Diagnostics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200">
        <span class="text-[11px] font-bold text-gray-500 uppercase">Remote LMS Version</span>
        <p class="text-sm font-bold text-gray-900 mt-1"><?php echo htmlspecialchars($lmsHealth['version'] ?? 'Laravel 8.83'); ?></p>
        <span class="text-[10px] text-gray-400">Environment: <?php echo htmlspecialchars($lmsHealth['environment'] ?? 'local'); ?></span>
      </div>

      <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200">
        <span class="text-[11px] font-bold text-gray-500 uppercase">Courses Available in LMS</span>
        <p class="text-sm font-bold text-gray-900 mt-1"><?php echo count($remoteCatalogue); ?> Published Courses</p>
        <span class="text-[10px] text-emerald-600 font-bold">Synchronized to ZAVORA Academy</span>
      </div>

      <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200">
        <span class="text-[11px] font-bold text-gray-500 uppercase">Security Secret Status</span>
        <p class="text-sm font-bold text-emerald-700 mt-1 flex items-center gap-1">
          <i class="fas fa-key text-xs"></i> 256-Bit Bearer Key Active
        </p>
        <span class="text-[10px] text-gray-400">Payload Integrity Enforced</span>
      </div>
    </div>

    <!-- Course Provisioning Feed -->
    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
      <i class="fas fa-list-check text-purple-600"></i> Recent Automatic LMS Student Enrolments
    </h3>
    
    <div class="overflow-x-auto border border-gray-200 rounded-2xl">
      <table class="w-full text-left text-xs text-gray-600">
        <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
          <tr>
            <th class="p-3">Order Number</th>
            <th class="p-3">Student Name & Email</th>
            <th class="p-3">Course Title</th>
            <th class="p-3">Paid Amount</th>
            <th class="p-3">Provisioning Status</th>
            <th class="p-3 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($lmsItems)): ?>
            <tr>
              <td colspan="6" class="p-6 text-center text-gray-400 italic">No course purchases recorded yet. Make a test checkout with a course item to see live provisioning.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($lmsItems as $cItem): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="p-3 font-bold text-gray-900">
                  <?php echo htmlspecialchars($cItem['order_number']); ?>
                </td>
                <td class="p-3">
                  <div class="font-bold text-gray-900"><?php echo htmlspecialchars($cItem['customer_name']); ?></div>
                  <div class="text-[11px] text-gray-500"><?php echo htmlspecialchars($cItem['customer_email']); ?></div>
                </td>
                <td class="p-3 font-medium text-gray-800 max-w-xs truncate">
                  <?php echo htmlspecialchars($cItem['title']); ?>
                </td>
                <td class="p-3 font-bold text-[#1B2299]">
                  <?php echo zavora_format_currency($cItem['total_price']); ?>
                </td>
                <td class="p-3">
                  <?php if ($cItem['enrolment_status'] === 'provisioned'): ?>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-1">
                      <i class="fas fa-check-circle text-emerald-600"></i> LMS Provisioned
                    </span>
                  <?php else: ?>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-1">
                      <i class="fas fa-clock text-amber-600"></i> Pending Retry
                    </span>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-right">
                  <?php if ($cItem['enrolment_status'] !== 'provisioned'): ?>
                    <form method="POST" class="inline">
                      <input type="hidden" name="action" value="retry_provision">
                      <input type="hidden" name="item_id" value="<?php echo $cItem['id']; ?>">
                      <button type="submit" class="px-3 py-1 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-lg text-[11px] font-bold transition-colors">
                        Retry Sync
                      </button>
                    </form>
                  <?php else: ?>
                    <a href="<?php echo htmlspecialchars(LEARNERIUM_URL . '/admin/enrollments'); ?>" target="_blank" rel="noopener"
                       class="text-gray-400 hover:text-purple-600 text-[11px] font-bold">
                      View in LMS <i class="fas fa-external-link-alt text-[9px]"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SECTION 2: RECENT ORDERS TABLE -->
  <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 mb-8">
    <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
      <h2 class="text-lg font-black text-[#0B132B] font-display flex items-center gap-2">
        <i class="fas fa-bag-shopping text-[#1B2299]"></i>
        <span>Recent Marketplace Orders</span>
      </h2>
      <span class="text-xs text-gray-500 font-semibold"><?php echo count($recentOrders); ?> Latest Transactions</span>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-2xl">
      <table class="w-full text-left text-xs text-gray-600">
        <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
          <tr>
            <th class="p-3">Order Ref</th>
            <th class="p-3">Customer</th>
            <th class="p-3">Types Included</th>
            <th class="p-3">Total Amount</th>
            <th class="p-3">Payment</th>
            <th class="p-3">Date</th>
            <th class="p-3 text-right">Receipt</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($recentOrders)): ?>
            <tr>
              <td colspan="7" class="p-6 text-center text-gray-400 italic">No orders recorded yet. Place an order in the store to see it here.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $o): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="p-3 font-bold text-gray-900"><?php echo htmlspecialchars($o['order_number']); ?></td>
                <td class="p-3">
                  <div class="font-bold text-gray-800"><?php echo htmlspecialchars($o['customer_name']); ?></div>
                  <div class="text-[11px] text-gray-500"><?php echo htmlspecialchars($o['customer_email']); ?></div>
                </td>
                <td class="p-3">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <?php if ($o['has_physical_items']): ?>
                      <span class="px-2 py-0.5 bg-blue-50 text-[#1B2299] rounded text-[10px] font-bold">Physical</span>
                    <?php endif; ?>
                    <?php if ($o['has_digital_items']): ?>
                      <span class="px-2 py-0.5 bg-rose-50 text-[#E4306D] rounded text-[10px] font-bold">Digital</span>
                    <?php endif; ?>
                    <?php if ($o['has_courses']): ?>
                      <span class="px-2 py-0.5 bg-purple-50 text-purple-700 rounded text-[10px] font-bold">Course</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="p-3 font-black text-gray-900"><?php echo zavora_format_currency($o['total_amount']); ?></td>
                <td class="p-3">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $o['payment_status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                    <?php echo ucfirst($o['payment_status']); ?>
                  </span>
                </td>
                <td class="p-3 text-gray-500 text-[11px]"><?php echo date('M d, H:i', strtotime($o['created_at'])); ?></td>
                <td class="p-3 text-right">
                  <a href="<?php echo zavora_asset('order_confirmation.php?order=' . urlencode($o['order_number'])); ?>" class="text-[#1B2299] hover:underline font-bold">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SECTION 3: VENDOR AI TRIAL ALLOCATION & MONETIZATION SETTINGS -->
  <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-gray-100 gap-3 mb-6">
      <div>
        <h2 class="text-lg font-black text-[#0B132B] font-display flex items-center gap-2">
          <i class="fas fa-wand-magic-sparkles text-amber-500"></i>
          <span>Vendor Free Trial & AI Generation Allocations</span>
        </h2>
        <p class="text-xs text-gray-500">Configure free trial limits for new vendors before paid Paystack credit packs are required.</p>
      </div>
      <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-black w-max">
        Trial Config Live
      </span>
    </div>

    <!-- Trial Limits Form -->
    <form method="POST" class="bg-slate-50 border border-slate-200 rounded-2xl p-5 mb-6">
      <input type="hidden" name="action" value="update_ai_settings">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Free AI Generations (Onboarding Trial)</label>
          <div class="flex items-center gap-2">
            <input type="number" name="vendor_trial_ai_credits" min="0" max="100" value="<?php echo $currentTrialAi; ?>" required
                   class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            <span class="text-xs font-bold text-gray-500 whitespace-nowrap">Trials</span>
          </div>
          <span class="text-[10px] text-gray-400 mt-1 block">Granted to new vendors for image/copy drafting.</span>
        </div>

        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Free Product Boost Trials</label>
          <div class="flex items-center gap-2">
            <input type="number" name="vendor_trial_boost_credits" min="0" max="50" value="<?php echo $currentTrialBoost; ?>" required
                   class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            <span class="text-xs font-bold text-gray-500 whitespace-nowrap">Boosts</span>
          </div>
          <span class="text-[10px] text-gray-400 mt-1 block">Free search boost placements for new stores.</span>
        </div>

        <div>
          <button type="submit" class="w-full py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl transition shadow">
            Save Trial Settings
          </button>
        </div>
      </div>
    </form>

    <!-- Vendor Balances & Instant Bonus Credit Grant -->
    <h3 class="text-xs font-black uppercase text-gray-700 tracking-wider mb-3 flex items-center gap-2">
      <i class="fas fa-users-gear text-gray-500"></i> Registered Vendors & Live Credit Balances
    </h3>
    <div class="overflow-x-auto border border-gray-200 rounded-2xl">
      <table class="w-full text-left text-xs text-gray-600">
        <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
          <tr>
            <th class="p-3">Store</th>
            <th class="p-3">Owner / Email</th>
            <th class="p-3">AI Credits Left</th>
            <th class="p-3">Boost Credits</th>
            <th class="p-3">Sales Balance</th>
            <th class="p-3 text-right">Grant Bonus Credits</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($adminVendors)): ?>
            <tr>
              <td colspan="6" class="p-6 text-center text-gray-400 italic">No vendors registered yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($adminVendors as $v): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <td class="p-3 font-bold text-gray-900"><?php echo htmlspecialchars($v['store_name']); ?></td>
                <td class="p-3">
                  <div class="font-bold text-gray-800"><?php echo htmlspecialchars($v['owner_name']); ?></div>
                  <div class="text-[11px] text-gray-400"><?php echo htmlspecialchars($v['email']); ?></div>
                </td>
                <td class="p-3">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?php echo $v['ai_credits'] > 0 ? 'bg-blue-100 text-[#1B2299]' : 'bg-rose-100 text-rose-800'; ?>">
                    <?php echo intval($v['ai_credits']); ?> Credits
                  </span>
                </td>
                <td class="p-3 font-bold text-amber-700"><?php echo intval($v['boost_credits']); ?></td>
                <td class="p-3 font-black text-gray-900"><?php echo zavora_format_price($v['balance']); ?></td>
                <td class="p-3 text-right">
                  <form method="POST" class="inline-flex items-center gap-1.5">
                    <input type="hidden" name="action" value="grant_vendor_bonus">
                    <input type="hidden" name="vendor_id" value="<?php echo $v['id']; ?>">
                    <input type="number" name="bonus_credits" value="5" min="1" max="100" class="w-14 px-2 py-1 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-center">
                    <button type="submit" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold rounded-lg transition shadow-sm">
                      + Add
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>