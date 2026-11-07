<?php
require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();

// 1. Enforce Vendor Authentication
$vendorId = $_SESSION['zavora_vendor_id'] ?? null;
if (!$vendorId) {
    header('Location: ' . zavora_asset('seller/login.php'));
    exit;
}

// Fetch Vendor Record
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();
if (!$vendor) {
    unset($_SESSION['zavora_vendor_id']);
    header('Location: ' . zavora_asset('seller/login.php'));
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
        unset($_SESSION['zavora_vendor_id']);
        unset($_SESSION['zavora_vendor_name']);
        header('Location: ' . zavora_asset('seller/login.php'));
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
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm flex flex-col justify-between">
      <div>
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Available Payout Balance</span>
        <p class="text-2xl font-black text-emerald-700 mt-2 font-display"><?php echo zavora_format_price($vendor['balance']); ?></p>
      </div>
      <button onclick="document.getElementById('payoutModal').classList.remove('hidden')" 
              class="mt-4 w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors shadow-sm flex items-center justify-center gap-1.5">
        <i class="fas fa-money-bill-transfer"></i> Withdraw to Bank
      </button>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Gross Store Sales</span>
      <p class="text-2xl font-black text-[#0B132B] mt-2 font-display"><?php echo zavora_format_price($totalVendorSales); ?></p>
      <p class="text-[11px] text-gray-500 font-medium mt-1">Across all product lines</p>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
      <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Active Products</span>
      <p class="text-2xl font-black text-[#1B2299] mt-2 font-display"><?php echo count($vendorProducts); ?></p>
      <p class="text-[11px] text-gray-500 font-medium mt-1">Listed in catalog</p>
    </div>

    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm flex flex-col justify-between">
      <div>
        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Boost Credits</span>
        <p class="text-2xl font-black text-amber-600 mt-2 font-display"><?php echo intval($vendor['boost_credits'] ?? 5); ?> Left</p>
      </div>
      <span class="text-[11px] text-amber-700 font-medium mt-1 flex items-center gap-1">
        <i class="fas fa-bolt text-amber-500"></i> Promotes items to top of search
      </span>
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
    </div>

    <!-- TAB 1: PRODUCTS & INVENTORY -->
    <div id="vtab-products" class="p-6">
      <div class="flex items-center justify-between gap-4 mb-6">
        <div>
          <h2 class="text-lg font-black text-[#0B132B] font-display">Product & Inventory Manager</h2>
          <p class="text-xs text-gray-500">Configure variety options, set preorders, boost visibility, and adjust stock in real-time.</p>
        </div>
        <button onclick="document.getElementById('newProductModal').classList.remove('hidden')"
                class="px-4 py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-2">
          <i class="fas fa-plus"></i>
          <span>Add New Product</span>
        </button>
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
                <td colspan="6" class="p-8 text-center text-gray-400 italic">No products added yet. Click "Add New Product" to list your first item.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($vendorProducts as $prod): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="p-3">
                    <div class="flex items-center gap-3">
                      <img src="<?php echo htmlspecialchars($prod['thumbnail']); ?>" class="w-12 h-12 rounded-xl object-cover border border-gray-200 flex-shrink-0">
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
                    <a href="<?php echo zavora_asset('product.php?id=' . $prod['id']); ?>" target="_blank"
                       class="text-[#1B2299] hover:underline font-bold text-xs inline-flex items-center gap-1">
                      <span>View</span>
                      <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                    </a>
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

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="add_product">

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Title *</label>
        <input type="text" name="title" required placeholder="e.g. Wireless Ergonomic Mechanical Keyboard"
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Category</label>
          <select name="category_id" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
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
        <input type="url" name="thumbnail" placeholder="https://images.unsplash.com/..."
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Description</label>
        <textarea name="description" rows="2" placeholder="Key features, specifications, and warranty details..."
                  class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Technical Specifications</label>
        <textarea name="specifications" rows="2" placeholder="Processor, Battery, Dimensions, Material, Warranty..."
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

<script>
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

  const target = document.getElementById('vtab-' + tabId);
  if (target) {
    target.classList.remove('hidden');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
