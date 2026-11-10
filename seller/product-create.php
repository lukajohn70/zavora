<?php
/**
 * ZAVORA Vendor Central — Full-Page Enterprise Product Creation Suite
 *
 * Includes:
 * - GS1 Formula E-Commerce Title Validator
 * - Key Features & Highlights Editor (Jumia / Konga Standard)
 * - Quill Rich Text WYSIWYG Product Description Editor
 * - Structured Two-Column Technical Specification Matrix
 * - What's In The Box Package Contents Editor
 * - Multi-Angle AI Studio Photography & Online Sourcing Gallery
 * - Product Variety / Variants Matrix Generator
 * - Pre-Order Allocation Engine
 * - Real-Time Storefront Live Preview Card
 */

require_once __DIR__ . '/../config/config.php';
$pdo = zavora_get_db();

// 1. Enforce Strict Vendor Session Control Guard
$vendor = zavora_vendor_require_auth();
$vendorId = (int)$vendor['id'];

// 2. Fetch Categories
$catsStmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
$categories = $catsStmt->fetchAll();

$flashMessage = '';
$flashType = '';

// 3. Handle Product Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_product') {
    $title = trim($_POST['title'] ?? '');
    $categoryId = intval($_POST['category_id'] ?? 1);
    $type = in_array($_POST['type'] ?? '', ['physical', 'digital']) ? $_POST['type'] : 'physical';
    $price = floatval($_POST['price'] ?? 0);
    $prevPrice = !empty($_POST['previous_price']) ? floatval($_POST['previous_price']) : null;
    $stock = intval($_POST['stock_quantity'] ?? 50);
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $narrativeDesc = trim($_POST['narrative_description'] ?? $_POST['description'] ?? '');

    // Process Direct Product Image Upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowedExts) && $file['size'] <= 5 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $newFileName = 'prod_' . $vendorId . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
                $thumbnail = 'uploads/products/' . $newFileName;
            }
        }
    }

    // Collect Key Features & Highlights
    $rawFeatures = $_POST['key_features'] ?? [];
    if (!is_array($rawFeatures)) $rawFeatures = [];
    for ($b = 1; $b <= 5; $b++) {
        if (!empty($_POST["bullet_{$b}"])) {
            $rawFeatures[] = trim($_POST["bullet_{$b}"]);
        }
    }
    $features = array_values(array_filter(array_map('trim', $rawFeatures)));
    if (!empty($features)) {
        $formattedBullets = "KEY HIGHLIGHTS:\n• " . implode("\n• ", $features);
        $fullDescription = $formattedBullets . "\n\nPRODUCT OVERVIEW:\n" . $narrativeDesc;
    } else {
        $fullDescription = $narrativeDesc;
    }

    // Collect Specifications (Structured Key-Values)
    $specKeys = $_POST['spec_keys'] ?? [];
    $specVals = $_POST['spec_vals'] ?? [];
    $specsPairs = [];
    if (is_array($specKeys) && is_array($specVals)) {
        foreach ($specKeys as $idx => $k) {
            $k = trim($k);
            $v = trim($specVals[$idx] ?? '');
            if (!empty($k) && !empty($v)) {
                $specsPairs[] = "{$k}: {$v}";
            }
        }
    }
    // What's in the box
    $boxContents = trim($_POST['box_contents'] ?? '');
    if (!empty($boxContents)) {
        $specsPairs[] = "What's in the box: {$boxContents}";
    }
    $specsString = !empty($specsPairs) ? implode(' | ', $specsPairs) : trim($_POST['raw_specifications'] ?? '');

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
        $flashMessage = 'Please provide a valid product title and selling price.';
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
            $thumbnail, $fullDescription, $specsString, $isPreorder, $preorderRelease, $preorderDeposit, 
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

        header('Location: ' . zavora_asset('seller/index.php?tab=products&created=1'));
        exit;
    }
}

$title = 'List New Product — ZAVORA Vendor Central';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Quill Rich Text WYSIWYG Editor Styles & Scripts -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<div class="min-h-screen bg-slate-50/60 pb-20">

  <!-- Sticky Enterprise Top Bar -->
  <div class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-gray-200 px-4 lg:px-8 py-3.5">
    <div class="container mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <a href="<?php echo zavora_asset('seller/index.php'); ?>" 
           class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 flex items-center justify-center transition" title="Back to Dashboard">
          <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div>
          <div class="flex items-center gap-2">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Vendor Studio</span>
            <span class="text-gray-300">•</span>
            <span class="text-[11px] font-bold text-[#1B2299]"><?php echo htmlspecialchars($vendor['store_name']); ?></span>
          </div>
          <h1 class="text-lg font-black text-gray-900 font-display flex items-center gap-2">
            <span>List a New Product</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-[#1B2299] uppercase tracking-wider">Enterprise Catalog Suite</span>
          </h1>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <!-- AI Credits Pill -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-200/80">
          <i class="fas fa-sparkles text-amber-500 text-xs"></i>
          <span class="text-xs font-black text-amber-900" id="topAiCredits"><?php echo intval($vendor['ai_credits']); ?> Credits</span>
          <a href="<?php echo zavora_asset('seller/index.php#vtab-ai'); ?>" class="text-[10px] text-amber-700 font-bold hover:underline">+ Top Up</a>
        </div>

        <a href="<?php echo zavora_asset('seller/index.php'); ?>" 
           class="px-4 py-2 border border-gray-200 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-xl transition">
          Discard
        </a>
        <button type="button" onclick="submitProductForm()" 
                class="px-5 py-2 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-extrabold rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2">
          <i class="fas fa-cloud-arrow-up"></i>
          <span>Publish to Storefront</span>
        </button>
      </div>
    </div>
  </div>

  <div class="container mx-auto px-4 lg:px-8 py-8">

    <?php if (!empty($flashMessage)): ?>
      <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-600"></i>
        <span><?php echo htmlspecialchars($flashMessage); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" id="mainProductForm" enctype="multipart/form-data" class="grid lg:grid-cols-12 gap-8 items-start">
      <input type="hidden" name="action" value="create_product">

      <!-- ════════════════════════════════════════════════════════════════════════
           LEFT COLUMN: CORE PRODUCT LISTING ATTRIBUTES (7 Cols)
           ════════════════════════════════════════════════════════════════════════ -->
      <div class="lg:col-span-7 space-y-6">

        <!-- CARD 1: TITLE & BASIC TAXONOMY -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-5">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <i class="fas fa-tag text-[#1B2299]"></i> Product Title & Taxonomy
            </h3>
            <span class="text-[10px] font-bold text-gray-400 uppercase">GS1 International Standard</span>
          </div>

          <!-- Product Title with Active GS1 Length Validator -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-extrabold text-gray-800 uppercase">Product Title *</label>
              <span id="gs1TitleCounter" class="text-[11px] font-mono font-bold text-gray-400">0 / 90 chars</span>
            </div>
            <input type="text" name="title" id="full_title" required
                   placeholder="e.g. Sony WH-1000XM5 — Wireless Active Noise Canceling Headphones, Midnight Black"
                   oninput="onTitleChanged(this.value)"
                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299] transition">
            <div class="flex items-center justify-between mt-1 text-[11px] text-gray-500">
              <span>Formula: <code class="bg-gray-100 px-1 py-0.5 rounded text-[10px] text-gray-700">[Brand] + [Model] + [Defining Feature] + [Color/Spec]</code></span>
              <span id="gs1CompliancePill" class="text-[10px] font-bold text-gray-400">Needs 60-90 chars</span>
            </div>
          </div>

          <!-- Category & Type Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Catalog Category *</label>
              <select name="category_id" id="full_category" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:bg-white focus:outline-none">
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Product Classification</label>
              <select name="type" id="full_type" onchange="onTypeChanged(this.value)" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-800 focus:bg-white focus:outline-none">
                <option value="physical">Physical Product (Requires Shipping / Delivery)</option>
                <option value="digital">Digital Product (Instant Download / License)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- CARD 2: PRICING & INVENTORY -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-5">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <i class="fas fa-coins text-emerald-600"></i> Pricing & Inventory Management
            </h3>
            <span id="discountBadge" class="hidden px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800">
              0% OFF
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Selling Price (₦) *</label>
              <input type="number" step="0.01" name="price" id="full_price" required placeholder="45000"
                     oninput="calculateDiscount()"
                     class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-black text-[#1B2299] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Compare-at Price (₦)</label>
              <input type="number" step="0.01" name="previous_price" id="full_prev_price" placeholder="60000"
                     oninput="calculateDiscount()"
                     class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-500 line-through focus:bg-white focus:outline-none">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Available Stock Units</label>
              <input type="number" name="stock_quantity" id="full_stock" value="50"
                     oninput="onStockChanged(this.value)"
                     class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-800 focus:bg-white focus:outline-none">
            </div>
          </div>
        </div>

        <!-- CARD 3: PRODUCT VARIETY & VARIANTS MATRIX -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="has_variants" value="1" id="cbHasVariants" onchange="toggleVarietySection(this.checked)" class="rounded text-[#1B2299] w-4 h-4">
              <span class="text-sm font-black text-gray-900 uppercase tracking-wider">Product Variety & Variants (Colors, Sizes, Specs)</span>
            </label>
            <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Automated Matrix</span>
          </div>

          <div id="varietyContainer" class="hidden space-y-4 pt-1">
            <p class="text-xs text-gray-500">Provide comma-separated options. ZAVORA will automatically generate individual child SKUs and stock distributions.</p>
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Available Colors</label>
              <input type="text" name="variant_colors" id="var_colors" placeholder="Midnight Black, Platinum Silver, Alpine Blue"
                     oninput="renderVariantMatrix()"
                     class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Available Sizes (if applicable)</label>
                <input type="text" name="variant_sizes" id="var_sizes" placeholder="S, M, L, XL or 42, 43, 44"
                       oninput="renderVariantMatrix()"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Storage / Hardware Specifications</label>
                <input type="text" name="variant_specs" id="var_specs" placeholder="128GB, 256GB, 512GB or 8GB/256GB"
                       oninput="renderVariantMatrix()"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              </div>
            </div>

            <!-- Real-Time Variant Matrix Preview Table -->
            <div id="variantMatrixWrap" class="hidden mt-3 p-3 bg-slate-50 border border-slate-200 rounded-2xl">
              <span class="text-[10px] font-black text-gray-600 uppercase tracking-wider block mb-2">Generated Child Variants Preview</span>
              <div id="variantMatrixList" class="flex flex-wrap gap-1.5 text-xs"></div>
            </div>
          </div>
        </div>

        <!-- CARD 4: PRE-ORDER ALLOCATION CONFIGURATION -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="is_preorder" value="1" id="cbIsPreorder" onchange="togglePreorderSection(this.checked)" class="rounded text-indigo-600 w-4 h-4">
              <span class="text-sm font-black text-gray-900 uppercase tracking-wider">Enable Pre-Order for this Product</span>
            </label>
            <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-full">Early Reservation</span>
          </div>

          <div id="preorderContainer" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Expected Release / Dispatch Date</label>
              <input type="date" name="preorder_release_date" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Minimum Booking Deposit (₦, Optional)</label>
              <input type="number" step="0.01" name="preorder_deposit" placeholder="0 for full payment"
                     class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none">
            </div>
          </div>
        </div>

        <!-- CARD 5: KEY FEATURES & HIGHLIGHTS -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-list-check text-[#1B2299]"></i> Key Features & Highlights
              </h3>
              <p class="text-[11px] text-gray-400">Essential customer benefit drivers shown prominently beside the product gallery.</p>
            </div>
            <button type="button" onclick="addKeyFeatureRow()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-gray-800 text-[11px] font-bold rounded-lg transition flex items-center gap-1">
              <i class="fas fa-plus"></i> Add Feature
            </button>
          </div>

          <div id="keyFeaturesContainer" class="space-y-2.5">
            <div class="flex items-center gap-2 feature-row">
              <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">✓</span>
              <input type="text" name="key_features[]" placeholder="e.g. 7,500mAh Massive Battery: Up to 3 full days of heavy usage with 33W fast charging."
                     class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              <button type="button" onclick="removeKeyFeatureRow(this)" class="text-gray-300 hover:text-rose-500 transition px-1"><i class="fas fa-trash-can text-xs"></i></button>
            </div>
            <div class="flex items-center gap-2 feature-row">
              <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">✓</span>
              <input type="text" name="key_features[]" placeholder="e.g. 6.78-inch FHD+ 120Hz Display: Ultra-smooth scrolling and vivid sunlight visibility."
                     class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              <button type="button" onclick="removeKeyFeatureRow(this)" class="text-gray-300 hover:text-rose-500 transition px-1"><i class="fas fa-trash-can text-xs"></i></button>
            </div>
            <div class="flex items-center gap-2 feature-row">
              <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">✓</span>
              <input type="text" name="key_features[]" placeholder="e.g. 50MP AI Ultra-Clear Main Camera: Crisp low-light photography and 2K video recording."
                     class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              <button type="button" onclick="removeKeyFeatureRow(this)" class="text-gray-300 hover:text-rose-500 transition px-1"><i class="fas fa-trash-can text-xs"></i></button>
            </div>
          </div>
        </div>

        <!-- CARD 6: RICH TEXT PRODUCT DESCRIPTION (QUILL WYSIWYG) -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-pen-nib text-[#1B2299]"></i> Product Details & Description
              </h3>
              <p class="text-[11px] text-gray-400">Professional rich text editor with formatting, headings, and bullet points.</p>
            </div>
            <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">Quill WYSIWYG</span>
          </div>

          <!-- Quill Container -->
          <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white shadow-2xs">
            <div id="quill-editor" class="min-h-[220px] text-sm text-gray-800 leading-relaxed p-4"></div>
          </div>
          <input type="hidden" name="narrative_description" id="full_narrative">
          <p class="text-[11px] text-gray-400 flex items-center gap-1.5">
            <i class="fas fa-info-circle text-[#1B2299]"></i> Formatted text, bold points, subheadings, and lists will render cleanly on your storefront product page.
          </p>
        </div>

        <!-- CARD 7: STRUCTURED TECHNICAL SPECIFICATIONS MATRIX -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-microchip text-[#1B2299]"></i> Technical Specifications Matrix
              </h3>
              <p class="text-[11px] text-gray-400">Renders as a clean 2-column alternating table on your storefront.</p>
            </div>
            <button type="button" onclick="addSpecRow()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-gray-800 text-[11px] font-bold rounded-lg transition flex items-center gap-1">
              <i class="fas fa-plus"></i> Add Row
            </button>
          </div>

          <div id="specRowsContainer" class="space-y-2">
            <!-- Dynamic rows will be inserted here or populated by AI Studio -->
            <div class="grid grid-cols-12 gap-2 spec-row">
              <input type="text" name="spec_keys[]" value="Brand" placeholder="Metric (e.g. Brand)" class="col-span-5 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 focus:bg-white focus:outline-none">
              <input type="text" name="spec_vals[]" value="<?php echo htmlspecialchars($vendor['store_name']); ?>" placeholder="Value" class="col-span-6 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 font-semibold focus:bg-white focus:outline-none">
              <button type="button" onclick="removeSpecRow(this)" class="col-span-1 text-gray-300 hover:text-rose-500 transition"><i class="fas fa-trash-can text-xs"></i></button>
            </div>
            <div class="grid grid-cols-12 gap-2 spec-row">
              <input type="text" name="spec_keys[]" value="Warranty Coverage" placeholder="Metric" class="col-span-5 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 focus:bg-white focus:outline-none">
              <input type="text" name="spec_vals[]" value="12-Month Official Warranty" placeholder="Value" class="col-span-6 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 font-semibold focus:bg-white focus:outline-none">
              <button type="button" onclick="removeSpecRow(this)" class="col-span-1 text-gray-300 hover:text-rose-500 transition"><i class="fas fa-trash-can text-xs"></i></button>
            </div>
          </div>
        </div>

        <!-- CARD 8: WHAT'S IN THE BOX -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-box-open text-[#1B2299]"></i> What's In The Box
              </h3>
              <p class="text-[11px] text-gray-400">List all included accessories, manuals, and contents in the package.</p>
            </div>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">Package Contents</span>
          </div>
          <div>
            <input type="text" name="box_contents" id="box_contents" placeholder="e.g. 1x Smartphone, 1x Power Adapter, 1x USB-C Cable, 1x Protective Case, 1x Warranty Card"
                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 focus:bg-white focus:outline-none">
          </div>
        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════════════════════
           RIGHT COLUMN: AI STUDIO & LIVE MEDIA SUITE (5 Cols)
           ════════════════════════════════════════════════════════════════════════ -->
      <div class="lg:col-span-5 space-y-6">

        <!-- CARD A: PRODUCT PHOTOGRAPHY & DIRECT IMAGE UPLOAD -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-7 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h4 class="text-xs font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-camera text-[#1B2299]"></i> Product Photography & Media
              </h4>
              <p class="text-[10px] text-gray-400">Upload high-resolution product photos from your device.</p>
            </div>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">Primary Image</span>
          </div>

          <!-- Direct Device File Upload Zone -->
          <div>
            <div id="dropzoneWrap" onclick="document.getElementById('productImageInput').click()" 
                 class="cursor-pointer border-2 border-dashed border-gray-300 hover:border-[#1B2299] bg-slate-50/80 hover:bg-indigo-50/20 rounded-2xl p-6 text-center transition-all group">
              <input type="file" name="product_image" id="productImageInput" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" onchange="handleImageFileSelect(this)">
              
              <div id="uploadPrompt" class="space-y-2.5">
                <div class="w-12 h-12 rounded-2xl bg-white border border-gray-200 shadow-2xs text-[#1B2299] group-hover:scale-110 flex items-center justify-center mx-auto transition-transform">
                  <i class="fas fa-cloud-arrow-up text-lg"></i>
                </div>
                <div>
                  <p class="text-xs font-black text-gray-900">Click to upload from device or drag & drop</p>
                  <p class="text-[10px] text-gray-400 mt-0.5">PNG, JPG, WEBP or GIF (Up to 5MB)</p>
                </div>
                <span class="inline-block px-3.5 py-1.5 bg-white border border-gray-200 text-[#1B2299] text-xs font-bold rounded-xl shadow-2xs group-hover:border-[#1B2299] transition">
                  <i class="fas fa-folder-open mr-1"></i> Browse Files
                </span>
              </div>

              <!-- Selected Upload Preview -->
              <div id="uploadSelectedPreview" class="hidden space-y-2">
                <div class="w-28 h-28 mx-auto rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-white p-1">
                  <img id="uploadImgThumb" src="" class="w-full h-full object-contain">
                </div>
                <p id="uploadFileName" class="text-xs font-bold text-gray-800 truncate max-w-xs mx-auto"></p>
                <button type="button" onclick="clearSelectedImage(event)" class="text-[11px] font-bold text-rose-600 hover:underline">
                  <i class="fas fa-trash-can mr-1"></i> Remove & Choose Different Photo
                </button>
              </div>
            </div>
          </div>

          <!-- Secondary / External Image URL Option -->
          <div class="pt-3 border-t border-gray-100">
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-[11px] font-bold text-gray-600 uppercase">Or Web Image URL (Optional)</label>
              <span class="text-[10px] text-gray-400">External CDN / Unsplash</span>
            </div>
            <input type="url" name="thumbnail" id="activeThumbnailInput"
                   placeholder="https://images.unsplash.com/photo-..."
                   oninput="onThumbnailUrlChanged(this.value)"
                   class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono text-gray-700 focus:bg-white focus:outline-none">
          </div>
        </div>

        <!-- CARD B: REAL-TIME STOREFRONT LIVE PREVIEW -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-7 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h4 class="text-xs font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <i class="fas fa-desktop text-emerald-600"></i> Storefront Live Preview
            </h4>
            <span class="text-[10px] font-bold text-gray-400">Buyer View</span>
          </div>

          <!-- Simulated Storefront Card -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="aspect-square rounded-xl bg-white border border-gray-200 flex items-center justify-center p-3 relative overflow-hidden">
              <img id="previewImg" src="https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800" class="w-full h-full object-contain">
              <span id="previewDiscountBadge" class="hidden absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-black bg-[#E4306D] text-white">
                -25%
              </span>
            </div>

            <div>
              <div class="flex items-center justify-between text-[10px] text-gray-400 uppercase font-bold mb-1">
                <span id="previewCategory">Category</span>
                <span class="text-emerald-600 flex items-center gap-1"><i class="fas fa-check-circle"></i> Verified</span>
              </div>
              <h5 id="previewTitle" class="text-xs font-black text-gray-900 line-clamp-2 leading-snug">
                Product Title will appear here in real-time...
              </h5>
              <div class="flex items-baseline gap-2 mt-2">
                <span id="previewPrice" class="text-sm font-black text-[#1B2299]">₦0.00</span>
                <span id="previewOldPrice" class="text-xs text-gray-400 line-through hidden">₦0.00</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD C: AI CATALOG STUDIO (COMING SOON) -->
        <div class="bg-gradient-to-br from-indigo-950 via-slate-900 to-purple-950 text-white rounded-3xl p-6 sm:p-7 border border-indigo-900/60 shadow-xl space-y-4 relative overflow-hidden">
          <div class="flex items-center justify-between pb-3 border-b border-white/10">
            <div class="flex items-center gap-2">
              <span class="w-7 h-7 rounded-lg bg-amber-400/20 text-amber-300 flex items-center justify-center text-xs">
                <i class="fas fa-sparkles"></i>
              </span>
              <div>
                <h4 class="text-xs font-black uppercase tracking-wider">AI Catalog & Photography Studio</h4>
                <p class="text-[10px] text-blue-200">Autonomous Merchant Intelligence</p>
              </div>
            </div>
            <span class="px-2.5 py-0.5 rounded-full bg-amber-400 text-gray-950 text-[9px] font-black uppercase tracking-wider">
              Coming Soon
            </span>
          </div>

          <p class="text-xs text-blue-100/90 leading-relaxed">
            Our multimodal AI copywriter and multi-angle product photography engine (FLUX.1 + Gemini) are undergoing final calibration for full Nigerian commercial release.
          </p>

          <div class="space-y-2 text-[11px] text-blue-200">
            <div class="flex items-center gap-2">
              <i class="fas fa-check text-amber-300 text-[10px]"></i>
              <span>Automated GS1 Titles & Highlight Bullets</span>
            </div>
            <div class="flex items-center gap-2">
              <i class="fas fa-check text-amber-300 text-[10px]"></i>
              <span>Pure White Studio & Lifestyle Photography</span>
            </div>
            <div class="flex items-center gap-2">
              <i class="fas fa-check text-amber-300 text-[10px]"></i>
              <span>Naira Price Optimization & Market Radar</span>
            </div>
          </div>

          <div class="pt-2">
            <button type="button" disabled
                    class="w-full py-2.5 bg-white/10 text-white/50 border border-white/10 font-bold text-xs rounded-xl cursor-not-allowed flex items-center justify-center gap-2">
              <i class="fas fa-lock"></i>
              <span>AI Studio In Private Beta (Coming Soon)</span>
            </button>
          </div>
        </div>

          <!-- Simulated Storefront Card -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="aspect-square rounded-xl bg-white border border-gray-200 flex items-center justify-center p-3 relative overflow-hidden">
              <img id="previewImg" src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800" class="w-full h-full object-contain">
              <span id="previewDiscountBadge" class="hidden absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-black bg-[#E4306D] text-white">
                -25%
              </span>
            </div>

            <div>
              <div class="flex items-center justify-between text-[10px] text-gray-400 uppercase font-bold mb-1">
                <span id="previewCategory">Category</span>
                <span class="text-emerald-600 flex items-center gap-1"><i class="fas fa-check-circle"></i> Verified</span>
              </div>
              <h5 id="previewTitle" class="text-xs font-black text-gray-900 line-clamp-2 leading-snug">
                Product Title will appear here in real-time...
              </h5>
              <div class="flex items-baseline gap-2 mt-2">
                <span id="previewPrice" class="text-sm font-black text-[#1B2299]">₦0.00</span>
                <span id="previewOldPrice" class="text-xs text-gray-400 line-through hidden">₦0.00</span>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD D: PUBLISHING SUMMARY & ACTIONS -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-7 shadow-sm space-y-3">
          <button type="submit" 
                  class="w-full py-4 bg-[#1B2299] hover:bg-[#0B132B] text-white font-black text-sm rounded-2xl shadow-xl hover:shadow-2xl transition-all flex items-center justify-center gap-2">
            <i class="fas fa-check-double"></i>
            <span>Publish Product to Storefront</span>
          </button>
          <a href="<?php echo zavora_asset('seller/index.php'); ?>" 
             class="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5">
            <i class="fas fa-xmark"></i>
            <span>Cancel & Return to Dashboard</span>
          </a>
        </div>

      </div>

    </form>
  </div>
</div>

<script>
let currentPhotoStudioMode = 'hero';
let currentPhotoMatches = [];
let quillEditor = null;

// Initialize Quill WYSIWYG Editor on Load
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('quill-editor')) {
    quillEditor = new Quill('#quill-editor', {
      theme: 'snow',
      placeholder: 'Compose a professional, compelling product description with styled headings, bullet points, and key details...',
      modules: {
        toolbar: [
          [{ 'header': [2, 3, 4, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          ['blockquote', 'link'],
          ['clean']
        ]
      }
    });
  }
});

// Key Features Dynamic Rows
function addKeyFeatureRow(val = '') {
  const container = document.getElementById('keyFeaturesContainer');
  if (!container) return;
  const div = document.createElement('div');
  div.className = 'flex items-center gap-2 feature-row';
  div.innerHTML = `
    <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">✓</span>
    <input type="text" name="key_features[]" value="${escapeHtml(val)}" placeholder="Highlight feature bullet (e.g. Battery, Display, Camera)..."
           class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
    <button type="button" onclick="removeKeyFeatureRow(this)" class="text-gray-300 hover:text-rose-500 transition px-1"><i class="fas fa-trash-can text-xs"></i></button>
  `;
  container.appendChild(div);
}

function removeKeyFeatureRow(btn) {
  const row = btn.closest('.feature-row');
  if (row) row.remove();
}

// 1. GS1 Title Length Validator
function onTitleChanged(val) {
  const len = (val || '').length;
  const counter = document.getElementById('gs1TitleCounter');
  const pill = document.getElementById('gs1CompliancePill');
  const preview = document.getElementById('previewTitle');

  counter.innerText = len + ' / 90 chars';
  if (preview) preview.innerText = val || 'Product Title will appear here in real-time...';

  if (len >= 60 && len <= 90) {
    counter.className = 'text-[11px] font-mono font-bold text-emerald-600';
    pill.innerText = '✓ GS1 Catalog Compliant';
    pill.className = 'text-[10px] font-bold text-emerald-600';
  } else if (len > 90) {
    counter.className = 'text-[11px] font-mono font-bold text-amber-600';
    pill.innerText = '⚠ Exceeds 90 chars optimal length';
    pill.className = 'text-[10px] font-bold text-amber-600';
  } else {
    counter.className = 'text-[11px] font-mono font-bold text-gray-400';
    pill.innerText = 'Needs 60-90 chars';
    pill.className = 'text-[10px] font-bold text-gray-400';
  }
}

// 2. Real-Time Price & Discount Calculator
function calculateDiscount() {
  const price = parseFloat(document.getElementById('full_price').value) || 0;
  const prevPrice = parseFloat(document.getElementById('full_prev_price').value) || 0;

  const previewPrice = document.getElementById('previewPrice');
  const previewOldPrice = document.getElementById('previewOldPrice');
  const previewDiscount = document.getElementById('previewDiscountBadge');
  const discountBadge = document.getElementById('discountBadge');

  previewPrice.innerText = '₦' + price.toLocaleString();

  if (prevPrice > price && price > 0) {
    const percent = Math.round(((prevPrice - price) / prevPrice) * 100);
    previewOldPrice.innerText = '₦' + prevPrice.toLocaleString();
    previewOldPrice.classList.remove('hidden');
    previewDiscount.innerText = '-' + percent + '%';
    previewDiscount.classList.remove('hidden');

    discountBadge.innerText = percent + '% OFF';
    discountBadge.classList.remove('hidden');
  } else {
    previewOldPrice.classList.add('hidden');
    previewDiscount.classList.add('hidden');
    discountBadge.classList.add('hidden');
  }
}

// 3. Category & Type Sync
document.getElementById('full_category').addEventListener('change', function() {
  const text = this.options[this.selectedIndex].text;
  document.getElementById('previewCategory').innerText = text;
});

function onThumbnailUrlChanged(url) {
  const img = document.getElementById('previewImg');
  if (img && url && url.length > 5) {
    img.src = url;
  }
}

// Device Image File Upload Handler
function handleImageFileSelect(input) {
  if (input.files && input.files[0]) {
    const file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('Selected image exceeds 5MB size limit. Please choose a smaller image.');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      const dataUrl = e.target.result;
      const previewImg = document.getElementById('previewImg');
      if (previewImg) previewImg.src = dataUrl;

      const uploadImgThumb = document.getElementById('uploadImgThumb');
      if (uploadImgThumb) uploadImgThumb.src = dataUrl;

      const uploadSelectedPreview = document.getElementById('uploadSelectedPreview');
      const uploadPrompt = document.getElementById('uploadPrompt');
      const uploadFileName = document.getElementById('uploadFileName');

      if (uploadSelectedPreview) uploadSelectedPreview.classList.remove('hidden');
      if (uploadPrompt) uploadPrompt.classList.add('hidden');
      if (uploadFileName) uploadFileName.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
    };
    reader.readAsDataURL(file);
  }
}

function clearSelectedImage(e) {
  if (e) e.stopPropagation();
  const input = document.getElementById('productImageInput');
  if (input) input.value = '';

  const uploadSelectedPreview = document.getElementById('uploadSelectedPreview');
  const uploadPrompt = document.getElementById('uploadPrompt');
  if (uploadSelectedPreview) uploadSelectedPreview.classList.add('hidden');
  if (uploadPrompt) uploadPrompt.classList.remove('hidden');

  // Reset preview image to fallback URL
  const activeUrl = document.getElementById('activeThumbnailInput') ? document.getElementById('activeThumbnailInput').value : '';
  const previewImg = document.getElementById('previewImg');
  if (previewImg) {
    previewImg.src = activeUrl || 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800';
  }
}

// 4. Variety & Preorder Toggles
function toggleVarietySection(checked) {
  document.getElementById('varietyContainer').classList.toggle('hidden', !checked);
  renderVariantMatrix();
}

function togglePreorderSection(checked) {
  document.getElementById('preorderContainer').classList.toggle('hidden', !checked);
}

function renderVariantMatrix() {
  const colors = (document.getElementById('var_colors').value || '').split(',').map(s => s.trim()).filter(Boolean);
  const sizes = (document.getElementById('var_sizes').value || '').split(',').map(s => s.trim()).filter(Boolean);
  const specs = (document.getElementById('var_specs').value || '').split(',').map(s => s.trim()).filter(Boolean);

  const wrap = document.getElementById('variantMatrixWrap');
  const list = document.getElementById('variantMatrixList');

  const combos = [];
  if (colors.length > 0 && specs.length > 0) {
    colors.forEach(c => specs.forEach(sp => combos.push(c + ' · ' + sp)));
  } else if (colors.length > 0 && sizes.length > 0) {
    colors.forEach(c => sizes.forEach(sz => combos.push(c + ' · Size ' + sz)));
  } else if (colors.length > 0) {
    colors.forEach(c => combos.push(c));
  } else if (specs.length > 0) {
    specs.forEach(sp => combos.push(sp));
  }

  if (combos.length > 0) {
    list.innerHTML = combos.map(cb => `<span class="px-2 py-1 rounded-lg bg-white border border-gray-200 text-gray-800 font-bold text-[11px] shadow-2xs">${escapeHtml(cb)}</span>`).join('');
    wrap.classList.remove('hidden');
  } else {
    wrap.classList.add('hidden');
  }
}

// 5. Specification Rows
function addSpecRow(key = '', val = '') {
  const container = document.getElementById('specRowsContainer');
  const div = document.createElement('div');
  div.className = 'grid grid-cols-12 gap-2 spec-row';
  div.innerHTML = `
    <input type="text" name="spec_keys[]" value="${escapeHtml(key)}" placeholder="Metric" class="col-span-5 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 focus:bg-white focus:outline-none">
    <input type="text" name="spec_vals[]" value="${escapeHtml(val)}" placeholder="Value" class="col-span-6 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 font-semibold focus:bg-white focus:outline-none">
    <button type="button" onclick="removeSpecRow(this)" class="col-span-1 text-gray-300 hover:text-rose-500 transition"><i class="fas fa-trash-can text-xs"></i></button>
  `;
  container.appendChild(div);
}

function removeSpecRow(btn) {
  const row = btn.closest('.spec-row');
  if (row) row.remove();
}

// 6. Photo Studio Mode Selector
function setPhotoStudioMode(mode) {
  currentPhotoStudioMode = mode;
  document.getElementById('tabModeHero').className = (mode === 'hero') ? 'flex-1 py-1.5 rounded-lg bg-white text-[#1B2299] shadow-sm transition' : 'flex-1 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition';
  document.getElementById('tabModeLife').className = (mode === 'lifestyle') ? 'flex-1 py-1.5 rounded-lg bg-white text-purple-700 shadow-sm transition' : 'flex-1 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition';
  document.getElementById('tabModeOnline').className = (mode === 'online') ? 'flex-1 py-1.5 rounded-lg bg-white text-emerald-700 shadow-sm transition' : 'flex-1 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition';
}

// 7. Run AI Copy Generation
function runAiCopyGeneration() {
  const keywords = document.getElementById('aiKeywords').value.trim() || document.getElementById('full_title').value.trim();
  const brand = document.getElementById('aiBrand').value.trim();
  const tone = document.getElementById('aiTone').value;
  const catSelect = document.getElementById('full_category');
  const categoryName = catSelect.options[catSelect.selectedIndex].text;
  const statusEl = document.getElementById('aiCopyStatus');
  const btn = document.getElementById('btnRunAiCopy');

  if (!keywords) {
    alert('Please enter a product title or keywords first.');
    document.getElementById('aiKeywords').focus();
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating GS1 Listing...';
  statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-white/10 text-blue-200 block';
  statusEl.innerText = 'Consulting AI Merchant Studio (1 Credit)...';

  const fd = new FormData();
  fd.append('keywords', keywords);
  fd.append('brand_name', brand);
  fd.append('tone', tone);
  fd.append('category_name', categoryName);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=generate_copy"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success && data.data) {
      const d = data.data;

      // Populate Title
      document.getElementById('full_title').value = d.title || '';
      onTitleChanged(d.title || '');

      // Populate Key Features & Highlights
      if (Array.isArray(d.bullet_points) && d.bullet_points.length > 0) {
        const kfContainer = document.getElementById('keyFeaturesContainer');
        kfContainer.innerHTML = '';
        d.bullet_points.forEach(bp => addKeyFeatureRow(bp));
      }

      // Populate Rich Text Narrative into Quill Editor
      if (quillEditor && d.description) {
        quillEditor.root.innerHTML = d.description;
      }
      document.getElementById('full_narrative').value = d.description || '';

      // Populate What's in the Box
      if (d.in_the_box && document.getElementById('box_contents')) {
        document.getElementById('box_contents').value = d.in_the_box;
      }

      // Populate Specifications Matrix
      if (d.specifications_map && typeof d.specifications_map === 'object') {
        const container = document.getElementById('specRowsContainer');
        container.innerHTML = '';
        Object.entries(d.specifications_map).forEach(([k, v]) => {
          addSpecRow(k, v);
        });
      }

      // Update Credits Display
      if (data.remaining_credits !== undefined) {
        updateAllCreditCounters(data.remaining_credits);
      }

      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-emerald-500/20 text-emerald-200 block';
      statusEl.innerHTML = '<i class="fas fa-check-circle text-emerald-400"></i> International GS1 listing drafted! Review the populated fields.';
    } else if (data.error === 'insufficient_credits') {
      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-amber-500/20 text-amber-200 block';
      statusEl.innerText = data.message || '0 AI Credits remaining.';
    } else {
      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-rose-500/20 text-rose-200 block';
      statusEl.innerText = data.message || 'Copy generation failed.';
    }
  })
  .catch(err => {
    console.error(err);
    statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-rose-500/20 text-rose-200 block';
    statusEl.innerText = 'Network connection error.';
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> <span>Auto-Draft Complete Listing (1 Credit)</span>';
  });
}

// 8. Run AI Photography & Online Sourcing (4 Matches)
function runAiPhotoGeneration() {
  const title = document.getElementById('full_title').value.trim() || document.getElementById('aiKeywords').value.trim();
  const brand = document.getElementById('aiBrand').value.trim();
  const catSelect = document.getElementById('full_category');
  const category = catSelect.options[catSelect.selectedIndex].text;
  const colors = document.getElementById('var_colors') ? document.getElementById('var_colors').value.trim() : '';
  const specs = document.getElementById('var_specs') ? document.getElementById('var_specs').value.trim() : '';
  const statusEl = document.getElementById('aiPhotoStatus');
  const btn = document.getElementById('btnRunAiPhoto');

  if (!title) {
    alert('Please enter a product title first so the studio can generate matching photography.');
    document.getElementById('full_title').focus();
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rendering & Sourcing Photography...';
  statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-purple-50 text-purple-700 block';
  statusEl.innerText = 'Generating FLUX.1 studio photos & sourcing real product photography...';

  const fd = new FormData();
  fd.append('title', title);
  fd.append('brand', brand);
  fd.append('category', category);
  fd.append('color', colors);
  fd.append('specs', specs);
  fd.append('mode', currentPhotoStudioMode);

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=generate_image"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success && data.matches) {
      currentPhotoMatches = data.matches;
      renderPhotoMatchesGrid(data.matches);

      // Select primary
      if (data.image_url) {
        document.getElementById('activeThumbnailInput').value = data.image_url;
        onThumbnailUrlChanged(data.image_url);
      }

      if (data.remaining_credits !== undefined) {
        updateAllCreditCounters(data.remaining_credits);
      }

      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-emerald-50 text-emerald-800 block';
      statusEl.innerHTML = '<i class="fas fa-check-circle text-emerald-600"></i> 4 Realistic product photos generated and sourced! Click any photo to apply.';
    } else if (data.error === 'insufficient_credits') {
      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-amber-50 text-amber-800 block';
      statusEl.innerText = data.message || '0 AI Credits remaining.';
    } else {
      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-rose-50 text-rose-800 block';
      statusEl.innerText = data.message || 'Image generation failed.';
    }
  })
  .catch(err => {
    console.error(err);
    statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-rose-50 text-rose-800 block';
    statusEl.innerText = 'Network error during image generation.';
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-camera"></i> <span>Generate & Source 4 Realistic Matches (1 Credit)</span>';
  });
}

function renderPhotoMatchesGrid(matches) {
  const grid = document.getElementById('photoMatchesGrid');
  if (!grid || !Array.isArray(matches)) return;

  grid.innerHTML = matches.map((m, idx) => `
    <div onclick="selectImageMatch('${escapeHtml(m.url)}', this, '${escapeHtml(m.title)}')"
         class="match-card cursor-pointer p-2 rounded-2xl border-2 ${idx === 0 ? 'border-[#1B2299] bg-white' : 'border-gray-200 bg-white'} transition hover:shadow-md relative">
      <div class="aspect-square rounded-xl ${m.type === 'ai_hero' ? 'bg-white' : 'bg-gray-100'} border border-gray-100 flex items-center justify-center p-2 mb-1.5 overflow-hidden">
        <img src="${escapeHtml(m.url)}" class="w-full h-full ${m.type === 'ai_hero' ? 'object-contain' : 'object-cover'}">
      </div>
      <div class="flex items-center justify-between text-[10px]">
        <span class="font-black text-gray-800 truncate">${escapeHtml(m.badge || m.title)}</span>
        <i class="fas ${idx === 0 ? 'fa-check-circle text-[#1B2299]' : 'fa-circle text-gray-300'} match-check"></i>
      </div>
    </div>
  `).join('');
}

function selectImageMatch(url, cardEl, label) {
  document.getElementById('activeThumbnailInput').value = url;
  onThumbnailUrlChanged(url);

  document.querySelectorAll('.match-card').forEach(c => {
    c.classList.remove('border-[#1B2299]');
    c.classList.add('border-gray-200');
    const ic = c.querySelector('.match-check');
    if (ic) { ic.className = 'fas fa-circle text-gray-300 match-check'; }
  });

  if (cardEl) {
    cardEl.classList.add('border-[#1B2299]');
    cardEl.classList.remove('border-gray-200');
    const ic = cardEl.querySelector('.match-check');
    if (ic) { ic.className = 'fas fa-check-circle text-[#1B2299] match-check'; }
  }

  const labelEl = document.getElementById('selectedMatchLabel');
  if (labelEl && label) {
    labelEl.innerText = 'Applied: ' + label;
  }
}

function updateAllCreditCounters(credits) {
  const c = parseInt(credits, 10);
  const top = document.getElementById('topAiCredits');
  if (top) top.innerText = c + ' Credits';
  const box = document.getElementById('boxAiCredits');
  if (box) box.innerText = c + ' Credits';
}

function submitProductForm() {
  const title = document.getElementById('full_title').value.trim();
  const price = parseFloat(document.getElementById('full_price').value);

  if (!title) {
    alert('Please provide a product title.');
    document.getElementById('full_title').focus();
    return;
  }
  if (!price || price <= 0) {
    alert('Please enter a valid selling price.');
    document.getElementById('full_price').focus();
    return;
  }

  // Sync Quill Rich Text HTML into hidden form input before submission
  if (quillEditor) {
    document.getElementById('full_narrative').value = quillEditor.root.innerHTML;
  }

  document.getElementById('mainProductForm').submit();
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
  });
}

// Prefill from Vendor AI Sandbox if navigated from there
document.addEventListener('DOMContentLoaded', function() {
  const draftRaw = sessionStorage.getItem('zavora_prefill_draft');
  if (draftRaw) {
    try {
      const d = JSON.parse(draftRaw);
      if (d.title) {
        document.getElementById('full_title').value = d.title;
        onTitleChanged(d.title);
      }
      if (Array.isArray(d.bullet_points) && d.bullet_points.length > 0) {
        const kfContainer = document.getElementById('keyFeaturesContainer');
        kfContainer.innerHTML = '';
        d.bullet_points.forEach(bp => addKeyFeatureRow(bp));
      }
      if (d.description) {
        document.getElementById('full_narrative').value = d.description;
        // Wait a tick for Quill to initialize if needed
        setTimeout(() => {
          if (quillEditor) quillEditor.root.innerHTML = d.description;
        }, 50);
      }
      if (d.in_the_box && document.getElementById('box_contents')) {
        document.getElementById('box_contents').value = d.in_the_box;
      }
      if (d.specifications_map && typeof d.specifications_map === 'object') {
        const container = document.getElementById('specRowsContainer');
        container.innerHTML = '';
        Object.entries(d.specifications_map).forEach(([k, v]) => {
          addSpecRow(k, v);
        });
      }
      sessionStorage.removeItem('zavora_prefill_draft');
    } catch(e) {
      console.error(e);
    }
  }

  const imgRaw = sessionStorage.getItem('zavora_prefill_image');
  if (imgRaw) {
    document.getElementById('activeThumbnailInput').value = imgRaw;
    onThumbnailUrlChanged(imgRaw);
    sessionStorage.removeItem('zavora_prefill_image');
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
