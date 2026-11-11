<?php
/**
 * ZAVORA Vendor Central — Product Management & Editing Suite
 *
 * Provides full control for sellers to update their product catalog:
 * - GS1 Formula E-Commerce Title Editing
 * - Key Features & Highlights Dynamic Editor
 * - Quill Rich Text WYSIWYG Product Description Editor
 * - Technical Specifications Matrix Builder
 * - What's In The Box Package Contents Editor
 * - Variety / Attribute Matrix Settings
 * - Pre-Order Allocation Controls
 * - AI Copywriter & Studio Photography Enhancer
 * - Real-Time Storefront Live Preview Card
 */

require_once __DIR__ . '/../config/config.php';
$pdo = zavora_get_db();

// 1. Strict Vendor Session Authentication
$vendor = zavora_vendor_require_auth();
$vendorId = (int)$vendor['id'];

// 2. Fetch Product & Verify Ownership
$productId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if ($productId <= 0) {
    header('Location: ' . zavora_asset('seller/index.php?tab=products'));
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
$stmt->execute([$productId, $vendorId]);
$product = $stmt->fetch();

if (!$product) {
    // If not found as vendor owner, check if logged in as admin
    if (!empty($_SESSION['admin_logged_in'])) {
        $admStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $admStmt->execute([$productId]);
        $product = $admStmt->fetch();
    }
}

if (!$product) {
    header('Location: ' . zavora_asset('seller/index.php?tab=products&error=not_found'));
    exit;
}

// 3. Fetch Categories
$catsStmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
$categories = $catsStmt->fetchAll();

$flashMessage = '';
$flashType = '';

// 4. Handle Product Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {
    $title = trim($_POST['title'] ?? '');
    $categoryId = intval($_POST['category_id'] ?? 1);
    $type = in_array($_POST['type'] ?? '', ['physical', 'digital', 'course']) ? $_POST['type'] : 'physical';
    $price = floatval($_POST['price'] ?? 0);
    $prevPrice = !empty($_POST['previous_price']) ? floatval($_POST['previous_price']) : null;
    $stock = intval($_POST['stock_quantity'] ?? 0);
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $narrativeDesc = trim($_POST['narrative_description'] ?? $_POST['description'] ?? '');

    // Process Multi-Image Gallery & Featured Selection
    $allImages = [];
    $featuredSelection = trim($_POST['featured_image'] ?? '');

    // 1. Collect Existing Images retained by the vendor
    if (!empty($_POST['existing_images']) && is_array($_POST['existing_images'])) {
        foreach ($_POST['existing_images'] as $img) {
            $img = trim($img);
            if (!empty($img)) {
                $allImages[] = $img;
            }
        }
    }

    // 2. Process Multiple New Uploaded Files
    if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $totalFiles = count($_FILES['product_images']['name']);

        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['product_images']['error'][$i] === UPLOAD_ERR_OK) {
                $origName = $_FILES['product_images']['name'][$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $size = $_FILES['product_images']['size'][$i];
                if (in_array($ext, $allowedExts) && $size <= 10 * 1024 * 1024) {
                    $newFileName = 'prod_' . $vendorId . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . "_{$i}." . $ext;
                    if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $uploadDir . $newFileName)) {
                        $relPath = 'uploads/products/' . $newFileName;
                        $allImages[] = $relPath;
                        if ($featuredSelection === "upload_{$i}" || $featuredSelection === $origName) {
                            $thumbnail = $relPath;
                        }
                    }
                }
            }
        }
    }

    // 3. Process Legacy Single Upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['product_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowedExts) && $file['size'] <= 10 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../uploads/products/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newFileName = 'prod_' . $vendorId . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
                $relPath = 'uploads/products/' . $newFileName;
                $allImages[] = $relPath;
                if (empty($thumbnail)) $thumbnail = $relPath;
            }
        }
    }

    // 4. Process External Gallery URLs
    if (!empty($_POST['gallery_urls']) && is_array($_POST['gallery_urls'])) {
        foreach ($_POST['gallery_urls'] as $gUrl) {
            $gUrl = trim($gUrl);
            if (!empty($gUrl) && filter_var($gUrl, FILTER_VALIDATE_URL)) {
                $allImages[] = $gUrl;
            }
        }
    }
    $singleUrl = trim($_POST['thumbnail_url'] ?? $_POST['thumbnail'] ?? '');
    if (!empty($singleUrl) && !in_array($singleUrl, $allImages, true)) {
        $allImages[] = $singleUrl;
    }

    $allImages = array_values(array_unique(array_filter($allImages)));

    // 5. Determine Featured / Cover Image
    if (!empty($featuredSelection) && in_array($featuredSelection, $allImages, true)) {
        $thumbnail = $featuredSelection;
    } elseif (empty($thumbnail) && !empty($allImages)) {
        $thumbnail = $allImages[0];
    } elseif (empty($thumbnail)) {
        $thumbnail = $product['thumbnail'] ?: 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800';
    }

    // Ensure featured thumbnail is guaranteed at index 0 in the gallery
    if (!empty($thumbnail)) {
        $allImages = array_values(array_unique(array_merge([$thumbnail], $allImages)));
    }

    // Collect Key Features
    $rawFeatures = $_POST['key_features'] ?? [];
    if (!is_array($rawFeatures)) $rawFeatures = [];
    $features = array_values(array_filter(array_map('trim', $rawFeatures)));
    if (!empty($features)) {
        $formattedBullets = "KEY HIGHLIGHTS:\n• " . implode("\n• ", $features);
        $fullDescription = $formattedBullets . "\n\nPRODUCT OVERVIEW:\n" . $narrativeDesc;
    } else {
        $fullDescription = $narrativeDesc;
    }

    // Collect Structured Specifications Matrix
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

    // Variety / Variants schema
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
        if (empty($thumbnail)) {
            $thumbnail = $product['thumbnail'] ?: 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800';
        }

        $upStmt = $pdo->prepare("
            UPDATE products SET 
                title = ?, category_id = ?, type = ?, price = ?, previous_price = ?,
                stock_quantity = ?, thumbnail = ?, images = ?, description = ?, specifications = ?,
                is_preorder = ?, preorder_release_date = ?, preorder_deposit = ?,
                has_variants = ?, variant_attributes = ?
            WHERE id = ?
        ");
        $upStmt->execute([
            $title, $categoryId, $type, $price, $prevPrice,
            $stock, $thumbnail, !empty($allImages) ? json_encode($allImages) : null, $fullDescription, $specsString,
            $isPreorder, $preorderRelease, $preorderDeposit,
            $hasVariants, !empty($attrSchema) ? json_encode($attrSchema) : null,
            $productId
        ]);

        // Sync product variants if requested
        if ($hasVariants && !empty($attrSchema)) {
            $checkVar = $pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = ?");
            $checkVar->execute([$productId]);
            $existingVarCount = $checkVar->fetchColumn();

            if ($existingVarCount == 0) {
                $sku = $product['sku'] ?: 'VND-' . $vendorId . '-' . $productId;
                $varStmt = $pdo->prepare("INSERT INTO product_variants (product_id, sku, title, attributes_json, price, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $varIndex = 1;

                if (!empty($variantColors) && !empty($variantSpecs)) {
                    foreach ($variantColors as $c) {
                        foreach ($variantSpecs as $sp) {
                            $vSku = $sku . '-' . strtoupper(substr($c, 0, 3)) . '-' . $varIndex++;
                            $vTitle = "{$c} · {$sp}";
                            $varStmt->execute([$productId, $vSku, $vTitle, json_encode(['Color' => $c, 'RAM / Storage' => $sp]), $price, max(5, round($stock / (count($variantColors) * count($variantSpecs))))]);
                        }
                    }
                } elseif (!empty($variantColors) && !empty($variantSizes)) {
                    foreach ($variantColors as $c) {
                        foreach ($variantSizes as $sz) {
                            $vSku = $sku . '-' . strtoupper(substr($c, 0, 3)) . '-' . $sz;
                            $vTitle = "{$c} · Size {$sz}";
                            $varStmt->execute([$productId, $vSku, $vTitle, json_encode(['Color' => $c, 'Size' => $sz]), $price, max(5, round($stock / (count($variantColors) * count($variantSizes))))]);
                        }
                    }
                } elseif (!empty($variantColors)) {
                    foreach ($variantColors as $c) {
                        $vSku = $sku . '-' . strtoupper(substr($c, 0, 3));
                        $vTitle = $c;
                        $varStmt->execute([$productId, $vSku, $vTitle, json_encode(['Color' => $c]), $price, max(5, round($stock / count($variantColors)))]);
                    }
                }
            }
        }

        $flashMessage = 'Product details updated successfully!';
        $flashType = 'success';

        // Refresh product data
        $stmt->execute([$productId, $vendorId]);
        $product = $stmt->fetch();
    }
}

// 5. Parse Existing Data for Form Fields
$rawDesc = $product['description'] ?? '';
$parsedHighlights = [];
$narrativeHtml = $rawDesc;

if (preg_match('/KEY HIGHLIGHTS:\s*(.*?)(?=(?:PRODUCT OVERVIEW:|$))/si', $rawDesc, $mH)) {
    $lines = explode("\n", $mH[1]);
    foreach ($lines as $line) {
        $c = trim(ltrim(trim($line), "•-* \t"));
        if (!empty($c)) $parsedHighlights[] = $c;
    }
    if (preg_match('/PRODUCT OVERVIEW:\s*(.*)/si', $rawDesc, $mO)) {
        $narrativeHtml = trim($mO[1]);
    } else {
        $narrativeHtml = trim(str_replace($mH[0], '', $rawDesc));
    }
}

// Parse Specifications & Box Contents
$parsedSpecs = [];
$boxContents = '';
$rawSpecs = trim($product['specifications'] ?? '');
if (!empty($rawSpecs)) {
    $parts = (strpos($rawSpecs, '|') !== false) ? explode('|', $rawSpecs) : explode("\n", $rawSpecs);
    foreach ($parts as $p) {
        $p = trim($p);
        if (empty($p)) continue;
        if (strpos($p, ':') !== false) {
            [$k, $v] = explode(':', $p, 2);
            $k = trim($k);
            $v = trim($v);
            if (stripos($k, "what's in the box") !== false || stripos($k, "in the box") !== false || stripos($k, "package contents") !== false) {
                $boxContents = $v;
            } else {
                $parsedSpecs[$k] = $v;
            }
        }
    }
}

// Parse Variant Attributes
$existingAttrs = !empty($product['variant_attributes']) ? json_decode($product['variant_attributes'], true) : [];
$varColorsStr = !empty($existingAttrs['Color']) ? implode(', ', $existingAttrs['Color']) : '';
$varSizesStr  = !empty($existingAttrs['Size'])  ? implode(', ', $existingAttrs['Size']) : '';
$varSpecsStr  = !empty($existingAttrs['RAM / Storage']) ? implode(', ', $existingAttrs['RAM / Storage']) : '';

// Parse Existing Multi-Image Gallery
$existingImages = [];
if (!empty($product['images'])) {
    $decodedImgs = json_decode($product['images'], true);
    if (is_array($decodedImgs)) {
        $existingImages = array_values(array_filter(array_map('trim', $decodedImgs)));
    }
}
if (empty($existingImages) && !empty($product['thumbnail'])) {
    $existingImages = [$product['thumbnail']];
}

$title = 'Edit Product: ' . htmlspecialchars($product['title']) . ' — ZAVORA Vendor Central';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Quill Rich Text WYSIWYG Editor Styles & Scripts -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<style>
  .ql-toolbar.ql-snow {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
  }
  .ql-container.ql-snow {
    border-bottom-left-radius: 1rem;
    border-bottom-right-radius: 1rem;
  }
</style>

<div class="min-h-screen bg-slate-50/60 pb-20 overflow-x-hidden">

  <!-- Sticky Enterprise Top Bar -->
  <div class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-gray-200 px-3 sm:px-4 lg:px-8 py-3 sm:py-3.5">
    <div class="container mx-auto flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <a href="<?php echo zavora_asset('seller/index.php?tab=products'); ?>" 
           class="w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-700 transition flex-shrink-0">
          <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">
              Product Editor
            </span>
            <span class="text-[11px] text-gray-400 font-mono truncate">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
          </div>
          <h1 class="text-sm sm:text-lg font-black text-gray-900 leading-tight truncate max-w-xs sm:max-w-md">
            Edit: <?php echo htmlspecialchars($product['title']); ?>
          </h1>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto justify-end">
        <a href="<?php echo zavora_asset('product.php?id=' . $product['id']); ?>" target="_blank"
           class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-gray-800 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
          <i class="fas fa-external-link-alt text-xs text-gray-500"></i>
          <span>View Storefront</span>
        </a>
        <button type="button" onclick="submitProductForm()"
                class="px-4 sm:px-5 py-2 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-black rounded-xl shadow-md transition flex items-center gap-1.5">
          <i class="fas fa-floppy-disk"></i>
          <span>Save Changes</span>
        </button>
      </div>
    </div>
  </div>

  <div class="container mx-auto px-4 lg:px-8 py-6">

    <!-- Flash Notification Banner -->
    <?php if (!empty($flashMessage)): ?>
      <div class="mb-6 p-4 rounded-2xl border <?php echo $flashType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'; ?> flex items-center justify-between">
        <div class="flex items-center gap-3">
          <i class="fas <?php echo $flashType === 'success' ? 'fa-check-circle text-emerald-600' : 'fa-triangle-exclamation text-rose-600'; ?> text-lg"></i>
          <p class="text-xs font-bold"><?php echo htmlspecialchars($flashMessage); ?></p>
        </div>
        <?php if ($flashType === 'success'): ?>
          <a href="<?php echo zavora_asset('product.php?id=' . $product['id']); ?>" target="_blank" class="text-xs font-extrabold text-emerald-700 underline hover:no-underline">
            View Live Listing &rarr;
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="mainProductForm" enctype="multipart/form-data" class="grid lg:grid-cols-12 gap-8 items-start">
      <input type="hidden" name="action" value="update_product">
      <input type="hidden" name="id" value="<?php echo $product['id']; ?>">

      <!-- ════════════════════════════════════════════════════════════════════════
           LEFT COLUMN: CORE DETAILS, KEY FEATURES, DESCRIPTION & SPECS (7 Cols)
           ════════════════════════════════════════════════════════════════════════ -->
      <div class="lg:col-span-7 space-y-6">

        <!-- REFORMATTED TEMPLATE IMPORTER SUITE: WORD (.DOCX) & CSV -->
        <div class="bg-gradient-to-br from-[#0B132B] via-[#1B2299] to-slate-900 text-white rounded-3xl p-6 sm:p-7 shadow-lg border border-indigo-700/50 space-y-5">
          
          <!-- Top Row: Icon, Headline & Status Badges -->
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-white/10">
            <div class="flex items-start sm:items-center gap-3.5">
              <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center flex-shrink-0 shadow-inner">
                <div class="flex items-center -space-x-1.5 text-lg">
                  <i class="fas fa-file-word text-blue-300 drop-shadow"></i>
                  <i class="fas fa-file-csv text-emerald-300 drop-shadow"></i>
                </div>
              </div>
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-sm font-black tracking-wide text-white font-display">
                    Catalog Template Importer &amp; Auto-Fill
                  </h3>
                  <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[10px] font-bold uppercase tracking-wider">
                    Word &amp; Excel Ready
                  </span>
                </div>
                <p class="text-xs text-blue-200/90 mt-1 leading-relaxed">
                  Download our pre-structured template, update your product details, and upload here to auto-populate Title, Highlights, Quill WYSIWYG Description, and Technical Specs in 1 click.
                </p>
              </div>
            </div>
          </div>

          <!-- Bottom Row: Download Templates & Upload Actions -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pt-1">
            
            <!-- Download Options (Clear & Distinct for Word and CSV) -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
              <span class="text-[11px] font-black uppercase tracking-wider text-blue-300/80 flex items-center gap-1.5">
                <i class="fas fa-download text-amber-300 text-xs"></i> Download Template:
              </span>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <!-- Word (.docx) Download Button -->
                <a href="<?php echo zavora_asset('seller/download-template.php?format=docx'); ?>" 
                   title="Download formatted Microsoft Word template with headings and bullet lists"
                   class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 hover:border-white/40 text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-2xs group">
                  <i class="fas fa-file-word text-blue-400 text-sm group-hover:scale-110 transition-transform"></i>
                  <span>Word (.docx) Template</span>
                </a>

                <!-- CSV / Excel Download Button -->
                <a href="<?php echo zavora_asset('seller/download-template.php?format=csv'); ?>" 
                   title="Download standard CSV spreadsheet template"
                   class="px-3.5 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/20 hover:border-white/40 text-xs font-bold rounded-xl transition flex items-center justify-center gap-2 shadow-2xs group">
                  <i class="fas fa-file-csv text-emerald-400 text-sm group-hover:scale-110 transition-transform"></i>
                  <span>CSV (.csv) Template</span>
                </a>
              </div>
            </div>

            <!-- Upload / Auto-fill Button -->
            <div class="w-full sm:w-auto">
              <button type="button" onclick="document.getElementById('catalogTemplateFileInput').click()" 
                      class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-amber-400 via-amber-500 to-amber-500 hover:from-amber-500 hover:to-amber-600 text-gray-950 text-xs font-black rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <i class="fas fa-cloud-arrow-up text-sm"></i>
                <span>Upload Word / CSV to Auto-Fill</span>
              </button>
              <input type="file" id="catalogTemplateFileInput" accept=".docx,.csv,.txt" class="hidden" onchange="handleTemplateUpload(this)">
            </div>

          </div>
        </div>

        <div id="templateImportNotice" class="hidden p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fas fa-check-circle text-emerald-600"></i>
            <span id="templateImportNoticeText"></span>
          </div>
          <button type="button" onclick="document.getElementById('templateImportNotice').classList.add('hidden')" class="text-emerald-700 hover:text-emerald-950 font-black"><i class="fas fa-xmark"></i></button>
        </div>

        <!-- CARD 1: CORE PRODUCT IDENTIFICATION -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <i class="fas fa-tag text-[#1B2299]"></i> 1. Core Identification
            </h3>
            <span class="text-[10px] font-bold text-gray-400">Step 1 of 5</span>
          </div>

          <!-- Product Title with GS1 Counter -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-gray-800">
                Product Title <span class="text-rose-500">*</span>
              </label>
              <div class="flex items-center gap-2">
                <span id="gs1CompliancePill" class="text-[10px] font-bold text-emerald-600">✓ GS1 Catalog Compliant</span>
                <span id="gs1TitleCounter" class="text-[11px] font-mono font-bold text-gray-400">0 / 90 chars</span>
              </div>
            </div>
            <input type="text" name="title" id="full_title" required
                   value="<?php echo htmlspecialchars($product['title']); ?>"
                   oninput="onTitleChanged(this.value)"
                   placeholder="e.g. Sony WH-1000XM5 Wireless Noise-Canceling Headphones, Black"
                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-2xl text-xs sm:text-sm font-semibold focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]/20">
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Category -->
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1.5">Category <span class="text-rose-500">*</span></label>
              <select name="category_id" id="full_category" required
                      class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:bg-white focus:outline-none">
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>" <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Product Type -->
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1.5">Product Type <span class="text-rose-500">*</span></label>
              <select name="type" id="full_type" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:bg-white focus:outline-none">
                <option value="physical" <?php echo ($product['type'] === 'physical') ? 'selected' : ''; ?>>Physical Deliverable (Across Nigeria)</option>
                <option value="digital" <?php echo ($product['type'] === 'digital') ? 'selected' : ''; ?>>Instant Digital Download (Files / Assets)</option>
                <option value="course" <?php echo ($product['type'] === 'course') ? 'selected' : ''; ?>>Learnerium LMS Online Course</option>
              </select>
            </div>
          </div>
        </div>

        <!-- CARD 2: PRICING & INVENTORY -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <i class="fas fa-coins text-[#1B2299]"></i> 2. Pricing & Inventory Controls
            </h3>
            <span id="discountBadge" class="hidden text-[10px] font-black text-white bg-[#E4306D] px-2 py-0.5 rounded-full"></span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Selling Price -->
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1.5">Selling Price (₦) <span class="text-rose-500">*</span></label>
              <input type="number" step="0.01" name="price" id="full_price" required
                     value="<?php echo htmlspecialchars($product['price']); ?>"
                     oninput="calculateDiscount()" placeholder="e.g. 85000"
                     class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-black text-[#1B2299] focus:bg-white focus:outline-none">
            </div>

            <!-- Previous Price (For Strikethrough Discount) -->
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1.5">Previous Price (₦, Optional)</label>
              <input type="number" step="0.01" name="previous_price" id="full_prev_price"
                     value="<?php echo htmlspecialchars($product['previous_price'] ?? ''); ?>"
                     oninput="calculateDiscount()" placeholder="e.g. 100000"
                     class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-500 focus:bg-white focus:outline-none">
            </div>

            <!-- Stock Quantity -->
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1.5">Available Stock Units</label>
              <input type="number" name="stock_quantity" id="full_stock"
                     value="<?php echo intval($product['stock_quantity']); ?>"
                     class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-900 focus:bg-white focus:outline-none">
            </div>
          </div>
        </div>

        <!-- CARD 3: PRODUCT VARIETY / ATTRIBUTES -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-sliders text-[#1B2299]"></i> 3. Variety & Options
              </h3>
              <p class="text-[11px] text-gray-400">Offer color options, clothing sizes, or storage configurations.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="has_variants" id="toggleVariety" value="1" 
                     <?php echo !empty($product['has_variants']) ? 'checked' : ''; ?>
                     onchange="toggleVarietySection(this.checked)" class="sr-only peer">
              <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#1B2299]"></div>
            </label>
          </div>

          <div id="varietyContainer" class="<?php echo empty($product['has_variants']) ? 'hidden' : ''; ?> space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Color Options (Comma separated)</label>
                <input type="text" name="variant_colors" id="var_colors" value="<?php echo htmlspecialchars($varColorsStr); ?>" placeholder="Black, Silver, Blue" oninput="renderVariantMatrix()"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              </div>
              <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">Sizes (If applicable)</label>
                <input type="text" name="variant_sizes" id="var_sizes" value="<?php echo htmlspecialchars($varSizesStr); ?>" placeholder="S, M, L, XL" oninput="renderVariantMatrix()"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              </div>
              <div>
                <label class="block text-[11px] font-bold text-gray-700 mb-1">RAM / Storage Specs</label>
                <input type="text" name="variant_specs" id="var_specs" value="<?php echo htmlspecialchars($varSpecsStr); ?>" placeholder="128GB, 256GB, 512GB" oninput="renderVariantMatrix()"
                       class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
              </div>
            </div>

            <div id="variantMatrixWrap" class="p-4 rounded-2xl bg-indigo-50/50 border border-indigo-100 space-y-2">
              <span class="text-[10px] font-black uppercase tracking-wider text-indigo-900 block">Configured Varieties:</span>
              <div id="variantMatrixList" class="flex flex-wrap gap-1.5"></div>
            </div>
          </div>
        </div>

        <!-- CARD 4: PRE-ORDER ALLOCATION -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-calendar-check text-[#1B2299]"></i> 4. Pre-Order Allocation
              </h3>
              <p class="text-[11px] text-gray-400">Collect reservations for upcoming stock or product releases.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" name="is_preorder" id="togglePreorder" value="1"
                     <?php echo !empty($product['is_preorder']) ? 'checked' : ''; ?>
                     onchange="togglePreorderSection(this.checked)" class="sr-only peer">
              <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
          </div>

          <div id="preorderContainer" class="<?php echo empty($product['is_preorder']) ? 'hidden' : ''; ?> grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Expected Release / Dispatch Date</label>
              <input type="date" name="preorder_release_date" value="<?php echo htmlspecialchars($product['preorder_release_date'] ?? ''); ?>"
                     class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none">
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Minimum Booking Deposit (₦, Optional)</label>
              <input type="number" step="0.01" name="preorder_deposit" value="<?php echo htmlspecialchars($product['preorder_deposit'] ?? ''); ?>"
                     placeholder="0 for full payment"
                     class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold focus:bg-white focus:outline-none">
            </div>
          </div>
        </div>

        <!-- CARD 5: KEY FEATURES & HIGHLIGHTS -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-list-check text-[#1B2299]"></i> 5. Key Features & Highlights
              </h3>
              <p class="text-[11px] text-gray-400">Essential customer benefit drivers shown prominently beside the product gallery.</p>
            </div>
            <button type="button" onclick="addKeyFeatureRow()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-gray-800 text-[11px] font-bold rounded-lg transition flex items-center gap-1">
              <i class="fas fa-plus"></i> Add Feature
            </button>
          </div>

          <div id="keyFeaturesContainer" class="space-y-2.5">
            <?php if (!empty($parsedHighlights)): ?>
              <?php foreach ($parsedHighlights as $h): ?>
                <div class="flex items-center gap-2 feature-row">
                  <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">✓</span>
                  <input type="text" name="key_features[]" value="<?php echo htmlspecialchars($h); ?>" placeholder="Highlight feature bullet..."
                         class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                  <button type="button" onclick="removeKeyFeatureRow(this)" class="text-gray-300 hover:text-rose-500 transition px-1"><i class="fas fa-trash-can text-xs"></i></button>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="flex items-center gap-2 feature-row">
                <span class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold">✓</span>
                <input type="text" name="key_features[]" placeholder="e.g. 7,500mAh Massive Battery: Up to 3 days continuous usage on a single charge."
                       class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
                <button type="button" onclick="removeKeyFeatureRow(this)" class="text-gray-300 hover:text-rose-500 transition px-1"><i class="fas fa-trash-can text-xs"></i></button>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- CARD 6: RICH TEXT PRODUCT DESCRIPTION (QUILL WYSIWYG) -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-pen-nib text-[#1B2299]"></i> 6. Product Details & Description
              </h3>
              <p class="text-[11px] text-gray-400">Professional rich text editor with formatting, headings, and bullet points.</p>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" onclick="document.getElementById('catalogTemplateFileInput').click()" 
                      class="text-[11px] font-bold text-[#1B2299] hover:underline flex items-center gap-1.5 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg border border-indigo-100 transition">
                <i class="fas fa-file-word text-blue-600"></i> Import Word (.docx)
              </button>
              <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full">Quill WYSIWYG</span>
            </div>
          </div>

          <!-- Quill Container -->
          <div class="border border-gray-200 rounded-2xl overflow-hidden bg-white shadow-2xs">
            <div id="quill-editor" class="min-h-[240px] text-sm text-gray-800 leading-relaxed p-4"><?php echo $narrativeHtml; ?></div>
          </div>
          <input type="hidden" name="narrative_description" id="full_narrative" value="<?php echo htmlspecialchars($narrativeHtml); ?>">
          <p class="text-[11px] text-gray-400 flex items-center gap-1.5">
            <i class="fas fa-info-circle text-[#1B2299]"></i> Formatted text, bold points, subheadings, and lists will render cleanly on your storefront product page.
          </p>
        </div>

        <!-- CARD 7: STRUCTURED TECHNICAL SPECIFICATIONS MATRIX -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-microchip text-[#1B2299]"></i> 7. Technical Specifications Matrix
              </h3>
              <p class="text-[11px] text-gray-400">Renders as a clean 2-column alternating table on your storefront.</p>
            </div>
            <button type="button" onclick="addSpecRow()" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-gray-800 text-[11px] font-bold rounded-lg transition flex items-center gap-1">
              <i class="fas fa-plus"></i> Add Row
            </button>
          </div>

          <div id="specRowsContainer" class="space-y-2">
            <?php if (!empty($parsedSpecs)): ?>
              <?php foreach ($parsedSpecs as $sKey => $sVal): ?>
                <div class="grid grid-cols-12 gap-2 spec-row">
                  <input type="text" name="spec_keys[]" value="<?php echo htmlspecialchars($sKey); ?>" placeholder="Metric" class="col-span-5 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 focus:bg-white focus:outline-none">
                  <input type="text" name="spec_vals[]" value="<?php echo htmlspecialchars($sVal); ?>" placeholder="Value" class="col-span-6 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 font-semibold focus:bg-white focus:outline-none">
                  <button type="button" onclick="removeSpecRow(this)" class="col-span-1 text-gray-300 hover:text-rose-500 transition"><i class="fas fa-trash-can text-xs"></i></button>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="grid grid-cols-12 gap-2 spec-row">
                <input type="text" name="spec_keys[]" value="Brand" placeholder="Metric" class="col-span-5 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 focus:bg-white focus:outline-none">
                <input type="text" name="spec_vals[]" value="<?php echo htmlspecialchars($vendor['store_name']); ?>" placeholder="Value" class="col-span-6 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 font-semibold focus:bg-white focus:outline-none">
                <button type="button" onclick="removeSpecRow(this)" class="col-span-1 text-gray-300 hover:text-rose-500 transition"><i class="fas fa-trash-can text-xs"></i></button>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- CARD 8: WHAT'S IN THE BOX -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h3 class="text-sm font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-box-open text-[#1B2299]"></i> 8. What's In The Box
              </h3>
              <p class="text-[11px] text-gray-400">List all included accessories, manuals, and contents in the package.</p>
            </div>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">Package Contents</span>
          </div>
          <div>
            <input type="text" name="box_contents" id="box_contents" value="<?php echo htmlspecialchars($boxContents); ?>"
                   placeholder="e.g. 1x Smartphone, 1x Power Adapter, 1x USB-C Cable, 1x Protective Case, 1x Warranty Card"
                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-900 focus:bg-white focus:outline-none">
          </div>
        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════════════════════
           RIGHT COLUMN: AI STUDIO & LIVE MEDIA SUITE (5 Cols)
           ════════════════════════════════════════════════════════════════════════ -->
      <div class="lg:col-span-5 space-y-6">

        <!-- CARD A: MULTI-IMAGE GALLERY & FEATURED PHOTO SELECTOR -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-7 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <div>
              <h4 class="text-xs font-black text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <i class="fas fa-images text-[#1B2299]"></i> Product Photography & Gallery
              </h4>
              <p class="text-[10px] text-gray-400">Upload multiple photos and click any image to designate as Featured.</p>
            </div>
            <span id="galleryCountBadge" class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-0.5 rounded-full"><?php echo count($existingImages); ?> Photos</span>
          </div>

          <!-- Hidden Featured & Thumbnail Controls -->
          <input type="hidden" name="featured_image" id="featuredImageInput" value="<?php echo htmlspecialchars($product['thumbnail']); ?>">
          <input type="hidden" name="thumbnail" id="activeThumbnailInput" value="<?php echo htmlspecialchars($product['thumbnail']); ?>">

          <!-- Multi-File Dropzone -->
          <div>
            <div id="dropzoneWrap" onclick="document.getElementById('productImagesInput').click()" 
                 class="cursor-pointer border-2 border-dashed border-gray-300 hover:border-[#1B2299] bg-slate-50/80 hover:bg-indigo-50/20 rounded-2xl p-6 text-center transition-all group">
              <input type="file" name="product_images[]" id="productImagesInput" multiple accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" onchange="handleMultipleImagesSelect(this)">
              
              <div id="uploadPrompt" class="space-y-2.5">
                <div class="w-12 h-12 rounded-2xl bg-white border border-gray-200 shadow-2xs text-[#1B2299] group-hover:scale-110 flex items-center justify-center mx-auto transition-transform">
                  <i class="fas fa-cloud-arrow-up text-lg"></i>
                </div>
                <div>
                  <p class="text-xs font-black text-gray-900">Upload new images or drag & drop</p>
                  <p class="text-[10px] text-gray-400 mt-0.5">PNG, JPG, WEBP or GIF (Select multiple, up to 10MB each)</p>
                </div>
                <span class="inline-block px-3.5 py-1.5 bg-white border border-gray-200 text-[#1B2299] text-xs font-bold rounded-xl shadow-2xs group-hover:border-[#1B2299] transition">
                  <i class="fas fa-folder-open mr-1"></i> Choose Photos from Device
                </span>
              </div>
            </div>
          </div>

          <!-- External URL Adder Option -->
          <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-[11px] font-bold text-gray-600 uppercase">Or Add Image via Web URL</label>
              <span class="text-[10px] text-gray-400">CDN / Unsplash / Cloudinary</span>
            </div>
            <div class="flex items-center gap-2">
              <input type="url" id="externalUrlInput" placeholder="https://images.unsplash.com/photo-..." 
                     class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-xl text-xs font-mono text-gray-800 focus:outline-none">
              <button type="button" onclick="addExternalImageUrl()" 
                      class="px-3 py-2 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl transition flex-shrink-0">
                <i class="fas fa-plus mr-1"></i> Add URL
              </button>
            </div>
          </div>

          <!-- Dynamic Multi-Image Gallery Grid -->
          <div id="galleryContainer" class="space-y-2">
            <div class="flex items-center justify-between text-[11px] text-gray-500 font-bold">
              <span>Selected Product Photos</span>
              <span class="text-amber-600 flex items-center gap-1"><i class="fas fa-star text-[10px]"></i> Gold border = Featured Cover</span>
            </div>

            <div id="galleryGrid" class="grid grid-cols-3 gap-2.5 min-h-[90px] p-2.5 rounded-2xl bg-gray-50 border border-dashed border-gray-200">
              <div id="emptyGalleryPrompt" class="col-span-3 text-center py-6 text-gray-400 text-xs italic">
                No images added yet. Upload files from device or enter an image URL above.
              </div>
            </div>
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

          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="aspect-square rounded-xl bg-white border border-gray-200 flex items-center justify-center p-3 relative overflow-hidden">
              <img id="previewImg" src="<?php echo htmlspecialchars(zavora_image_url($product['thumbnail'])); ?>" class="w-full h-full object-contain">
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
                <?php echo htmlspecialchars($product['title']); ?>
              </h5>
              <div class="flex items-baseline gap-2 mt-2">
                <span id="previewPrice" class="text-sm font-black text-[#1B2299]">
                  ₦<?php echo number_format($product['price'], 2); ?>
                </span>
                <span id="previewOldPrice" class="text-xs text-gray-400 line-through <?php echo empty($product['previous_price']) ? 'hidden' : ''; ?>">
                  ₦<?php echo number_format((float)($product['previous_price'] ?? 0), 2); ?>
                </span>
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
            Automated copy upgrades, multi-angle product photo rendering, and competitor price tracking are being calibrated for enterprise launch.
          </p>

          <div class="pt-2">
            <button type="button" disabled
                    class="w-full py-2.5 bg-white/10 text-white/50 border border-white/10 font-bold text-xs rounded-xl cursor-not-allowed flex items-center justify-center gap-2">
              <i class="fas fa-lock"></i>
              <span>AI Studio In Private Beta (Coming Soon)</span>
            </button>
          </div>
        </div>

        <!-- CARD D: PUBLISHING ACTIONS -->
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-7 shadow-sm space-y-3">
          <button type="submit" 
                  class="w-full py-4 bg-[#1B2299] hover:bg-[#0B132B] text-white font-black text-sm rounded-2xl shadow-xl hover:shadow-2xl transition-all flex items-center justify-center gap-2">
            <i class="fas fa-floppy-disk"></i>
            <span>Save & Apply Updates</span>
          </button>
          <a href="<?php echo zavora_asset('seller/index.php?tab=products'); ?>" 
             class="w-full py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-xl transition flex items-center justify-center gap-1.5">
            <i class="fas fa-xmark"></i>
            <span>Return to Products List</span>
          </a>
        </div>

      </div>

    </form>
  </div>
</div>

<script>
let currentPhotoStudioMode = 'hero';
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

  // Calculate discount & preview on initial load
  calculateDiscount();
  onTitleChanged(document.getElementById('full_title').value);
  renderVariantMatrix();
  renderGalleryGrid();

  // Sync Quill rich text before form submission
  const editForm = document.querySelector('form[action*="product-edit"]');
  if (editForm) {
    editForm.addEventListener('submit', function() {
      if (quillEditor) {
        document.getElementById('full_narrative').value = quillEditor.root.innerHTML;
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

  if (counter) counter.innerText = len + ' / 90 chars';
  if (preview) preview.innerText = val || 'Product Title will appear here in real-time...';

  if (!pill) return;
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

  if (previewPrice) previewPrice.innerText = '₦' + price.toLocaleString();

  if (prevPrice > price && price > 0) {
    const percent = Math.round(((prevPrice - price) / prevPrice) * 100);
    if (previewOldPrice) {
      previewOldPrice.innerText = '₦' + prevPrice.toLocaleString();
      previewOldPrice.classList.remove('hidden');
    }
    if (previewDiscount) {
      previewDiscount.innerText = '-' + percent + '%';
      previewDiscount.classList.remove('hidden');
    }
    if (discountBadge) {
      discountBadge.innerText = percent + '% OFF';
      discountBadge.classList.remove('hidden');
    }
  } else {
    if (previewOldPrice) previewOldPrice.classList.add('hidden');
    if (previewDiscount) previewDiscount.classList.add('hidden');
    if (discountBadge) discountBadge.classList.add('hidden');
  }
}

// 3. Category & Thumbnail Sync
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

// Pre-populate existing gallery images from PHP
const initialImages = <?php echo json_encode(array_map(function($img) use ($product) {
    $isFeat = ($img === $product['thumbnail'] || zavora_image_url($img) === zavora_image_url($product['thumbnail']));
    return [
        'id' => 'existing_' . md5($img),
        'type' => 'existing',
        'src' => zavora_image_url($img),
        'raw_src' => $img,
        'filename' => basename($img),
        'isFeatured' => $isFeat
    ];
}, $existingImages)); ?>;

// Multi-Image Gallery State & Handlers
let galleryItems = (initialImages && initialImages.length > 0) ? initialImages : [];

// Ensure at least one image is marked featured
if (galleryItems.length > 0 && !galleryItems.some(i => i.isFeatured)) {
  galleryItems[0].isFeatured = true;
}

function handleMultipleImagesSelect(input) {
  if (!input.files || input.files.length === 0) return;
  addFilesToGallery(input.files);
  input.value = ''; // Reset so the same file or new files can be selected again cleanly
}

function addFilesToGallery(fileList) {
  if (!fileList || fileList.length === 0) return;
  const files = Array.from(fileList);

  files.forEach((file, index) => {
    if (file.size > 10 * 1024 * 1024) {
      alert('File "' + file.name + '" exceeds the 10MB limit.');
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
      const isFirst = (galleryItems.length === 0);
      galleryItems.push({
        id: 'file_' + Date.now() + '_' + index + '_' + Math.random().toString(36).substring(2, 6),
        type: 'file',
        src: e.target.result,
        file: file,
        filename: file.name,
        isFeatured: isFirst
      });
      syncFileInputFiles();
      renderGalleryGrid();
    };
    reader.readAsDataURL(file);
  });
}

function syncFileInputFiles() {
  try {
    const dt = new DataTransfer();
    galleryItems.forEach(item => {
      if (item.type === 'file' && item.file instanceof File) {
        dt.items.add(item.file);
      }
    });
    const fileInput = document.getElementById('productImagesInput');
    if (fileInput) {
      fileInput.files = dt.files;
    }
  } catch (err) {
    console.warn('DataTransfer sync note:', err);
  }
}

function addExternalImageUrl() {
  const input = document.getElementById('externalUrlInput');
  const url = (input.value || '').trim();
  if (!url) return;
  if (!url.startsWith('http://') && !url.startsWith('https://')) {
    alert('Please enter a valid HTTP or HTTPS image URL.');
    return;
  }
  const isFirst = (galleryItems.length === 0);
  const name = url.split('/').pop().split('?')[0] || 'Web Image';
  galleryItems.push({
    id: 'url_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
    type: 'url',
    src: url,
    raw_src: url,
    filename: name,
    isFeatured: isFirst
  });
  input.value = '';
  renderGalleryGrid();
}

function setFeaturedImage(itemId) {
  galleryItems.forEach(item => {
    item.isFeatured = (item.id === itemId);
  });
  renderGalleryGrid();
}

function removeGalleryItem(itemId, event) {
  if (event) event.stopPropagation();
  const wasFeatured = galleryItems.find(i => i.id === itemId)?.isFeatured;
  galleryItems = galleryItems.filter(i => i.id !== itemId);
  if (wasFeatured && galleryItems.length > 0) {
    galleryItems[0].isFeatured = true;
  }
  syncFileInputFiles();
  renderGalleryGrid();
}

// Drag & drop attachment for dropzone
document.addEventListener('DOMContentLoaded', () => {
  const dropzone = document.getElementById('dropzoneWrap');
  if (dropzone) {
    ['dragenter', 'dragover'].forEach(name => {
      dropzone.addEventListener(name, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('border-[#1B2299]', 'bg-indigo-50/40');
      });
    });
    ['dragleave', 'drop'].forEach(name => {
      dropzone.addEventListener(name, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('border-[#1B2299]', 'bg-indigo-50/40');
      });
    });
    dropzone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        addFilesToGallery(dt.files);
      }
    });
  }
});

function renderGalleryGrid() {
  const grid = document.getElementById('galleryGrid');
  const countBadge = document.getElementById('galleryCountBadge');
  const featuredInput = document.getElementById('featuredImageInput');
  const activeThumbInput = document.getElementById('activeThumbnailInput');
  const previewImg = document.getElementById('previewImg');

  if (countBadge) {
    countBadge.textContent = galleryItems.length + ' Photo' + (galleryItems.length === 1 ? '' : 's');
  }

  if (galleryItems.length === 0) {
    grid.innerHTML = '<div id="emptyGalleryPrompt" class="col-span-3 text-center py-6 text-gray-400 text-xs italic">No images added yet. Upload files from device or enter an image URL above.</div>';
    if (featuredInput) featuredInput.value = '';
    if (activeThumbInput) activeThumbInput.value = '';
    if (previewImg) previewImg.src = 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800';
    return;
  }

  // Ensure one item is featured
  let featuredItem = galleryItems.find(i => i.isFeatured);
  if (!featuredItem && galleryItems.length > 0) {
    galleryItems[0].isFeatured = true;
    featuredItem = galleryItems[0];
  }

  if (featuredItem) {
    const val = (featuredItem.raw_src || featuredItem.filename || featuredItem.src);
    if (featuredInput) featuredInput.value = val;
    if (activeThumbInput) activeThumbInput.value = featuredItem.src;
    if (previewImg) previewImg.src = featuredItem.src;
  }

  grid.innerHTML = '';
  galleryItems.forEach(item => {
    const card = document.createElement('div');
    card.className = `relative group rounded-xl overflow-hidden border-2 bg-white aspect-square shadow-2xs p-1 transition-all ${
      item.isFeatured ? 'border-amber-400 ring-2 ring-amber-200' : 'border-gray-200 hover:border-gray-400'
    }`;

    let hiddenFields = '';
    if (item.type === 'existing') {
      hiddenFields = `<input type="hidden" name="existing_images[]" value="${escapeHtml(item.raw_src || item.src)}">`;
    } else if (item.type === 'url') {
      hiddenFields = `<input type="hidden" name="gallery_urls[]" value="${escapeHtml(item.raw_src || item.src)}">`;
    }

    card.innerHTML = `
      ${hiddenFields}
      <img src="${escapeHtml(item.src)}" class="w-full h-full object-contain rounded-lg">
      
      <!-- Featured Badge / Button -->
      ${item.isFeatured ? `
        <span class="absolute top-1.5 left-1.5 bg-amber-400 text-gray-950 text-[9px] font-black px-1.5 py-0.5 rounded shadow flex items-center gap-0.5 z-10">
          <i class="fas fa-star text-[8px]"></i> Featured
        </span>
      ` : `
        <button type="button" onclick="setFeaturedImage('${item.id}')" 
                title="Set as Featured Cover Photo"
                class="absolute top-1.5 left-1.5 bg-black/60 hover:bg-amber-400 text-white hover:text-gray-950 text-[9px] font-bold px-1.5 py-0.5 rounded transition opacity-0 group-hover:opacity-100 flex items-center gap-0.5 z-10">
          <i class="far fa-star text-[8px]"></i> Set Featured
        </button>
      `}

      <!-- Remove Button -->
      <button type="button" onclick="removeGalleryItem('${item.id}', event)" 
              title="Remove Photo"
              class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-white/90 hover:bg-rose-500 text-gray-600 hover:text-white shadow flex items-center justify-center text-xs transition z-10">
        <i class="fas fa-trash-can text-[10px]"></i>
      </button>

      <!-- Bottom label -->
      <span class="absolute bottom-1 left-1 right-1 bg-black/60 text-white text-[8px] font-mono px-1 py-0.5 rounded truncate text-center backdrop-blur-2xs">
        ${escapeHtml(item.filename || 'Photo')}
      </span>
    `;

    grid.appendChild(card);
  });
}

// Template Document Importer Handler (Word .docx, CSV, TXT)
function handleTemplateUpload(input) {
  if (!input.files || input.files.length === 0) return;
  const file = input.files[0];
  const formData = new FormData();
  formData.append('template_file', file);

  const notice = document.getElementById('templateImportNotice');
  const noticeText = document.getElementById('templateImportNoticeText');
  if (notice && noticeText) {
    noticeText.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Reading and parsing template document (' + file.name + ')...';
    notice.classList.remove('hidden', 'bg-rose-50', 'border-rose-200', 'text-rose-800');
    notice.classList.add('bg-blue-50', 'border-blue-200', 'text-blue-800');
  }

  fetch('<?php echo zavora_asset("seller/import-template.php"); ?>', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data && data.success && data.data) {
      const d = data.data;

      // 1. Title
      if (d.title) {
        const titleInput = document.getElementById('full_title');
        if (titleInput) {
          titleInput.value = d.title;
          if (typeof onTitleChanged === 'function') onTitleChanged(d.title);
        }
      }

      // 2. Price & Previous Price
      if (d.price && d.price > 0) {
        const priceInput = document.getElementById('full_price');
        if (priceInput) {
          priceInput.value = d.price;
          if (typeof calculateDiscount === 'function') calculateDiscount();
        }
      }
      if (d.previous_price && d.previous_price > 0) {
        const prevPriceInput = document.getElementById('full_prev_price');
        if (prevPriceInput) {
          prevPriceInput.value = d.previous_price;
          if (typeof calculateDiscount === 'function') calculateDiscount();
        }
      }

      // 3. Category Match
      if (d.category) {
        const catSelect = document.getElementById('full_category');
        if (catSelect) {
          const catNorm = d.category.toLowerCase().trim();
          for (let opt of catSelect.options) {
            if (opt.text.toLowerCase().includes(catNorm) || catNorm.includes(opt.text.toLowerCase())) {
              catSelect.value = opt.value;
              catSelect.dispatchEvent(new Event('change'));
              break;
            }
          }
        }
      }

      // 4. Key Highlights
      if (d.highlights && Array.isArray(d.highlights) && d.highlights.length > 0) {
        const container = document.getElementById('keyFeaturesContainer');
        if (container) {
          container.innerHTML = '';
          d.highlights.forEach(h => addKeyFeatureRow(h));
        }
      }

      // 5. Quill Rich Text Description
      if (d.html_description && quillEditor) {
        quillEditor.root.innerHTML = d.html_description;
        const hiddenNarrative = document.getElementById('full_narrative');
        if (hiddenNarrative) hiddenNarrative.value = d.html_description;
      }

      // 6. Specifications Matrix
      if (d.specifications && Array.isArray(d.specifications) && d.specifications.length > 0) {
        const specsContainer = document.getElementById('specRowsContainer');
        if (specsContainer) {
          specsContainer.innerHTML = '';
          d.specifications.forEach(sp => addSpecRow(sp.key, sp.val));
        }
      }

      // 7. Box Contents
      if (d.box_contents) {
        const boxInput = document.getElementById('box_contents');
        if (boxInput) boxInput.value = d.box_contents;
      }

      if (notice && noticeText) {
        noticeText.innerHTML = `<strong>Success!</strong> Imported ${data.filename} (${data.format.toUpperCase()}). Title, highlights, Quill description, and specs auto-filled.`;
        notice.classList.remove('hidden', 'bg-blue-50', 'border-blue-200', 'text-blue-800');
        notice.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800');
      }
    } else {
      if (notice && noticeText) {
        noticeText.innerHTML = `<strong>Import Warning:</strong> ` + (data.message || 'Could not parse template file.');
        notice.classList.remove('hidden', 'bg-blue-50', 'border-blue-200', 'text-blue-800');
        notice.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-800');
      }
    }
  })
  .catch(err => {
    if (notice && noticeText) {
      noticeText.innerHTML = `<strong>Import Error:</strong> Failed to connect to server: ` + err.message;
      notice.classList.remove('hidden', 'bg-blue-50', 'border-blue-200', 'text-blue-800');
      notice.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-800');
    }
  });
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

      // Populate What's In The Box
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
        const box = document.getElementById('boxAiCredits');
        if (box) box.innerText = parseInt(data.remaining_credits, 10) + ' Credits';
      }

      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-emerald-500/20 text-emerald-200 block';
      statusEl.innerHTML = '<i class="fas fa-check-circle text-emerald-400"></i> Upgraded copy drafted! Review fields before saving.';
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
    btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> <span>Auto-Draft Listing Upgrade (1 Credit)</span>';
  });
}

// 8. Run AI Photography & Online Sourcing
function runAiPhotoGeneration() {
  const title = document.getElementById('full_title').value.trim() || document.getElementById('aiKeywords').value.trim();
  const brand = document.getElementById('aiBrand').value.trim();
  const catSelect = document.getElementById('full_category');
  const category = catSelect.options[catSelect.selectedIndex].text;
  const colors = document.getElementById('var_colors') ? document.getElementById('var_colors').value.trim() : '';
  const specs = document.getElementById('var_specs') ? document.getElementById('var_specs').value.trim() : '';
  const statusEl = document.getElementById('aiPhotoStatus');
  const btn = document.getElementById('btnRunAiPhoto');

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sourcing Photography...';
  statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-purple-50 text-purple-700 block';
  statusEl.innerText = 'Rendering studio photos & sourcing real matches...';

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
      renderPhotoMatchesGrid(data.matches);
      if (data.image_url) {
        document.getElementById('activeThumbnailInput').value = data.image_url;
        onThumbnailUrlChanged(data.image_url);
      }
      statusEl.className = 'text-[11px] font-bold p-2.5 rounded-xl bg-emerald-50 text-emerald-800 block';
      statusEl.innerHTML = '<i class="fas fa-check-circle text-emerald-600"></i> New matches sourced! Click any photo to apply.';
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
    btn.innerHTML = '<i class="fas fa-camera"></i> <span>Source 4 New Matches (1 Credit)</span>';
  });
}

function renderPhotoMatchesGrid(matches) {
  const wrap = document.getElementById('photoMatchesWrap');
  const grid = document.getElementById('photoMatchesGrid');
  if (!grid || !Array.isArray(matches)) return;

  wrap.classList.remove('hidden');
  grid.innerHTML = matches.map((m, idx) => `
    <div onclick="selectImageMatch('${escapeHtml(m.url)}', this)"
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

function selectImageMatch(url, cardEl) {
  document.getElementById('activeThumbnailInput').value = url;
  onThumbnailUrlChanged(url);

  document.querySelectorAll('.match-card').forEach(c => {
    c.classList.remove('border-[#1B2299]');
    c.classList.add('border-gray-200');
    const ic = c.querySelector('.match-check');
    if (ic) ic.className = 'fas fa-circle text-gray-300 match-check';
  });

  if (cardEl) {
    cardEl.classList.add('border-[#1B2299]');
    cardEl.classList.remove('border-gray-200');
    const ic = cardEl.querySelector('.match-check');
    if (ic) ic.className = 'fas fa-check-circle text-[#1B2299] match-check';
  }
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

  // Synchronize Quill HTML into hidden form input
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
