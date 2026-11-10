<?php
require_once __DIR__ . '/../config/config.php';

// 1. Enforce Strict Vendor Session Control Guard
$vendor = zavora_vendor_require_auth();
$vendorId = (int)$vendor['id'];
$pdo = zavora_get_db();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    zavora_vendor_logout();
    header('Location: ' . zavora_asset('seller/login.php?logged_out=1'));
    exit;
}

$flashMessage = '';
$flashType = 'info';

// List of Standard Nigerian Banks for Payout Selection
$nigerianBanks = [
    'Access Bank',
    'Guaranty Trust Bank (GTBank)',
    'Zenith Bank',
    'First Bank of Nigeria',
    'United Bank for Africa (UBA)',
    'Kuda Microfinance Bank',
    'OPay (PayCom)',
    'PalmPay',
    'Moniepoint MFB',
    'Stanbic IBTC Bank',
    'Fidelity Bank',
    'First City Monument Bank (FCMB)',
    'Sterling Bank',
    'Wema Bank (ALAT)',
    'Union Bank of Nigeria',
    'Polaris Bank',
    'Keystone Bank',
    'Taj Bank'
];

// 2. Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'signout') {
        zavora_vendor_logout();
        header('Location: ' . zavora_asset('seller/login.php?logged_out=1'));
        exit;
    }

    // ── Update Store Profile & Payout Accounts ──────────────────────────────
    if ($action === 'update_profile') {
        $storeName    = trim($_POST['store_name'] ?? '');
        $ownerName    = trim($_POST['owner_name'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $supportEmail = trim($_POST['support_email'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $bankName     = trim($_POST['bank_name'] ?? '');
        $accountNum   = trim($_POST['account_number'] ?? '');
        $accountName  = trim($_POST['account_name'] ?? '');

        if (empty($storeName) || empty($bankName) || empty($accountNum)) {
            $flashMessage = 'Store name, payout bank, and account number are required.';
            $flashType = 'error';
        } else {
            $updStmt = $pdo->prepare("
                UPDATE vendors SET 
                    store_name = ?, owner_name = ?, phone = ?, support_email = ?, 
                    description = ?, bank_name = ?, account_number = ?, account_name = ?
                WHERE id = ?
            ");
            $updStmt->execute([
                $storeName, $ownerName, $phone, $supportEmail, 
                $description, $bankName, $accountNum, $accountName, $vendorId
            ]);

            // Refresh vendor
            $stmt->execute([$vendorId]);
            $vendor = $stmt->fetch();
            $_SESSION['zavora_vendor_name'] = $vendor['store_name'];

            $flashMessage = 'Store profile and bank payout details successfully updated!';
            $flashType = 'success';
        }
    }

    // ── Quick Inventory Adjustment ──────────────────────────────────────────
    if ($action === 'adjust_inventory') {
        $productId = intval($_POST['product_id'] ?? 0);
        $newStock = intval($_POST['new_stock'] ?? 0);

        if ($productId > 0 && $newStock >= 0) {
            $upd = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ? AND vendor_id = ?");
            $upd->execute([$newStock, $productId, $vendorId]);
            $flashMessage = "Stock updated to {$newStock} units for Product #{$productId}.";
            $flashType = 'success';
        }
    }

    // ── Boost / Promote Product ─────────────────────────────────────────────
    if ($action === 'boost_product') {
        $productId = intval($_POST['product_id'] ?? 0);
        $tier = trim($_POST['boost_tier'] ?? 'Sponsored Gold');

        if ($productId > 0) {
            $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
            $pdo->prepare("UPDATE products SET is_boosted = 1, boost_tier = ?, boost_expires_at = ? WHERE id = ? AND vendor_id = ?")
                ->execute([$tier, $expiry, $productId, $vendorId]);

            // Deduct boost credit if available
            $pdo->prepare("UPDATE vendors SET boost_credits = MAX(0, boost_credits - 1) WHERE id = ?")
                ->execute([$vendorId]);

            // Refresh vendor
            $stmt->execute([$vendorId]);
            $vendor = $stmt->fetch();

            $flashMessage = "Product #{$productId} is now BOOSTED with '{$tier}' placement for 7 days!";
            $flashType = 'success';
        }
    }

    // ── Add New Product with Variety & Preorder ─────────────────────────────
    if ($action === 'add_product') {
        $title = trim($_POST['title'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 1);
        $type = in_array($_POST['type'] ?? '', ['physical', 'digital']) ? $_POST['type'] : 'physical';
        $price = floatval($_POST['price'] ?? 0);
        $prevPrice = !empty($_POST['previous_price']) ? floatval($_POST['previous_price']) : null;
        $stock = intval($_POST['stock_quantity'] ?? 50);
        $thumbnail = trim($_POST['thumbnail'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $specs = trim($_POST['specifications'] ?? '');

        // Preorder settings
        $isPreorder = !empty($_POST['is_preorder']) ? 1 : 0;
        $preorderRelease = !empty($_POST['preorder_release_date']) ? trim($_POST['preorder_release_date']) : null;
        $preorderDeposit = !empty($_POST['preorder_deposit']) ? floatval($_POST['preorder_deposit']) : null;

        // Variety / Variants definition
        $hasVariants = !empty($_POST['has_variants']) ? 1 : 0;
        $variantColors = array_filter(array_map('trim', explode(',', $_POST['variant_colors'] ?? '')));
        $variantSizes  = array_filter(array_map('trim', explode(',', $_POST['variant_sizes'] ?? '')));
        $variantSpecs  = array_filter(array_map('trim', explode(',', $_POST['variant_specs'] ?? '')));

        $attrSchema = [];
        if (!empty($variantColors)) $attrSchema['Color'] = array_values($variantColors);
        if (!empty($variantSizes))  $attrSchema['Size']  = array_values($variantSizes);
        if (!empty($variantSpecs))  $attrSchema['RAM / Storage'] = array_values($variantSpecs);

        if (empty($title) || $price <= 0) {
            $flashMessage = 'Please enter a valid product title and price.';
            $flashType = 'error';
        } else {
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $title))) . '-' . rand(100, 999);
            $sku = 'VND-' . $vendorId . '-' . strtoupper(substr(uniqid(), -4));
            if (empty($thumbnail)) {
                $thumbnail = ($type === 'digital') 
                    ? 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800' 
                    : 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800';
            }

            $insStmt = $pdo->prepare("
                INSERT INTO products (
                    category_id, vendor_id, title, slug, sku, type, price, previous_price, stock_quantity, 
                    thumbnail, description, specifications, is_featured, is_preorder, preorder_release_date, 
                    preorder_deposit, has_variants, variant_attributes, rating, reviews_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 5.0, 1)
            ");
            $insStmt->execute([
                $categoryId, $vendorId, $title, $slug, $sku, $type, $price, $prevPrice, $stock, 
                $thumbnail, $description, $specs, $isPreorder, $preorderRelease, $preorderDeposit, 
                $hasVariants, !empty($attrSchema) ? json_encode($attrSchema) : null
            ]);
            $newProdId = $pdo->lastInsertId();

            // Generate Variants in product_variants if variety provided
            if ($hasVariants && !empty($attrSchema)) {
                $varStmt = $pdo->prepare("INSERT INTO product_variants (product_id, sku, title, attributes_json, price, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $varIndex = 1;

                if (!empty($variantColors) && !empty($variantSpecs)) {
                    foreach ($variantColors as $c) {
                        foreach ($variantSpecs as $sp) {
                            $vSku = $sku . '-' . strtoupper(substr($c, 0, 3)) . '-' . $varIndex++;
                            $vTitle = "{$c} · {$sp}";
                            $varStmt->execute([$newProdId, $vSku, $vTitle, json_encode(['Color' => $c, 'RAM / Storage' => $sp]), $price, max(5, round($stock / (count($variantColors) * count($variantSpecs))))]);
                        }
                    }
                } elseif (!empty($variantColors) && !empty($variantSizes)) {
                    foreach ($variantColors as $c) {
                        foreach ($variantSizes as $sz) {
                            $vSku = $sku . '-' . strtoupper(substr($c, 0, 3)) . '-' . $sz;
                            $vTitle = "{$c} · Size {$sz}";
                            $varStmt->execute([$newProdId, $vSku, $vTitle, json_encode(['Color' => $c, 'Size' => $sz]), $price, max(5, round($stock / (count($variantColors) * count($variantSizes))))]);
                        }
                    }
                } elseif (!empty($variantColors)) {
                    foreach ($variantColors as $c) {
                        $vSku = $sku . '-' . strtoupper(substr($c, 0, 3));
                        $vTitle = $c;
                        $varStmt->execute([$newProdId, $vSku, $vTitle, json_encode(['Color' => $c]), $price, max(5, round($stock / count($variantColors)))]);
                    }
                } elseif (!empty($variantSpecs)) {
                    foreach ($variantSpecs as $sp) {
                        $vSku = $sku . '-V' . $varIndex++;
                        $vTitle = $sp;
                        $varStmt->execute([$newProdId, $vSku, $vTitle, json_encode(['RAM / Storage' => $sp]), $price, max(5, round($stock / count($variantSpecs)))]);
                    }
                }
            }

            $flashMessage = "Product '{$title}' successfully published to the ZAVORA marketplace catalog!";
            $flashType = 'success';
        }
    }

    if ($action === 'update_fulfillment') {
        $itemId = intval($_POST['item_id'] ?? 0);
        $newStatus = trim($_POST['fulfillment_status'] ?? 'processing');
        if (in_array($newStatus, ['pending', 'processing', 'shipped', 'delivered'])) {
            $upd = $pdo->prepare("UPDATE order_items SET fulfillment_status = ? WHERE id = ? AND (vendor_id = ? OR vendor_id IS NULL)");
            $upd->execute([$newStatus, $itemId, $vendorId]);
            $flashMessage = 'Order fulfillment status updated to ' . ucfirst($newStatus);
            $flashType = 'success';
        }
    }

    if ($action === 'request_payout') {
        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount <= 0 || $amount > $vendor['balance']) {
            $flashMessage = 'Invalid withdrawal amount requested.';
            $flashType = 'error';
        } else {
            $ref = 'PAYOUT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $bankStr = "{$vendor['bank_name']} — {$vendor['account_number']} ({$vendor['account_name']})";
            
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO vendor_payouts (vendor_id, amount, bank_details, status, reference) VALUES (?, ?, ?, 'pending', ?)")
                ->execute([$vendorId, $amount, $bankStr, $ref]);
            
            $pdo->prepare("UPDATE vendors SET balance = balance - ? WHERE id = ?")
                ->execute([$amount, $vendorId]);
            $pdo->commit();

            // Refresh vendor
            $stmt->execute([$vendorId]);
            $vendor = $stmt->fetch();

            $flashMessage = 'Payout request of ' . zavora_format_price($amount) . ' submitted for settlement to ' . $vendor['bank_name'] . '.';
            $flashType = 'success';
        }
    }
}

// 3. Fetch Vendor's Products & Variant Counts
$prodStmt = $pdo->prepare("
    SELECT p.*, (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) as variants_count 
    FROM products p 
    WHERE p.vendor_id = ? 
    ORDER BY p.id DESC
");
$prodStmt->execute([$vendorId]);
$vendorProducts = $prodStmt->fetchAll();

// 4. Fetch Vendor's Order Items
$itemStmt = $pdo->prepare("
    SELECT oi.*, o.order_number, o.customer_name, o.customer_email, o.customer_phone, o.delivery_address, o.delivery_state, o.created_at as order_date 
    FROM order_items oi 
    JOIN orders o ON o.id = oi.order_id 
    WHERE (oi.vendor_id = ? OR (oi.vendor_id IS NULL AND oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?)))
    ORDER BY oi.id DESC
");
$itemStmt->execute([$vendorId, $vendorId]);
$vendorOrders = $itemStmt->fetchAll();

// 5. Fetch Categories for Product Form
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// 6. Fetch Payouts
$payoutStmt = $pdo->prepare("SELECT * FROM vendor_payouts WHERE vendor_id = ? ORDER BY id DESC");
$payoutStmt->execute([$vendorId]);
$payouts = $payoutStmt->fetchAll();

// 7. Handle AI Credit Payment Callback & Fetch AI Credit Ledger (Paid Feature)
if (isset($_GET['ai_payment']) && $_GET['ai_payment'] === 'success') {
    $flashMessage = 'AI Credit recharge successful! Your AI balance has been updated.';
    $flashType = 'success';
    // Refresh vendor to get updated ai_credits
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
}

$aiTransStmt = $pdo->prepare("SELECT * FROM ai_credit_transactions WHERE vendor_id = ? ORDER BY id DESC LIMIT 50");
$aiTransStmt->execute([$vendorId]);
$aiTransactions = $aiTransStmt->fetchAll();

// Calculate total sales
$totalVendorSales = 0;
foreach ($vendorOrders as $vo) {
    $totalVendorSales += $vo['total_price'];
}

$title = $vendor['store_name'] . ' — Seller Central Hub';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-8 max-w-7xl">
  
  <!-- Merchant Top Banner -->
  <div class="bg-gradient-to-r from-[#0B132B] via-[#1B2299] to-[#0B132B] rounded-3xl p-6 sm:p-8 text-white mb-8 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-3xl font-black text-[#F7DE7A]">
        <i class="fas fa-store"></i>
      </div>
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[10px] font-black uppercase tracking-wider flex items-center gap-1">
            <i class="fas fa-circle-check"></i> Verified Merchant
          </span>
          <span class="text-xs text-gray-300">Commission Rate: <?php echo 100 - (float)$vendor['commission_rate']; ?>% Vendor / <?php echo (float)$vendor['commission_rate']; ?>% ZAVORA</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-black font-display"><?php echo htmlspecialchars($vendor['store_name']); ?></h1>
        <p class="text-xs text-gray-300">
          Owner: <?php echo htmlspecialchars($vendor['owner_name']); ?> • 
          Payout Bank: <strong class="text-white"><?php echo htmlspecialchars($vendor['bank_name'] ?: 'Not configured'); ?></strong> 
          (<?php echo htmlspecialchars($vendor['account_number'] ?: 'Setup Required'); ?>)
        </p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <a href="<?php echo zavora_asset('shop.php'); ?>" target="_blank" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition-all border border-white/20 flex items-center gap-1.5">
        <i class="fas fa-globe"></i> View Marketplace Storefront
      </a>
      <form method="POST">
        <input type="hidden" name="action" value="signout">
        <button type="submit" class="px-4 py-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 rounded-xl text-xs font-bold transition-all border border-rose-500/30 flex items-center gap-1">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </button>
      </form>
    </div>
  </div>

  <?php if ($flashMessage): ?>
    <div class="p-4 mb-6 rounded-2xl text-xs font-bold flex items-center gap-2 <?php echo $flashType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
      <i class="fas <?php echo $flashType === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-rose-600'; ?> text-base"></i>
      <span><?php echo htmlspecialchars($flashMessage); ?></span>
    </div>
  <?php endif; ?>

  <!-- KPI Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm flex flex-col justify-between">
      <div>
        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Available Balance</span>
        <p class="text-2xl font-black text-emerald-700 mt-2 font-display"><?php echo zavora_format_price($vendor['balance']); ?></p>
      </div>
      <button onclick="document.getElementById('payoutModal').classList.remove('hidden')" 
              class="mt-4 w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm flex items-center justify-center gap-1.5">
        <i class="fas fa-money-bill-transfer"></i> Withdraw
      </button>
    </div>

    <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Gross Sales</span>
      <p class="text-2xl font-black text-[#0B132B] mt-2 font-display"><?php echo zavora_format_price($totalVendorSales); ?></p>
      <p class="text-[11px] text-gray-500 font-medium mt-1">Across all product lines</p>
    </div>

    <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Active Products</span>
      <p class="text-2xl font-black text-[#1B2299] mt-2 font-display"><?php echo count($vendorProducts); ?></p>
      <p class="text-[11px] text-gray-500 font-medium mt-1">Listed in catalog</p>
    </div>

    <div class="bg-white p-5 rounded-3xl border border-gray-200 shadow-sm flex flex-col justify-between">
      <div>
        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Boost Credits</span>
        <p class="text-2xl font-black text-amber-600 mt-2 font-display"><?php echo intval($vendor['boost_credits'] ?? 5); ?> Left</p>
      </div>
      <span class="text-[11px] text-amber-700 font-medium mt-1 flex items-center gap-1">
        <i class="fas fa-bolt text-amber-500"></i> Search promotion
      </span>
    </div>

    <div class="bg-gradient-to-br from-[#1B2299] to-[#0B132B] p-5 rounded-3xl text-white shadow-sm flex flex-col justify-between relative overflow-hidden">
      <div>
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-bold text-blue-200 uppercase tracking-wider flex items-center gap-1.5">
            <i class="fas fa-wand-magic-sparkles text-[#F7DE7A]"></i> AI Studio
          </span>
          <span class="px-2 py-0.5 rounded-full bg-white/10 text-amber-300 border border-white/20 text-[9px] font-black uppercase">
            Paid
          </span>
        </div>
        <p class="text-2xl font-black text-white mt-2 font-display" id="kpiAiCreditsDisplay"><?php echo intval($vendor['ai_credits'] ?? 3); ?> Credits</p>
        <p class="text-[11px] text-blue-200 font-medium mt-1">Copy, titles & studio photos</p>
      </div>
      <button onclick="openAiTopupModal()" 
              class="mt-4 w-full py-2 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-gray-950 font-black text-xs rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5">
        <i class="fas fa-plus-circle text-xs"></i> Top Up Credits
      </button>
    </div>
  </div>

  <!-- Main Tabs Hub -->
  <div class="bg-white rounded-3xl border border-gray-200 shadow-sm overflow-hidden mb-12">
    
    <div class="border-b border-gray-200 px-6 pt-4 flex gap-6 overflow-x-auto" id="vendorTabs">
      <button onclick="switchVendorTab('products')" id="vtab-btn-products" class="vtab-btn pb-4 font-bold text-sm border-b-2 border-[#1B2299] text-[#1B2299] flex items-center gap-2">
        <i class="fas fa-box"></i>
        <span>My Products & Inventory</span>
        <span class="px-2 py-0.5 rounded-full bg-blue-100 text-[#1B2299] text-[11px] font-black"><?php echo count($vendorProducts); ?></span>
      </button>

      <button onclick="switchVendorTab('settings')" id="vtab-btn-settings" class="vtab-btn pb-4 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-900 flex items-center gap-2">
        <i class="fas fa-store"></i>
        <span>Store & Payout Accounts</span>
      </button>

      <button onclick="switchVendorTab('orders')" id="vtab-btn-orders" class="vtab-btn pb-4 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-900 flex items-center gap-2">
        <i class="fas fa-truck-fast"></i>
        <span>Fulfillment & Orders</span>
        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] font-bold"><?php echo count($vendorOrders); ?></span>
      </button>

      <button onclick="switchVendorTab('payouts')" id="vtab-btn-payouts" class="vtab-btn pb-4 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-900 flex items-center gap-2">
        <i class="fas fa-building-columns"></i>
        <span>Settlements & Payouts</span>
        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[11px] font-bold"><?php echo count($payouts); ?></span>
      </button>

      <button onclick="switchVendorTab('ai')" id="vtab-btn-ai" class="vtab-btn pb-4 font-semibold text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-900 flex items-center gap-2">
        <i class="fas fa-wand-magic-sparkles text-amber-500"></i>
        <span>AI Merchant Studio</span>
        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold" id="tabAiCreditsBadge"><?php echo intval($vendor['ai_credits'] ?? 3); ?> credits</span>
      </button>
    </div>

    <!-- TAB 1: PRODUCTS & INVENTORY -->
    <div id="vtab-products" class="p-6">
      <div class="flex items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-lg font-black text-[#0B132B] font-display">Product & Inventory Manager</h2>
          <p class="text-xs text-gray-500">Configure variety options, set preorders, boost visibility, and adjust stock in real-time.</p>
        </div>
        <div class="flex items-center gap-2">
          <a href="<?php echo zavora_asset('seller/ai-studio.php'); ?>"
             class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-gray-700 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-gray-200">
            <i class="fas fa-sparkles text-purple-600"></i>
            <span>AI Studio</span>
            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">Coming Soon</span>
          </a>
          <a href="<?php echo zavora_asset('seller/product-create.php'); ?>"
             class="px-4 py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-2">
            <i class="fas fa-plus"></i>
            <span>Add New Product</span>
          </a>
        </div>
      </div>

      <div class="overflow-x-auto border border-gray-200 rounded-2xl">
        <table class="w-full text-left text-xs text-gray-600">
          <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
            <tr>
              <th class="p-3">Product</th>
              <th class="p-3">Type & Features</th>
              <th class="p-3">Price</th>
              <th class="p-3">Inventory Stock</th>
              <th class="p-3">Visibility / Boost</th>
              <th class="p-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($vendorProducts)): ?>
              <tr>
                <td colspan="6" class="p-10 text-center text-gray-400">
                  <div class="w-12 h-12 rounded-2xl bg-blue-50 text-[#1B2299] flex items-center justify-center mx-auto mb-3 text-lg">
                    <i class="fas fa-box-open"></i>
                  </div>
                  <p class="font-bold text-gray-700 text-sm mb-1">No products listed in your store yet</p>
                  <p class="text-xs text-gray-400 mb-4">Use the full-page enterprise AI studio to draft, generate photography, and list your first item.</p>
                  <a href="<?php echo zavora_asset('seller/product-create.php'); ?>" 
                     class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-extrabold rounded-xl shadow transition">
                    <i class="fas fa-sparkles"></i>
                    <span>List Product with AI Studio</span>
                  </a>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($vendorProducts as $prod): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="p-3">
                    <div class="flex items-center gap-3">
                      <img src="<?php echo htmlspecialchars(zavora_image_url($prod['thumbnail'])); ?>" class="w-12 h-12 rounded-xl object-cover border border-gray-200 flex-shrink-0">
                      <div>
                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($prod['title']); ?></div>
                        <div class="text-[11px] text-gray-400 font-mono"><?php echo htmlspecialchars($prod['sku']); ?></div>
                      </div>
                    </div>
                  </td>
                  <td class="p-3">
                    <div class="flex flex-col gap-1">
                      <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold w-max <?php echo $prod['type'] === 'digital' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-[#1B2299]'; ?>">
                        <?php echo ucfirst($prod['type']); ?>
                      </span>
                      <?php if (!empty($prod['has_variants'])): ?>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 w-max">
                          <i class="fas fa-sliders text-[9px]"></i> <?php echo $prod['variants_count']; ?> Variants
                        </span>
                      <?php endif; ?>
                      <?php if (!empty($prod['is_preorder'])): ?>
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 w-max">
                          <i class="fas fa-calendar-alt text-[9px]"></i> Pre-Order
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="p-3 font-bold text-gray-900"><?php echo zavora_format_price($prod['price']); ?></td>
                  <td class="p-3">
                    <!-- Inline Stock Adjuster -->
                    <form method="POST" class="flex items-center gap-1.5">
                      <input type="hidden" name="action" value="adjust_inventory">
                      <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                      <input type="number" name="new_stock" value="<?php echo $prod['stock_quantity']; ?>" min="0" max="9999"
                             class="w-16 px-2 py-1 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-center focus:bg-white focus:outline-none">
                      <button type="submit" title="Save Inventory" class="p-1 px-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition">
                        <i class="fas fa-check"></i>
                      </button>
                    </form>
                  </td>
                  <td class="p-3">
                    <?php if (!empty($prod['is_boosted'])): ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-sm">
                        <i class="fas fa-bolt text-[9px]"></i> Sponsored
                      </span>
                    <?php else: ?>
                      <form method="POST" class="inline-block">
                        <input type="hidden" name="action" value="boost_product">
                        <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
                        <button type="submit" class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 transition flex items-center gap-1">
                          <i class="fas fa-arrow-trend-up"></i> Boost Item
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td class="p-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <a href="<?php echo zavora_asset('seller/product-edit.php?id=' . $prod['id']); ?>"
                         class="px-3 py-1.5 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                        <i class="fas fa-pen-to-square"></i>
                        <span>Edit</span>
                      </a>
                      <a href="<?php echo zavora_asset('product.php?id=' . $prod['id']); ?>" target="_blank"
                         class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition flex items-center gap-1">
                        <span>View</span>
                        <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: STORE PROFILE & PAYOUT ACCOUNTS -->
    <div id="vtab-settings" class="p-6 hidden">
      <div class="mb-6">
        <h2 class="text-lg font-black text-[#0B132B] font-display">Store Settings & Settlement Payout Account</h2>
        <p class="text-xs text-gray-500">Configure your public storefront branding, contact information, and verified Nigerian bank account for automated payouts.</p>
      </div>

      <form method="POST" class="max-w-2xl space-y-6">
        <input type="hidden" name="action" value="update_profile">

        <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl space-y-4">
          <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider flex items-center gap-2">
            <i class="fas fa-store text-[#1B2299]"></i> Public Store Profile
          </h3>

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Store / Business Name *</label>
              <input type="text" name="store_name" value="<?php echo htmlspecialchars($vendor['store_name']); ?>" required
                     class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Business Owner Full Name *</label>
              <input type="text" name="owner_name" value="<?php echo htmlspecialchars($vendor['owner_name']); ?>" required
                     class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            </div>
          </div>

          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Customer Support Phone</label>
              <input type="tel" name="phone" value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>" placeholder="080XXXXXXXX"
                     class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none">
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Customer Support Email</label>
              <input type="email" name="support_email" value="<?php echo htmlspecialchars($vendor['support_email'] ?? $vendor['email']); ?>"
                     class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Store Bio / Description</label>
            <textarea name="description" rows="3" class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none"><?php echo htmlspecialchars($vendor['description'] ?? ''); ?></textarea>
          </div>
        </div>

        <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl space-y-4">
          <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider flex items-center gap-2">
            <i class="fas fa-building-columns text-emerald-600"></i> Nigerian Bank Payout Account
          </h3>
          <p class="text-xs text-gray-500">Sales proceeds (85% net) will be transferred directly to this bank account upon withdrawal.</p>

          <div class="grid sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Select Bank *</label>
              <select name="bank_name" required class="w-full px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-semibold focus:outline-none">
                <option value="">Choose Bank...</option>
                <?php foreach ($nigerianBanks as $bnk): ?>
                  <option value="<?php echo htmlspecialchars($bnk); ?>" <?php echo $vendor['bank_name'] === $bnk ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($bnk); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">NUBAN Account Number *</label>
              <input type="text" name="account_number" maxlength="10" value="<?php echo htmlspecialchars($vendor['account_number'] ?? ''); ?>" required placeholder="0123456789"
                     class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold font-mono focus:outline-none">
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Account Beneficiary Name *</label>
              <input type="text" name="account_name" value="<?php echo htmlspecialchars($vendor['account_name'] ?? ''); ?>" required placeholder="Account Name"
                     class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold focus:outline-none">
            </div>
          </div>
        </div>

        <button type="submit" class="py-3 px-6 bg-[#1B2299] hover:bg-[#0B132B] text-white font-bold rounded-xl text-xs transition-all shadow-md">
          Save Settings & Bank Details
        </button>
      </form>
    </div>

    <!-- TAB 3: ORDERS & FULFILLMENT -->
    <div id="vtab-orders" class="p-6 hidden">
      <div class="mb-6">
        <h2 class="text-lg font-black text-[#0B132B] font-display">Fulfillment Manager</h2>
        <p class="text-xs text-gray-500">Update order status when you package and dispatch physical items to customers.</p>
      </div>

      <div class="overflow-x-auto border border-gray-200 rounded-2xl">
        <table class="w-full text-left text-xs text-gray-600">
          <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
            <tr>
              <th class="p-3">Order Number</th>
              <th class="p-3">Item Purchased</th>
              <th class="p-3">Customer Details</th>
              <th class="p-3">Total / Net Payout</th>
              <th class="p-3">Fulfillment Status</th>
              <th class="p-3 text-right">Update Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($vendorOrders)): ?>
              <tr>
                <td colspan="6" class="p-8 text-center text-gray-400 italic">No orders received for your store items yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($vendorOrders as $vo): ?>
                <?php 
                  $fee = $vo['total_price'] * ($vendor['commission_rate'] / 100);
                  $net = $vo['total_price'] - $fee;
                ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="p-3 font-bold text-gray-900"><?php echo htmlspecialchars($vo['order_number']); ?></td>
                  <td class="p-3">
                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($vo['title']); ?></div>
                    <div class="text-[11px] text-gray-400">Qty: <?php echo $vo['quantity']; ?></div>
                  </td>
                  <td class="p-3">
                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($vo['customer_name']); ?></div>
                    <div class="text-[11px] text-gray-500"><?php echo htmlspecialchars($vo['customer_phone']); ?></div>
                    <div class="text-[10px] text-gray-400 truncate max-w-xs"><?php echo htmlspecialchars($vo['delivery_address'] ?? 'Digital Delivery'); ?></div>
                  </td>
                  <td class="p-3">
                    <div class="font-black text-gray-900"><?php echo zavora_format_price($vo['total_price']); ?></div>
                    <div class="text-[11px] text-emerald-700 font-bold">Net: <?php echo zavora_format_price($net); ?></div>
                  </td>
                  <td class="p-3">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold 
                      <?php echo $vo['fulfillment_status'] === 'delivered' ? 'bg-emerald-100 text-emerald-800' : 
                                ($vo['fulfillment_status'] === 'shipped' ? 'bg-blue-100 text-[#1B2299]' : 'bg-amber-100 text-amber-800'); ?>">
                      <?php echo ucfirst($vo['fulfillment_status'] ?? 'pending'); ?>
                    </span>
                  </td>
                  <td class="p-3 text-right">
                    <form method="POST" class="inline-flex items-center gap-1.5">
                      <input type="hidden" name="action" value="update_fulfillment">
                      <input type="hidden" name="item_id" value="<?php echo $vo['id']; ?>">
                      <select name="fulfillment_status" class="px-2 py-1 bg-white border border-gray-200 rounded-lg text-xs font-semibold focus:outline-none">
                        <option value="pending" <?php echo $vo['fulfillment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $vo['fulfillment_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $vo['fulfillment_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $vo['fulfillment_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                      </select>
                      <button type="submit" class="px-2.5 py-1 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-lg text-xs font-bold transition">
                        Save
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

    <!-- TAB 4: SETTLEMENTS & PAYOUTS -->
    <div id="vtab-payouts" class="p-6 hidden">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-black text-[#0B132B] font-display">Settlement Payouts</h2>
          <p class="text-xs text-gray-500">Track all transfers from your ZAVORA vendor balance to your Nigerian bank account.</p>
        </div>
        <button onclick="document.getElementById('payoutModal').classList.remove('hidden')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center gap-1.5">
          <i class="fas fa-money-bill-transfer"></i> Withdraw Funds
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="p-5 bg-gray-50 rounded-2xl border border-gray-200">
          <span class="text-[11px] font-bold text-gray-500 uppercase">Settlement Bank</span>
          <p class="text-sm font-bold text-gray-900 mt-1"><?php echo htmlspecialchars($vendor['bank_name'] ?: 'Not set'); ?></p>
          <span class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($vendor['account_number'] ?: '—'); ?></span>
        </div>

        <div class="p-5 bg-gray-50 rounded-2xl border border-gray-200">
          <span class="text-[11px] font-bold text-gray-500 uppercase">Account Beneficiary</span>
          <p class="text-sm font-bold text-gray-900 mt-1"><?php echo htmlspecialchars($vendor['account_name'] ?: $vendor['owner_name']); ?></p>
          <span class="text-[11px] text-emerald-600 font-bold">Verified for Paystack Transfers</span>
        </div>

        <div class="p-5 bg-gray-50 rounded-2xl border border-gray-200">
          <span class="text-[11px] font-bold text-gray-500 uppercase">Payout Schedule</span>
          <p class="text-sm font-bold text-gray-900 mt-1">Automatic Next-Day Settlement</p>
          <span class="text-[11px] text-gray-400">T+1 rolling schedule</span>
        </div>
      </div>

      <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3">Withdrawal & Settlement History</h3>
      
      <div class="overflow-x-auto border border-gray-200 rounded-2xl">
        <table class="w-full text-left text-xs text-gray-600">
          <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
            <tr>
              <th class="p-3">Reference</th>
              <th class="p-3">Amount</th>
              <th class="p-3">Bank Details</th>
              <th class="p-3">Status</th>
              <th class="p-3 text-right">Date</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($payouts)): ?>
              <tr>
                <td colspan="5" class="p-8 text-center text-gray-400 italic">No withdrawal requests yet. You can withdraw your available balance anytime.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($payouts as $po): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="p-3 font-mono font-bold text-gray-900"><?php echo htmlspecialchars($po['reference']); ?></td>
                  <td class="p-3 font-black text-gray-900"><?php echo zavora_format_price($po['amount']); ?></td>
                  <td class="p-3 text-gray-600"><?php echo htmlspecialchars($po['bank_details']); ?></td>
                  <td class="p-3">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?php echo $po['status'] === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>">
                      <?php echo ucfirst($po['status']); ?>
                    </span>
                  </td>
                  <td class="p-3 text-right text-gray-400"><?php echo date('M d, Y', strtotime($po['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 5: AI MERCHANT STUDIO (PAID FEATURE) -->
    <div id="vtab-ai" class="p-6 hidden">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-8 pb-6 border-b border-gray-200">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-black uppercase tracking-wider shadow-sm">
              <i class="fas fa-sparkles"></i> Paid Merchant Studio
            </span>
            <span class="text-xs font-bold text-gray-500">Commercial Copy & Photo Generation</span>
          </div>
          <h2 class="text-xl font-black text-[#0B132B] font-display">AI Merchant Studio & Automation</h2>
          <p class="text-xs text-gray-500 max-w-2xl mt-1">
            Empower your store with generative AI. Create high-converting product titles, marketing copy, and generate clean studio product photography. Powered by Paystack credits.
          </p>
        </div>
        <div class="flex items-center gap-4 bg-slate-50 border border-slate-200 p-4 rounded-2xl">
          <div>
            <div class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Your Balance</div>
            <div class="text-2xl font-black text-[#1B2299] font-display" id="aiTabBalanceDisplay"><?php echo intval($vendor['ai_credits'] ?? 3); ?> Credits</div>
          </div>
          <button onclick="openAiTopupModal()" class="px-4 py-2.5 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-gray-950 font-black text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5 whitespace-nowrap">
            <i class="fas fa-plus-circle"></i> Top Up Credits
          </button>
        </div>
      </div>

      <!-- Launch Full Conversational AI Studio Banner -->
      <div class="mb-10 p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 border border-indigo-900/60 text-white flex flex-col md:flex-row md:items-center justify-between gap-6 shadow-2xl">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-[#1B2299] via-indigo-500 to-purple-500 flex items-center justify-center text-white text-2xl shadow-xl shadow-indigo-500/30 flex-shrink-0">
            <i class="fas fa-sparkles text-amber-300"></i>
          </div>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black bg-amber-400 text-gray-950 uppercase tracking-wider">COMING SOON</span>
              <span class="text-xs text-indigo-300 font-bold">• Fine-Tuning For Enterprise Launch</span>
            </div>
            <h3 class="text-xl font-black text-white font-display">Conversational AI Merchant Studio (Chat & Canvas)</h3>
            <p class="text-xs text-slate-300 mt-1 max-w-xl leading-relaxed">
              Automated listing generation and AI photography sourcing are undergoing final calibration. Full enterprise release coming soon!
            </p>
          </div>
        </div>
        <div class="px-5 py-3 bg-white/10 border border-white/20 text-white font-black text-xs rounded-2xl flex items-center justify-center gap-2 whitespace-nowrap">
          <i class="fas fa-lock text-amber-300"></i>
          <span>Feature In Early Access Preview</span>
        </div>
      </div>

      <!-- Pricing Packs Cards -->
      <div class="mb-10">
        <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider mb-4 flex items-center gap-2">
          <i class="fas fa-coins text-amber-500"></i> AI Credit Purchase Packs
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <!-- Starter Pack -->
          <div class="p-6 bg-white rounded-3xl border border-gray-200 hover:border-blue-400 hover:shadow-lg transition-all flex flex-col justify-between">
            <div>
              <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Starter</span>
              <h4 class="text-lg font-black text-[#0B132B] mt-1">25 AI Credits</h4>
              <p class="text-2xl font-black text-[#1B2299] mt-2 font-display">₦2,500</p>
              <span class="text-[11px] text-gray-500 font-medium">₦100 per generation</span>
              <ul class="mt-4 space-y-2 text-xs text-gray-600 border-t border-gray-100 pt-3">
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> 25 Title & Copy generations</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> Studio product photography</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> Instant activation</li>
              </ul>
            </div>
            <button onclick="buyAiCredits('starter')" class="mt-6 w-full py-2.5 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl transition shadow">
              Purchase Starter Pack
            </button>
          </div>

          <!-- Growth Pack (Featured) -->
          <div class="p-6 bg-gradient-to-b from-blue-50/50 to-white rounded-3xl border-2 border-[#1B2299] shadow-md relative flex flex-col justify-between">
            <div class="absolute -top-3 right-6 bg-[#1B2299] text-white text-[10px] font-black uppercase px-3 py-1 rounded-full tracking-wider shadow">
              Most Popular • 20% Off
            </div>
            <div>
              <span class="text-xs font-bold text-[#1B2299] uppercase tracking-wider">Growth</span>
              <h4 class="text-lg font-black text-[#0B132B] mt-1">75 AI Credits</h4>
              <p class="text-2xl font-black text-[#1B2299] mt-2 font-display">₦6,000</p>
              <span class="text-[11px] text-emerald-700 font-bold">₦80 per generation (Save ₦1,500)</span>
              <ul class="mt-4 space-y-2 text-xs text-gray-600 border-t border-blue-100 pt-3">
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> 75 Title, copy & spec batches</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> HD Studio product imagery</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> Priority AI queue</li>
              </ul>
            </div>
            <button onclick="buyAiCredits('growth')" class="mt-6 w-full py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl transition shadow-md">
              Purchase Growth Pack
            </button>
          </div>

          <!-- Pro Merchant Pack -->
          <div class="p-6 bg-white rounded-3xl border border-gray-200 hover:border-amber-400 hover:shadow-lg transition-all flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-amber-600 uppercase tracking-wider">Pro Merchant</span>
                <span class="text-[10px] font-black text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">30% Off</span>
              </div>
              <h4 class="text-lg font-black text-[#0B132B] mt-1">200 AI Credits</h4>
              <p class="text-2xl font-black text-[#1B2299] mt-2 font-display">₦14,000</p>
              <span class="text-[11px] text-emerald-700 font-bold">₦70 per generation (Save ₦6,000)</span>
              <ul class="mt-4 space-y-2 text-xs text-gray-600 border-t border-gray-100 pt-3">
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> 200 Complete studio runs</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> Full catalog optimization</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-500 text-xs"></i> Dedicated support</li>
              </ul>
            </div>
            <button onclick="buyAiCredits('pro')" class="mt-6 w-full py-2.5 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl transition shadow">
              Purchase Pro Pack
            </button>
          </div>
        </div>
      </div>

      <!-- Studio Interactive Sandbox -->
      <div class="mb-10 bg-slate-50 border border-slate-200 rounded-3xl p-6 sm:p-8">
        <h3 class="text-base font-black text-[#0B132B] font-display mb-1 flex items-center gap-2">
          <i class="fas fa-laptop-code text-[#1B2299]"></i> AI Studio Interactive Sandbox
        </h3>
        <p class="text-xs text-gray-500 mb-6">Test and generate content directly before adding to your products.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Copywriter Sandbox -->
          <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div>
              <h4 class="text-xs font-black uppercase text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-pen-fancy text-[#1B2299]"></i> AI Copywriter (1 Credit)
              </h4>
              <div class="space-y-3">
                <div>
                  <label class="block text-[11px] font-bold text-gray-600 mb-1">Product Keywords / Features</label>
                  <input type="text" id="sbCopyKeywords" placeholder="e.g. Ergonomic mechanical keyboard with RGB, tactile brown switches"
                         class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Category</label>
                    <select id="sbCopyCategory" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                      <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Tone</label>
                    <select id="sbCopyTone" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                      <option value="Commercial">Commercial & Persuasive</option>
                      <option value="Luxury">Luxury & Premium</option>
                      <option value="Minimalist">Minimalist & Direct</option>
                      <option value="Technical">Technical & Detailed</option>
                    </select>
                  </div>
                </div>
                <button type="button" onclick="runSandboxCopy()" id="btnSbCopy"
                        class="w-full py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl transition shadow flex items-center justify-center gap-2">
                  <i class="fas fa-sparkles"></i> <span>Generate Title & Copy</span>
                </button>
              </div>

              <!-- Result Area -->
              <div id="sbCopyResult" class="hidden mt-4 pt-4 border-t border-gray-150 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1">
                    <i class="fas fa-certificate"></i> Enterprise E-Commerce Standard
                  </span>
                  <span id="sbResTitleCount" class="text-[10px] font-bold text-gray-500"></span>
                </div>
                <div>
                  <span class="text-[10px] font-bold text-gray-400 uppercase">Product Title</span>
                  <p id="sbResTitle" class="text-xs font-bold text-gray-900 bg-gray-50 p-2.5 rounded-xl border border-gray-200"></p>
                </div>
                <div>
                  <span class="text-[10px] font-bold text-gray-400 uppercase">Key Features & Highlights</span>
                  <div id="sbResBullets" class="space-y-1.5 bg-gray-50 p-3 rounded-xl border border-gray-200 text-xs text-gray-700"></div>
                </div>
                <div>
                  <span class="text-[10px] font-bold text-gray-400 uppercase">Narrative Description</span>
                  <p id="sbResDesc" class="text-xs text-gray-700 bg-gray-50 p-2.5 rounded-xl border border-gray-200 leading-relaxed"></p>
                </div>
                <div>
                  <span class="text-[10px] font-bold text-gray-400 uppercase">Technical Specifications</span>
                  <div id="sbResSpecsTable" class="bg-gray-50 p-2.5 rounded-xl border border-gray-200 grid grid-cols-2 gap-1 text-[11px]"></div>
                </div>
                <button type="button" onclick="createProductFromSandbox()" class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow flex items-center justify-center gap-1.5">
                  <i class="fas fa-plus"></i> <span>Create Listing with This Draft</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Image Studio Sandbox -->
          <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex flex-col justify-between">
            <div>
              <h4 class="text-xs font-black uppercase text-gray-700 mb-3 flex items-center gap-2">
                <i class="fas fa-camera-retro text-purple-600"></i> AI Product Photo Studio (1 Credit)
              </h4>
              <div class="space-y-3">
                <div>
                  <label class="block text-[11px] font-bold text-gray-600 mb-1">Visual Concept Prompt</label>
                  <input type="text" id="sbImgPrompt" placeholder="e.g. Studio shot of titanium smart watch with AMOLED display"
                         class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Style / Category</label>
                    <select id="sbImgStyle" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                      <option value="electronics">Electronics & Audio</option>
                      <option value="computing">Laptops & Workstations</option>
                      <option value="fashion">Apparel & Footwear</option>
                      <option value="gaming">Console & Gaming</option>
                      <option value="digital">Digital Assets</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Standard Mode</label>
                    <select id="sbImgMode" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                      <option value="hero">Hero (Pure White #FFFFFF)</option>
                      <option value="lifestyle">Lifestyle Context</option>
                    </select>
                  </div>
                </div>
                <button type="button" onclick="runSandboxImage()" id="btnSbImage"
                        class="w-full py-2.5 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-xl transition shadow flex items-center justify-center gap-2">
                  <i class="fas fa-wand-magic-sparkles"></i> <span>Generate Studio Imagery</span>
                </button>
              </div>

              <!-- Result Area -->
              <div id="sbImgResult" class="hidden mt-4 pt-4 border-t border-gray-150 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-black text-purple-700 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1">
                    <i class="fas fa-certificate"></i> FLUX.1 Commercial Photography
                  </span>
                  <span class="text-[10px] text-gray-400">8K • No Watermarks</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <!-- Hero Preview -->
                  <div class="p-2 bg-gray-50 border border-gray-200 rounded-xl">
                    <span class="text-[10px] font-black text-gray-700 uppercase block mb-1">Hero (Pure White)</span>
                    <div class="aspect-square rounded-lg bg-white border border-gray-200 flex items-center justify-center p-1.5 mb-2">
                      <img id="sbResHeroTag" src="" class="w-full h-full object-contain">
                    </div>
                    <button type="button" onclick="copySandboxImgUrl('hero')" class="w-full py-1 bg-white hover:bg-gray-100 border border-gray-200 text-gray-800 rounded-lg text-[10px] font-bold transition flex items-center justify-center gap-1">
                      <i class="fas fa-copy"></i> Copy Hero URL
                    </button>
                  </div>
                  <!-- Lifestyle Preview -->
                  <div class="p-2 bg-gray-50 border border-gray-200 rounded-xl">
                    <span class="text-[10px] font-black text-gray-700 uppercase block mb-1">Lifestyle Context</span>
                    <div class="aspect-square rounded-lg bg-gray-900 border border-gray-200 flex items-center justify-center overflow-hidden mb-2">
                      <img id="sbResLifeTag" src="" class="w-full h-full object-cover">
                    </div>
                    <button type="button" onclick="copySandboxImgUrl('lifestyle')" class="w-full py-1 bg-white hover:bg-gray-100 border border-gray-200 text-gray-800 rounded-lg text-[10px] font-bold transition flex items-center justify-center gap-1">
                      <i class="fas fa-copy"></i> Copy Lifestyle URL
                    </button>
                  </div>
                </div>
                <input type="text" id="sbResImgUrl" readonly class="w-full px-2.5 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-[11px] font-mono text-gray-500">
                <button type="button" onclick="useSandboxImgInListing()" class="w-full py-2 bg-purple-700 hover:bg-purple-800 text-white rounded-xl text-xs font-bold transition shadow flex items-center justify-center gap-1.5">
                  <i class="fas fa-plus"></i> <span>List Product with Generated Photo</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Audit Ledger Table -->
      <div>
        <h3 class="text-xs font-black text-gray-800 uppercase tracking-wider mb-3 flex items-center gap-2">
          <i class="fas fa-list-check text-gray-500"></i> AI Credit Usage & Purchase Ledger
        </h3>
        <div class="overflow-x-auto border border-gray-200 rounded-2xl">
          <table class="w-full text-left text-xs text-gray-600">
            <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
              <tr>
                <th class="p-3">Action</th>
                <th class="p-3">Credits</th>
                <th class="p-3">Amount Paid</th>
                <th class="p-3">Reference / Details</th>
                <th class="p-3 text-right">Timestamp</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php if (empty($aiTransactions)): ?>
                <tr>
                  <td colspan="5" class="p-6 text-center text-gray-400 italic">No credit transactions yet. New vendors receive 3 complimentary credits to test.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($aiTransactions as $tx): ?>
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="p-3 font-bold text-gray-900 flex items-center gap-2">
                      <?php if ($tx['credits_change'] > 0): ?>
                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px]"><i class="fas fa-plus"></i></span>
                      <?php else: ?>
                        <span class="w-6 h-6 rounded-full bg-blue-100 text-[#1B2299] flex items-center justify-center text-[10px]"><i class="fas fa-wand-magic-sparkles"></i></span>
                      <?php endif; ?>
                      <span><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $tx['action_type']))); ?></span>
                    </td>
                    <td class="p-3 font-black <?php echo $tx['credits_change'] > 0 ? 'text-emerald-700' : 'text-gray-900'; ?>">
                      <?php echo ($tx['credits_change'] > 0 ? '+' : '') . $tx['credits_change']; ?> Credits
                    </td>
                    <td class="p-3 font-semibold text-gray-700">
                      <?php echo !empty($tx['amount_paid']) && $tx['amount_paid'] > 0 ? zavora_format_price($tx['amount_paid']) : '—'; ?>
                    </td>
                    <td class="p-3 text-gray-500 text-[11px]">
                      <?php echo htmlspecialchars($tx['payment_reference'] ?: $tx['prompt_summary']); ?>
                    </td>
                    <td class="p-3 text-right text-gray-400 text-[11px]">
                      <?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</div>

<!-- Modal 1: Add Product with Variety & Preorder -->
<div id="newProductModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-2xl relative max-h-[90vh] overflow-y-auto">
    <button onclick="document.getElementById('newProductModal').classList.add('hidden')" class="absolute top-5 right-5 text-gray-400 hover:text-gray-700 text-lg">
      <i class="fas fa-times"></i>
    </button>
    <h3 class="text-xl font-black text-[#0B132B] font-display mb-1">List a New Product</h3>
    <p class="text-xs text-gray-500 mb-6">Create a product with colors, sizes, specifications, and preorder settings.</p>

    <form method="POST" class="space-y-4" id="newProductForm">
      <input type="hidden" name="action" value="add_product">

      <!-- AI Merchant Studio Assistant Box (Paid Feature) -->
      <div class="p-4 bg-gradient-to-r from-blue-50/70 via-indigo-50/70 to-purple-50/70 border border-blue-200/80 rounded-2xl mb-4">
        <div class="flex items-center justify-between gap-2 mb-2">
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-lg bg-[#1B2299] text-white flex items-center justify-center text-xs">
              <i class="fas fa-wand-magic-sparkles"></i>
            </span>
            <div>
              <h4 class="text-xs font-black text-gray-900 uppercase tracking-wider">AI Studio Assistant (Paid)</h4>
              <p class="text-[10px] text-gray-500">Auto-draft copy or generate studio photos (1 credit/use)</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-[#1B2299] text-[10px] font-black">
              <span id="modalAiCreditsCount"><?php echo intval($vendor['ai_credits'] ?? 3); ?></span> Credits
            </span>
            <button type="button" onclick="openAiTopupModal()" class="text-[10px] text-[#1B2299] hover:underline font-bold">
              + Buy
            </button>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-2">
          <div class="sm:col-span-2">
            <input type="text" id="aiConceptInput" placeholder="Keywords (e.g. Wireless noise cancelling headset, 40h battery)..."
                   class="w-full px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none">
          </div>
          <div>
            <select id="aiToneInput" class="w-full px-2.5 py-2 bg-white border border-gray-200 rounded-xl text-xs focus:outline-none">
              <option value="Commercial">Commercial</option>
              <option value="Luxury">Luxury</option>
              <option value="Minimalist">Minimalist</option>
              <option value="Technical">Technical</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-2 mt-2.5">
          <button type="button" id="btnAiGenCopy" onclick="generateModalAiCopy()"
                  class="flex-1 py-1.5 px-3 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-xl text-[11px] font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
            <i class="fas fa-pen-nib"></i> <span>Auto-Draft Title & Copy</span>
          </button>
          <button type="button" id="btnAiGenImage" onclick="generateModalAiImage()"
                  class="flex-1 py-1.5 px-3 bg-purple-700 hover:bg-purple-800 text-white rounded-xl text-[11px] font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
            <i class="fas fa-image"></i> <span>Generate Photo</span>
          </button>
        </div>

        <!-- Mode Toggle for Photo Studio -->
        <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-blue-200/60 text-[10px]">
          <span class="text-gray-600 font-bold flex items-center gap-1">
            <i class="fas fa-camera text-[#1B2299]"></i> Photo Mode:
          </span>
          <div class="flex items-center gap-1">
            <input type="hidden" id="aiImgMode" value="hero">
            <button type="button" id="btnModeHero" onclick="setAiImgMode('hero')" class="px-2 py-0.5 rounded text-[10px] font-black bg-[#1B2299] text-white transition">
              Hero (Pure White #FFFFFF)
            </button>
            <button type="button" id="btnModeLifestyle" onclick="setAiImgMode('lifestyle')" class="px-2 py-0.5 rounded text-[10px] font-bold bg-white text-gray-700 border border-gray-200 hover:border-gray-400 transition">
              Lifestyle Context
            </button>
          </div>
        </div>

        <div id="aiModalStatus" class="hidden text-[11px] font-bold mt-2 p-2 rounded-lg"></div>

        <!-- Interactive AI Listing Reviewer Drawer (Enterprise Standard) -->
        <div id="aiListingReviewerDrawer" class="hidden mt-3 p-3 bg-white/95 backdrop-blur rounded-xl border border-blue-200 shadow-sm space-y-3">
          <div class="flex items-center justify-between pb-2 border-b border-gray-150">
            <span class="text-[11px] font-black text-gray-900 uppercase tracking-wider flex items-center gap-1.5">
              <i class="fas fa-certificate text-emerald-600"></i> AI Listing Reviewer
            </span>
            <span id="aiReviewTitleLen" class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
              0 / 90 chars (Catalog Formula)
            </span>
          </div>

          <!-- Bullets Preview -->
          <div>
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Key Product Features & Highlights</span>
            <div id="aiReviewBulletsList" class="space-y-1.5 text-[11px] text-gray-700 max-h-36 overflow-y-auto pr-1"></div>
          </div>

          <!-- Technical Specs Matrix Preview -->
          <div>
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Structured Specs Matrix</span>
            <div id="aiReviewSpecsGrid" class="grid grid-cols-2 gap-1 text-[10px]"></div>
          </div>

          <!-- Dual Generated Images Preview (Hero & Lifestyle) -->
          <div id="aiReviewImagesWrap" class="hidden pt-2 border-t border-gray-150">
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider block mb-1">Studio Photos (Click to choose main image)</span>
            <div class="grid grid-cols-2 gap-2">
              <div onclick="selectAiHeroImage()" id="cardHeroPreview" class="cursor-pointer p-1.5 rounded-xl border-2 border-[#1B2299] bg-white transition hover:shadow">
                <div class="aspect-square rounded-lg bg-white border border-gray-100 flex items-center justify-center p-1 mb-1">
                  <img id="imgHeroPreview" src="" class="w-full h-full object-contain">
                </div>
                <div class="text-[10px] font-black text-[#1B2299] flex items-center justify-between">
                  <span>Hero (#FFFFFF)</span>
                  <i class="fas fa-check-circle" id="iconHeroSelected"></i>
                </div>
              </div>
              <div onclick="selectAiLifestyleImage()" id="cardLifePreview" class="cursor-pointer p-1.5 rounded-xl border-2 border-transparent bg-white transition hover:shadow">
                <div class="aspect-square rounded-lg bg-gray-100 border border-gray-100 flex items-center justify-center mb-1 overflow-hidden">
                  <img id="imgLifePreview" src="" class="w-full h-full object-cover">
                </div>
                <div class="text-[10px] font-bold text-gray-600 flex items-center justify-between">
                  <span>Lifestyle</span>
                  <i class="fas fa-circle text-gray-300" id="iconLifeSelected"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-2 border-t border-gray-150 flex items-center justify-between gap-2">
            <span class="text-[10px] text-gray-400 italic">Enterprise catalog standard formatted.</span>
            <button type="button" onclick="applyAiListingToForm()" id="btnApplyAiListing" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow flex items-center gap-1">
              <i class="fas fa-check-double"></i> <span>Apply to Form</span>
            </button>
          </div>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-xs font-bold text-gray-700 uppercase">Product Title *</label>
          <span id="npTitleCharCount" class="text-[10px] text-gray-400 font-mono">0 chars</span>
        </div>
        <input type="text" name="title" id="np_title" oninput="updateTitleCharCount(this.value)" required placeholder="e.g. Sony WH-1000XM5 — Active Noise Canceling Headphones, Midnight Black"
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
          <select name="category_id" id="np_category_id" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Type</label>
          <select name="type" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
            <option value="physical">Physical Product</option>
            <option value="digital">Digital Download / Asset</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Base Price (₦) *</label>
          <input type="number" step="0.01" name="price" required placeholder="50000"
                 class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Old Price (₦)</label>
          <input type="number" step="0.01" name="previous_price" placeholder="65000"
                 class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Total Stock</label>
          <input type="number" name="stock_quantity" value="50"
                 class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
        </div>
      </div>

      <!-- Variety & Variants Section -->
      <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="has_variants" value="1" onchange="document.getElementById('varietyFields').classList.toggle('hidden', !this.checked)" class="rounded text-[#1B2299]">
          <span class="text-xs font-extrabold text-gray-800">This product has variety (Colors, Sizes, Specs, RAM/Storage)</span>
        </label>

        <div id="varietyFields" class="space-y-3 hidden pt-2 border-t border-gray-200">
          <div>
            <label class="block text-[11px] font-bold text-gray-600 mb-1">Colors (comma separated)</label>
            <input type="text" name="variant_colors" placeholder="Space Black, Silver, Midnight Blue"
                   class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-bold text-gray-600 mb-1">Sizes (e.g. S, M, L, XL)</label>
              <input type="text" name="variant_sizes" placeholder="S, M, L, XL"
                     class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none">
            </div>
            <div>
              <label class="block text-[11px] font-bold text-gray-600 mb-1">RAM / Storage / Specs</label>
              <input type="text" name="variant_specs" placeholder="256GB, 512GB, 1TB"
                     class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none">
            </div>
          </div>
        </div>
      </div>

      <!-- Preorder Section -->
      <div class="p-4 bg-indigo-50/50 border border-indigo-200 rounded-2xl space-y-3">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="is_preorder" value="1" onchange="document.getElementById('preorderFields').classList.toggle('hidden', !this.checked)" class="rounded text-indigo-600">
          <span class="text-xs font-extrabold text-indigo-950">Enable Pre-Order for this Item</span>
        </label>

        <div id="preorderFields" class="grid grid-cols-2 gap-3 hidden pt-2 border-t border-indigo-100">
          <div>
            <label class="block text-[11px] font-bold text-indigo-900 mb-1">Expected Ship Date</label>
            <input type="date" name="preorder_release_date" class="w-full px-3 py-2 bg-white border border-indigo-200 rounded-lg text-xs focus:outline-none">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-indigo-900 mb-1">Preorder Deposit (₦)</label>
            <input type="number" step="0.01" name="preorder_deposit" placeholder="Optional Deposit"
                   class="w-full px-3 py-2 bg-white border border-indigo-200 rounded-lg text-xs focus:outline-none">
          </div>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Image URL</label>
        <input type="url" name="thumbnail" id="np_thumbnail" oninput="updateThumbnailPreview(this.value)" placeholder="https://images.unsplash.com/..."
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
        <div id="np_preview_wrap" class="hidden mt-2 p-2 bg-gray-50 rounded-xl border border-gray-200 flex items-center gap-3">
          <img id="np_preview_img" src="" class="w-14 h-14 rounded-lg object-cover border border-gray-200 flex-shrink-0">
          <div class="text-[11px] text-gray-500 font-medium">Image preview from AI Studio / URL</div>
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Description</label>
        <textarea name="description" id="np_description" rows="3" placeholder="Key features, specifications, and warranty details..."
                  class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Technical Specifications</label>
        <textarea name="specifications" id="np_specifications" rows="2" placeholder="Processor, Battery, Dimensions, Material, Warranty..."
                  class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none"></textarea>
      </div>

      <button type="submit" class="w-full py-3 bg-[#1B2299] hover:bg-[#0B132B] text-white font-bold rounded-xl text-sm transition-all shadow-md">
        Publish to ZAVORA Storefront
      </button>
    </form>
  </div>
</div>

<!-- Modal 2: Withdraw Funds -->
<div id="payoutModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl relative">
    <button onclick="document.getElementById('payoutModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700">
      <i class="fas fa-times"></i>
    </button>
    <h3 class="text-lg font-black text-[#0B132B] font-display mb-1">Withdraw Funds</h3>
    <p class="text-xs text-gray-500 mb-4">Transfer available merchant balance to your verified Nigerian bank account.</p>

    <div class="p-3 bg-gray-50 rounded-xl mb-4 text-xs border border-gray-200">
      <div class="text-gray-500">Available: <span class="font-black text-gray-900"><?php echo zavora_format_price($vendor['balance']); ?></span></div>
      <div class="text-gray-500 mt-1">Bank: <span class="font-bold text-gray-800"><?php echo htmlspecialchars($vendor['bank_name']); ?> (<?php echo htmlspecialchars($vendor['account_number']); ?>)</span></div>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="request_payout">
      <div>
        <label class="block text-xs font-bold text-gray-700 mb-1">Amount to Withdraw (₦)</label>
        <input type="number" step="0.01" name="amount" max="<?php echo $vendor['balance']; ?>" value="<?php echo $vendor['balance']; ?>" required
               class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold focus:bg-white focus:outline-none">
      </div>
      <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-colors shadow-md">
        Confirm Bank Transfer
      </button>
    </form>
  </div>
</div>

<!-- Modal 3: AI Credit Top-Up (Paystack Checkout) -->
<div id="aiTopupModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl relative">
    <button onclick="closeAiTopupModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 text-lg">
      <i class="fas fa-times"></i>
    </button>
    <div class="flex items-center gap-2 mb-1">
      <span class="w-8 h-8 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 text-gray-950 flex items-center justify-center text-sm font-black shadow-sm">
        <i class="fas fa-wand-magic-sparkles"></i>
      </span>
      <h3 class="text-lg font-black text-[#0B132B] font-display">Top Up AI Studio Credits</h3>
    </div>
    <p class="text-xs text-gray-500 mb-5">Purchase credits for AI product descriptions, titles, and studio product imagery.</p>

    <div class="space-y-3 mb-6" id="topupPackList">
      <!-- Tier 1 -->
      <div onclick="selectTopupPack('starter')" id="pack-card-starter"
           class="pack-card p-3.5 border-2 border-[#1B2299] bg-blue-50/40 rounded-2xl cursor-pointer flex items-center justify-between transition">
        <div class="flex items-center gap-3">
          <input type="radio" name="pack_choice" id="pack_starter" value="starter" checked class="text-[#1B2299]">
          <div>
            <div class="text-xs font-bold text-gray-900">Starter Pack (25 Credits)</div>
            <div class="text-[10px] text-gray-500">₦100 per generation</div>
          </div>
        </div>
        <div class="text-sm font-black text-[#1B2299] font-display">₦2,500</div>
      </div>

      <!-- Tier 2 -->
      <div onclick="selectTopupPack('growth')" id="pack-card-growth"
           class="pack-card p-3.5 border border-gray-200 bg-white hover:bg-gray-50 rounded-2xl cursor-pointer flex items-center justify-between transition">
        <div class="flex items-center gap-3">
          <input type="radio" name="pack_choice" id="pack_growth" value="growth" class="text-[#1B2299]">
          <div>
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-bold text-gray-900">Growth Pack (75 Credits)</span>
              <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-black rounded">Save 20%</span>
            </div>
            <div class="text-[10px] text-gray-500">₦80 per generation</div>
          </div>
        </div>
        <div class="text-sm font-black text-[#1B2299] font-display">₦6,000</div>
      </div>

      <!-- Tier 3 -->
      <div onclick="selectTopupPack('pro')" id="pack-card-pro"
           class="pack-card p-3.5 border border-gray-200 bg-white hover:bg-gray-50 rounded-2xl cursor-pointer flex items-center justify-between transition">
        <div class="flex items-center gap-3">
          <input type="radio" name="pack_choice" id="pack_pro" value="pro" class="text-[#1B2299]">
          <div>
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-bold text-gray-900">Pro Merchant (200 Credits)</span>
              <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[9px] font-black rounded">Save 30%</span>
            </div>
            <div class="text-[10px] text-gray-500">₦70 per generation</div>
          </div>
        </div>
        <div class="text-sm font-black text-[#1B2299] font-display">₦14,000</div>
      </div>
    </div>

    <button type="button" id="btnConfirmTopup" onclick="submitTopupPack()"
            class="w-full py-3 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2">
      <i class="fas fa-lock"></i> <span>Proceed to Paystack Checkout</span>
    </button>
    <p class="text-[10px] text-gray-400 text-center mt-2.5 flex items-center justify-center gap-1.5">
      <i class="fas fa-shield-alt text-emerald-600"></i> Encrypted 256-Bit SSL payment processing
    </p>
  </div>
</div>

<script>
let selectedPack = 'starter';

function openAiTopupModal() {
  document.getElementById('aiTopupModal').classList.remove('hidden');
}

function closeAiTopupModal() {
  document.getElementById('aiTopupModal').classList.add('hidden');
}

function selectTopupPack(pack) {
  selectedPack = pack;
  ['starter', 'growth', 'pro'].forEach(p => {
    const card = document.getElementById('pack-card-' + p);
    const radio = document.getElementById('pack_' + p);
    if (p === pack) {
      card.className = 'pack-card p-3.5 border-2 border-[#1B2299] bg-blue-50/40 rounded-2xl cursor-pointer flex items-center justify-between transition';
      radio.checked = true;
    } else {
      card.className = 'pack-card p-3.5 border border-gray-200 bg-white hover:bg-gray-50 rounded-2xl cursor-pointer flex items-center justify-between transition';
      radio.checked = false;
    }
  });
}

function submitTopupPack() {
  buyAiCredits(selectedPack);
}

function buyAiCredits(pack) {
  const btn = document.getElementById('btnConfirmTopup');
  const origHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing Paystack Checkout...';
  }

  const formData = new FormData();
  formData.append('pack', pack);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=buy_credits"); ?>', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.authorization_url) {
      window.location.href = data.authorization_url;
    } else if (data.success && data.new_balance !== undefined) {
      alert(data.message || 'Payment successful! AI credits added.');
      updateCreditCounters(data.new_balance);
      closeAiTopupModal();
      location.reload();
    } else {
      alert(data.message || 'Unable to initialize credit purchase.');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network error connecting to payment gateway.');
  })
  .finally(() => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    }
  });
}

function updateCreditCounters(credits) {
  const c = parseInt(credits, 10);
  const kpi = document.getElementById('kpiAiCreditsDisplay');
  if (kpi) kpi.innerText = c + ' Credits';
  const tab = document.getElementById('tabAiCreditsBadge');
  if (tab) tab.innerText = c + ' credits';
  const modal = document.getElementById('modalAiCreditsCount');
  if (modal) modal.innerText = c;
  const tabBal = document.getElementById('aiTabBalanceDisplay');
  if (tabBal) tabBal.innerText = c + ' Credits';
}

let currentAiDrawerData = null;
let currentAiImageData = null;

function updateTitleCharCount(val) {
  const len = (val || '').length;
  const countEl = document.getElementById('npTitleCharCount');
  if (countEl) {
    countEl.innerText = len + ' / 90 chars';
    if (len >= 60 && len <= 90) {
      countEl.className = 'text-[10px] text-emerald-600 font-bold font-mono';
    } else if (len > 90) {
      countEl.className = 'text-[10px] text-amber-600 font-bold font-mono';
    } else {
      countEl.className = 'text-[10px] text-gray-400 font-mono';
    }
  }
}

function setAiImgMode(mode) {
  const input = document.getElementById('aiImgMode');
  if (input) input.value = mode;
  const btnHero = document.getElementById('btnModeHero');
  const btnLife = document.getElementById('btnModeLifestyle');
  if (mode === 'hero') {
    if (btnHero) { btnHero.className = 'px-2 py-0.5 rounded text-[10px] font-black bg-[#1B2299] text-white transition'; }
    if (btnLife) { btnLife.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-white text-gray-700 border border-gray-200 hover:border-gray-400 transition'; }
  } else {
    if (btnLife) { btnLife.className = 'px-2 py-0.5 rounded text-[10px] font-black bg-purple-700 text-white transition'; }
    if (btnHero) { btnHero.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-white text-gray-700 border border-gray-200 hover:border-gray-400 transition'; }
  }
}

function updateThumbnailPreview(url) {
  const wrap = document.getElementById('np_preview_wrap');
  const img = document.getElementById('np_preview_img');
  if (url && url.trim().length > 5) {
    img.src = url.trim();
    wrap.classList.remove('hidden');
  } else {
    wrap.classList.add('hidden');
  }
}

function generateModalAiCopy() {
  const keywords = document.getElementById('aiConceptInput').value.trim();
  const tone = document.getElementById('aiToneInput').value;
  const catSelect = document.getElementById('np_category_id');
  const categoryName = catSelect ? catSelect.options[catSelect.selectedIndex].text : 'General';
  const statusEl = document.getElementById('aiModalStatus');
  const btn = document.getElementById('btnAiGenCopy');

  if (!keywords) {
    alert('Please enter a few keywords or product concept first.');
    document.getElementById('aiConceptInput').focus();
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
  statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-blue-50 text-[#1B2299] block';
  statusEl.innerText = 'Consulting AI Merchant Studio (1 Credit)...';

  const fd = new FormData();
  fd.append('keywords', keywords);
  fd.append('tone', tone);
  fd.append('category_name', categoryName);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=generate_copy"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success && data.data) {
      currentAiDrawerData = data.data;
      renderAiListingDrawer(data.data);
      applyAiListingToForm();

      if (data.remaining_credits !== undefined) {
        updateCreditCounters(data.remaining_credits);
      }
      statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-emerald-50 text-emerald-800 block';
      statusEl.innerHTML = '<i class="fas fa-check-circle text-emerald-600"></i> Enterprise product listing drafted! Review below or edit directly. (1 credit deducted)';
    } else if (data.error === 'insufficient_credits') {
      statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-amber-50 text-amber-800 block';
      statusEl.innerText = data.message || '0 AI Credits remaining.';
      openAiTopupModal();
    } else {
      statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-rose-50 text-rose-800 block';
      statusEl.innerText = data.message || 'AI Generation failed.';
    }
  })
  .catch(err => {
    console.error(err);
    statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-rose-50 text-rose-800 block';
    statusEl.innerText = 'Network connection error.';
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-pen-nib"></i> <span>Auto-Draft Title & Copy</span>';
  });
}

function renderAiListingDrawer(d) {
  const drawer = document.getElementById('aiListingReviewerDrawer');
  if (!drawer) return;
  drawer.classList.remove('hidden');

  const len = (d.title || '').length;
  const lenEl = document.getElementById('aiReviewTitleLen');
  if (lenEl) {
    lenEl.innerText = len + ' / 90 chars (GS1 Formula)';
    lenEl.className = 'text-[10px] font-bold px-2 py-0.5 rounded ' + (len <= 90 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200');
  }

  const bulletsWrap = document.getElementById('aiReviewBulletsList');
  if (bulletsWrap && Array.isArray(d.bullet_points)) {
    bulletsWrap.innerHTML = d.bullet_points.map(b => `
      <div class="flex items-start gap-1.5 p-1.5 rounded-lg bg-gray-50 border border-gray-150">
        <i class="fas fa-check-circle text-emerald-600 mt-0.5 text-[10px] flex-shrink-0"></i>
        <span>${escapeHtml(b)}</span>
      </div>
    `).join('');
  }

  const specsWrap = document.getElementById('aiReviewSpecsGrid');
  if (specsWrap && d.specifications_map) {
    specsWrap.innerHTML = Object.entries(d.specifications_map).map(([k, v]) => `
      <div class="p-1 rounded bg-gray-50 border border-gray-150 truncate">
        <strong class="text-gray-500 uppercase text-[9px]">${escapeHtml(k)}:</strong>
        <span class="text-gray-900 font-semibold">${escapeHtml(v)}</span>
      </div>
    `).join('');
  }
}

function applyAiListingToForm() {
  if (!currentAiDrawerData) return;
  const d = currentAiDrawerData;
  const titleInput = document.getElementById('np_title');
  const descInput = document.getElementById('np_description');
  const specsInput = document.getElementById('np_specifications');

  if (titleInput && d.title) {
    titleInput.value = d.title;
    updateTitleCharCount(d.title);
  }
  if (descInput) {
    descInput.value = d.full_description || d.description || '';
  }
  if (specsInput) {
    specsInput.value = d.specifications || d.specifications_pipe || '';
  }

  const btn = document.getElementById('btnApplyAiListing');
  if (btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> <span>Applied!</span>';
    btn.classList.add('bg-emerald-700');
    setTimeout(() => {
      btn.innerHTML = orig;
      btn.classList.remove('bg-emerald-700');
    }, 1500);
  }
}

function generateModalAiImage() {
  const keywords = document.getElementById('aiConceptInput').value.trim() || document.getElementById('np_title').value.trim();
  const statusEl = document.getElementById('aiModalStatus');
  const btn = document.getElementById('btnAiGenImage');
  const mode = document.getElementById('aiImgMode')?.value || 'hero';

  if (!keywords) {
    alert('Please enter a product title or concept keywords for image generation.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Photo...';
  statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-purple-50 text-purple-700 block';
  statusEl.innerText = 'Rendering commercial product photo (' + (mode === 'hero' ? 'Studio Pure White' : 'Lifestyle Setting') + ')...';

  const fd = new FormData();
  fd.append('prompt', keywords);
  fd.append('mode', mode);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=generate_image"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success && data.image_url) {
      currentAiImageData = data;
      document.getElementById('np_thumbnail').value = data.image_url;
      updateThumbnailPreview(data.image_url);

      renderAiImagePreviews(data);

      if (data.remaining_credits !== undefined) {
        updateCreditCounters(data.remaining_credits);
      }
      statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-emerald-50 text-emerald-800 block';
      statusEl.innerHTML = '<i class="fas fa-check-circle text-emerald-600"></i> Commercial photo generated and attached! (1 credit deducted)';
    } else if (data.error === 'insufficient_credits') {
      statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-amber-50 text-amber-800 block';
      statusEl.innerText = data.message || '0 AI Credits remaining.';
      openAiTopupModal();
    } else {
      statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-rose-50 text-rose-800 block';
      statusEl.innerText = data.message || 'Image generation failed.';
    }
  })
  .catch(err => {
    console.error(err);
    statusEl.className = 'text-[11px] font-bold mt-2 p-2 rounded-lg bg-rose-50 text-rose-800 block';
    statusEl.innerText = 'Network error generating image.';
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-image"></i> <span>Generate Photo</span>';
  });
}

function renderAiImagePreviews(data) {
  const wrap = document.getElementById('aiReviewImagesWrap');
  if (!wrap) return;
  wrap.classList.remove('hidden');

  const heroImg = document.getElementById('imgHeroPreview');
  const lifeImg = document.getElementById('imgLifePreview');
  if (heroImg && data.hero_url) heroImg.src = data.hero_url;
  if (lifeImg && data.lifestyle_url) lifeImg.src = data.lifestyle_url;

  if (data.mode === 'lifestyle') {
    selectAiLifestyleImage();
  } else {
    selectAiHeroImage();
  }
}

function selectAiHeroImage() {
  if (!currentAiImageData || !currentAiImageData.hero_url) return;
  document.getElementById('np_thumbnail').value = currentAiImageData.hero_url;
  updateThumbnailPreview(currentAiImageData.hero_url);

  const cardHero = document.getElementById('cardHeroPreview');
  const cardLife = document.getElementById('cardLifePreview');
  const iconHero = document.getElementById('iconHeroSelected');
  const iconLife = document.getElementById('iconLifeSelected');

  if (cardHero) cardHero.className = 'cursor-pointer p-1.5 rounded-xl border-2 border-[#1B2299] bg-white transition hover:shadow';
  if (cardLife) cardLife.className = 'cursor-pointer p-1.5 rounded-xl border-2 border-transparent bg-white transition hover:shadow';
  if (iconHero) iconHero.className = 'fas fa-check-circle text-[#1B2299]';
  if (iconLife) iconLife.className = 'fas fa-circle text-gray-300';
}

function selectAiLifestyleImage() {
  if (!currentAiImageData || !currentAiImageData.lifestyle_url) return;
  document.getElementById('np_thumbnail').value = currentAiImageData.lifestyle_url;
  updateThumbnailPreview(currentAiImageData.lifestyle_url);

  const cardHero = document.getElementById('cardHeroPreview');
  const cardLife = document.getElementById('cardLifePreview');
  const iconHero = document.getElementById('iconHeroSelected');
  const iconLife = document.getElementById('iconLifeSelected');

  if (cardLife) cardLife.className = 'cursor-pointer p-1.5 rounded-xl border-2 border-purple-700 bg-white transition hover:shadow';
  if (cardHero) cardHero.className = 'cursor-pointer p-1.5 rounded-xl border-2 border-transparent bg-white transition hover:shadow';
  if (iconLife) iconLife.className = 'fas fa-check-circle text-purple-700';
  if (iconHero) iconHero.className = 'fas fa-circle text-gray-300';
}

function runSandboxCopy() {
  const keywords = document.getElementById('sbCopyKeywords').value.trim();
  const categoryName = document.getElementById('sbCopyCategory').value;
  const tone = document.getElementById('sbCopyTone').value;
  const btn = document.getElementById('btnSbCopy');

  if (!keywords) {
    alert('Please enter product keywords or features.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Drafting with AI...';

  const fd = new FormData();
  fd.append('keywords', keywords);
  fd.append('category_name', categoryName);
  fd.append('tone', tone);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=generate_copy"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success && data.data) {
      window.sandboxLastCopy = data.data;
      const d = data.data;
      document.getElementById('sbResTitle').innerText = d.title || '';
      document.getElementById('sbResTitleCount').innerText = (d.title || '').length + ' / 90 chars';

      const bulletsEl = document.getElementById('sbResBullets');
      if (bulletsEl && Array.isArray(d.bullet_points)) {
        bulletsEl.innerHTML = d.bullet_points.map(b => `
          <div class="flex items-start gap-1.5">
            <i class="fas fa-check-circle text-emerald-600 mt-0.5 text-[11px] flex-shrink-0"></i>
            <span>${escapeHtml(b)}</span>
          </div>
        `).join('');
      }

      document.getElementById('sbResDesc').innerText = d.description || '';

      const specsEl = document.getElementById('sbResSpecsTable');
      if (specsEl && d.specifications_map) {
        specsEl.innerHTML = Object.entries(d.specifications_map).map(([k, v]) => `
          <div class="p-1 rounded bg-white border border-gray-150 truncate">
            <strong class="text-gray-500 uppercase text-[9px]">${escapeHtml(k)}:</strong>
            <span class="text-gray-900 font-semibold">${escapeHtml(v)}</span>
          </div>
        `).join('');
      }

      document.getElementById('sbCopyResult').classList.remove('hidden');
      if (data.remaining_credits !== undefined) {
        updateCreditCounters(data.remaining_credits);
      }
    } else if (data.error === 'insufficient_credits') {
      alert(data.message);
      openAiTopupModal();
    } else {
      alert(data.message || 'Copy generation failed.');
    }
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-sparkles"></i> <span>Generate Title & Copy</span>';
  });
}

function createProductFromSandbox() {
  if (!window.sandboxLastCopy) return;
  sessionStorage.setItem('zavora_prefill_draft', JSON.stringify(window.sandboxLastCopy));
  window.location.href = '<?php echo zavora_asset("seller/product-create.php?prefill=1"); ?>';
}

function runSandboxImage() {
  const prompt = document.getElementById('sbImgPrompt').value.trim();
  const mode = document.getElementById('sbImgMode').value;
  const btn = document.getElementById('btnSbImage');

  if (!prompt) {
    alert('Please enter a visual concept prompt.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rendering Asset...';

  const fd = new FormData();
  fd.append('prompt', prompt);
  fd.append('mode', mode);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=generate_image"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success && data.image_url) {
      window.sandboxLastImage = data;
      const heroTag = document.getElementById('sbResHeroTag');
      const lifeTag = document.getElementById('sbResLifeTag');
      if (heroTag && data.hero_url) heroTag.src = data.hero_url;
      if (lifeTag && data.lifestyle_url) lifeTag.src = data.lifestyle_url;

      document.getElementById('sbResImgUrl').value = data.image_url;
      document.getElementById('sbImgResult').classList.remove('hidden');

      if (data.remaining_credits !== undefined) {
        updateCreditCounters(data.remaining_credits);
      }
    } else if (data.error === 'insufficient_credits') {
      alert(data.message);
      openAiTopupModal();
    } else {
      alert(data.message || 'Image generation failed.');
    }
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> <span>Generate Studio Imagery</span>';
  });
}

function copySandboxImgUrl(type) {
  if (!window.sandboxLastImage) return;
  const url = (type === 'hero') ? window.sandboxLastImage.hero_url : window.sandboxLastImage.lifestyle_url;
  if (!url) return;
  navigator.clipboard.writeText(url).then(() => {
    alert('Copied ' + (type === 'hero' ? 'Hero Shot' : 'Lifestyle') + ' URL to clipboard!');
  }).catch(() => {
    prompt('Copy URL:', url);
  });
}

function useSandboxImgInListing() {
  if (!window.sandboxLastImage) return;
  sessionStorage.setItem('zavora_prefill_image', window.sandboxLastImage.image_url);
  window.location.href = '<?php echo zavora_asset("seller/product-create.php?prefill=1"); ?>';
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
  });
}

function switchVendorTab(tabId) {
  document.querySelectorAll('.vtab-btn').forEach(btn => {
    btn.classList.remove('border-[#1B2299]', 'text-[#1B2299]');
    btn.classList.add('border-transparent', 'text-gray-500');
  });
  const activeBtn = document.getElementById('vtab-btn-' + tabId);
  if (activeBtn) {
    activeBtn.classList.add('border-[#1B2299]', 'text-[#1B2299]');
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
  }

  document.getElementById('vtab-products').classList.add('hidden');
  document.getElementById('vtab-settings').classList.add('hidden');
  document.getElementById('vtab-orders').classList.add('hidden');
  document.getElementById('vtab-payouts').classList.add('hidden');
  const aiTab = document.getElementById('vtab-ai');
  if (aiTab) aiTab.classList.add('hidden');

  const target = document.getElementById('vtab-' + tabId);
  if (target) {
    target.classList.remove('hidden');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
