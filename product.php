<?php
/**
 * ZAVORA Storefront — Professional Product Details & Commerce Suite
 *
 * Designed to international e-commerce standards (Jumia & Konga Gold Standard):
 * - Top Fold: High-Resolution Product Showcase, Dynamic Variants, Live Stock & Pricing
 * - Main Column: Key Features & Highlights, Rich-Text WYSIWYG Narrative, Technical Specs Matrix, What's In The Box
 * - Sticky Sidebar: Nationwide Door Delivery & Returns, 100% Genuine Guarantee, Verified Merchant Profile
 * - Merchant In-Place Management: Quick "Edit Product" shortcut for authorized vendors & admins
 */

require_once __DIR__ . '/config/config.php';

$pdo = zavora_get_db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug, 
           v.store_name as vendor_store_name, v.slug as vendor_slug, v.owner_name as vendor_owner_name, v.status as vendor_status
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: ' . zavora_asset('shop.php'));
    exit;
}

// Check Vendor / Admin Authentication for In-Place Listing Management
$currentVendor = zavora_vendor_auth();
$canEditProduct = false;
if (!empty($_SESSION['admin_logged_in'])) {
    $canEditProduct = true;
} elseif ($currentVendor && (int)$currentVendor['id'] === (int)$product['vendor_id']) {
    $canEditProduct = true;
}

// Fetch Product Variants if applicable
$varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY price ASC");
$varStmt->execute([$id]);
$variants = $varStmt->fetchAll();

// Handle Customer Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $custName = trim($_POST['customer_name'] ?? '');
    $custRating = max(1, min(5, intval($_POST['rating'] ?? 5)));
    $custTitle = trim($_POST['review_title'] ?? '');
    $custComment = trim($_POST['comment'] ?? '');

    if (!empty($custName) && !empty($custComment)) {
        $ins = $pdo->prepare("INSERT INTO product_reviews (product_id, customer_name, rating, title, comment, is_verified_buyer) VALUES (?, ?, ?, ?, ?, 1)");
        $ins->execute([$id, $custName, $custRating, $custTitle, $custComment]);

        // Dynamically recalculate average rating and count
        $stat = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM product_reviews WHERE product_id = ?");
        $stat->execute([$id]);
        $row = $stat->fetch();
        $pdo->prepare("UPDATE products SET rating = ?, reviews_count = ? WHERE id = ?")->execute([
            round($row['avg_r'] ?: $custRating, 1),
            intval($row['cnt']),
            $id
        ]);
        header('Location: ' . zavora_asset('product.php?id=' . $id . '&reviewed=1#reviews'));
        exit;
    }
}

// Fetch Product Reviews (100% Database-Driven)
$revStmt = $pdo->prepare("SELECT * FROM product_reviews WHERE product_id = ? ORDER BY id DESC");
$revStmt->execute([$id]);
$reviews = $revStmt->fetchAll();

$avgRating = !empty($reviews) ? round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1) : (float)($product['rating'] ?? 5.0);
$reviewCount = count($reviews) ?: (int)($product['reviews_count'] ?? 0);

// Parse variant attributes schema
$attributeGroups = !empty($product['variant_attributes']) ? json_decode($product['variant_attributes'], true) : [];

// Parse Description for Key Highlights and Rich Narrative
$rawDesc = $product['description'] ?? '';
$highlights = [];
$narrativeDesc = $rawDesc;

if (preg_match('/KEY HIGHLIGHTS:\s*(.*?)(?=(?:PRODUCT OVERVIEW:|$))/si', $rawDesc, $matchHighlights)) {
    $bulletBlock = $matchHighlights[1];
    $lines = explode("\n", $bulletBlock);
    foreach ($lines as $line) {
        $cleanLine = trim(ltrim(trim($line), "•-* \t"));
        if (!empty($cleanLine)) {
            $highlights[] = $cleanLine;
        }
    }
    if (preg_match('/PRODUCT OVERVIEW:\s*(.*)/si', $rawDesc, $matchOverview)) {
        $narrativeDesc = trim($matchOverview[1]);
    } else {
        $narrativeDesc = trim(str_replace($matchHighlights[0], '', $rawDesc));
    }
}

// Prepare Safe Rich Text HTML for Narrative Description (Preserving Quill WYSIWYG tags)
$isRichHtml = (strip_tags($narrativeDesc) !== $narrativeDesc);
if ($isRichHtml) {
    $sanitizedNarrative = strip_tags($narrativeDesc, '<p><br><strong><b><em><i><u><h2><h3><h4><h5><h6><ul><ol><li><span><div><table><thead><tbody><tr><th><td><a><img><blockquote>');
} else {
    $sanitizedNarrative = nl2br(htmlspecialchars($narrativeDesc));
}

// Parse Specifications into Structured Key-Value Dictionary & Extract Box Contents
$parsedSpecs = [];
$boxContents = '';
$rawSpecs = trim($product['specifications'] ?? '');
if (!empty($rawSpecs)) {
    $jsonDecoded = json_decode($rawSpecs, true);
    if (is_array($jsonDecoded)) {
        $parsedSpecs = $jsonDecoded;
    } else {
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
            } elseif (strpos($p, '—') !== false) {
                [$k, $v] = explode('—', $p, 2);
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
}

// Parse Multi-Image Gallery
$galleryImages = [];
if (!empty($product['images'])) {
    $decoded = json_decode($product['images'], true);
    if (is_array($decoded)) {
        $galleryImages = array_values(array_filter(array_map('trim', $decoded)));
    }
}
if (empty($galleryImages)) {
    if (!empty($product['thumbnail'])) {
        $galleryImages = [$product['thumbnail']];
    }
} else {
    if (!empty($product['thumbnail']) && !in_array($product['thumbnail'], $galleryImages, true)) {
        array_unshift($galleryImages, $product['thumbnail']);
    }
}
$galleryImages = array_values(array_unique($galleryImages));

$title = $product['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-6 sm:py-10">

  <!-- Breadcrumbs & Merchant Quick Action Banner -->
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-2 text-xs text-gray-400">
      <a href="<?php echo zavora_asset('index.php'); ?>" class="hover:text-gray-600">Home</a>
      <span>/</span>
      <a href="<?php echo zavora_asset('shop.php'); ?>" class="hover:text-gray-600">Shop</a>
      <span>/</span>
      <a href="<?php echo zavora_asset('shop.php?category=' . urlencode($product['category_slug'] ?? '')); ?>" class="hover:text-gray-600 capitalize">
        <?php echo htmlspecialchars($product['category_name'] ?? 'Products'); ?>
      </a>
      <span>/</span>
      <span class="text-gray-800 font-bold truncate max-w-xs"><?php echo htmlspecialchars($product['title']); ?></span>
    </div>

    <?php if ($canEditProduct): ?>
      <a href="<?php echo zavora_asset('seller/product-edit.php?id=' . $product['id']); ?>" 
         class="px-3.5 py-1.5 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-black rounded-xl shadow transition flex items-center gap-1.5">
        <i class="fas fa-pen-to-square"></i>
        <span>Edit This Product</span>
      </a>
    <?php endif; ?>
  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       TOP HERO: PRODUCT SHOWCASE & PURCHASE CONTROLS
       ════════════════════════════════════════════════════════════════════════ -->
  <div class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-10 shadow-sm">
    
    <!-- Left: Product Image Gallery (5 Cols) -->
    <div class="lg:col-span-5 space-y-3.5">
      <div class="relative rounded-2xl overflow-hidden aspect-square bg-white border border-gray-200 shadow-inner flex items-center justify-center p-3 group">
        <img id="main-product-img" src="<?php echo htmlspecialchars(zavora_image_url($product['thumbnail'])); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" 
             class="w-full h-full object-contain transition-all duration-200">
        
        <?php if (!empty($product['is_boosted'])): ?>
          <span class="absolute top-4 left-4 px-3 py-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-black rounded-lg shadow-md uppercase tracking-wider flex items-center gap-1.5">
            <i class="fas fa-bolt text-yellow-200"></i> <?php echo htmlspecialchars($product['boost_tier'] ?: 'Featured Sponsor'); ?>
          </span>
        <?php endif; ?>

        <?php if (!empty($product['is_preorder'])): ?>
          <span class="absolute top-4 right-4 px-3 py-1 bg-indigo-600 text-white text-xs font-black rounded-lg shadow-md uppercase tracking-wider flex items-center gap-1.5">
            <i class="fas fa-calendar-check"></i> Pre-Order
          </span>
        <?php endif; ?>
      </div>

      <!-- Multi-Image Thumbnail Selector Strip -->
      <?php if (count($galleryImages) > 1): ?>
        <div>
          <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
            <?php foreach ($galleryImages as $gIdx => $gImg): ?>
              <?php $gUrl = zavora_image_url($gImg); ?>
              <button type="button" 
                      onclick="switchGalleryImage('<?php echo htmlspecialchars($gUrl, ENT_QUOTES); ?>', this)" 
                      class="gallery-thumb-btn flex-shrink-0 w-16 h-16 rounded-xl border-2 bg-white p-1 transition-all overflow-hidden <?php echo $gIdx === 0 ? 'border-[#1B2299] shadow-sm ring-2 ring-indigo-100' : 'border-gray-200 opacity-70 hover:opacity-100 hover:border-gray-400'; ?>"
                      title="View Photo <?php echo $gIdx + 1; ?>">
                <img src="<?php echo htmlspecialchars($gUrl); ?>" alt="Thumbnail <?php echo $gIdx + 1; ?>" class="w-full h-full object-contain">
              </button>
            <?php endforeach; ?>
          </div>
          <p class="text-[10px] text-gray-400 mt-1 flex items-center gap-1">
            <i class="fas fa-images text-[#1B2299]"></i> Click any thumbnail above to view angles
          </p>
        </div>
      <?php endif; ?>
      
      <!-- Type & Stock Pill Under Image -->
      <div class="p-3.5 rounded-xl bg-gray-50 border border-gray-150 flex items-center justify-between text-xs text-gray-600">
        <span class="font-bold flex items-center gap-1.5 text-gray-800">
          <?php if ($product['type'] === 'digital'): ?>
            <i class="fas fa-bolt text-[#E4306D]"></i> Instant Digital Download
          <?php elseif ($product['type'] === 'course'): ?>
            <i class="fas fa-graduation-cap text-indigo-600"></i> Video LMS Course
          <?php else: ?>
            <i class="fas fa-truck text-[#1B2299]"></i> Physical Delivery Across Nigeria
          <?php endif; ?>
        </span>
        <span id="product-sku-display" class="text-gray-400 font-mono">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
      </div>
    </div>

    <!-- Right: Product Details & Purchase Controls (7 Cols) -->
    <div class="lg:col-span-7">
      
      <!-- Category & Rating -->
      <div class="flex items-center justify-between gap-4 mb-2">
        <span class="text-xs font-bold uppercase tracking-wider text-[#1B2299]">
          <?php echo htmlspecialchars($product['category_name'] ?? 'ZAVORA Store'); ?>
        </span>
        <a href="#reviews" class="flex items-center gap-1 text-xs text-amber-500 font-bold hover:underline">
          <i class="fas fa-star"></i>
          <span><?php echo number_format($avgRating, 1); ?></span>
          <span class="text-gray-400 font-normal">(<?php echo $reviewCount; ?> verified reviews)</span>
        </a>
      </div>

      <!-- Title -->
      <h1 class="text-2xl sm:text-3xl font-black font-display text-gray-900 mb-4 leading-tight">
        <?php echo htmlspecialchars($product['title']); ?>
      </h1>

      <!-- Pre-Order Alert Banner (If Applicable) -->
      <?php if (!empty($product['is_preorder'])): ?>
        <div class="mb-6 p-4 rounded-2xl bg-indigo-50 border border-indigo-200/80 flex items-start gap-3.5">
          <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
            <i class="fas fa-calendar-alt text-sm"></i>
          </div>
          <div>
            <h4 class="text-sm font-black text-indigo-950">Official Pre-Order Product</h4>
            <p class="text-xs text-indigo-800 mt-0.5 leading-relaxed">
              Expected Release / Ship Date: <strong><?php echo htmlspecialchars($product['preorder_release_date'] ?: 'Upcoming Release'); ?></strong>.
              <?php if (!empty($product['preorder_deposit'])): ?>
                Minimum deposit required to secure initial allocation: <strong><?php echo zavora_format_price($product['preorder_deposit']); ?></strong>.
              <?php else: ?>
                Your pre-order reserves your unit directly from the verified vendor.
              <?php endif; ?>
            </p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Pricing Bar -->
      <div class="p-5 rounded-2xl bg-gray-50 border border-gray-150 mb-6 flex flex-wrap items-baseline justify-between gap-4">
        <div class="flex items-baseline gap-3">
          <span id="product-price-display" class="text-3xl font-black text-[#1B2299]">
            <?php echo zavora_format_price($product['price']); ?>
          </span>
          <?php if ($product['previous_price'] && $product['previous_price'] > $product['price']): ?>
            <span class="text-base text-gray-400 line-through"><?php echo zavora_format_price($product['previous_price']); ?></span>
            <span class="px-2.5 py-0.5 rounded-full bg-[#E4306D] text-white text-xs font-black uppercase">
              Save <?php echo round((($product['previous_price'] - $product['price']) / $product['previous_price']) * 100); ?>%
            </span>
          <?php endif; ?>
        </div>

        <div id="product-stock-display">
          <?php if ($product['type'] === 'physical'): ?>
            <?php if ($product['stock_quantity'] > 10): ?>
              <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> In Stock (<?php echo $product['stock_quantity']; ?> units)
              </span>
            <?php elseif ($product['stock_quantity'] > 0): ?>
              <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span> Low Stock (Only <?php echo $product['stock_quantity']; ?> left)
              </span>
            <?php else: ?>
              <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                Out of Stock
              </span>
            <?php endif; ?>
          <?php elseif ($product['type'] === 'digital'): ?>
            <?php if (!empty($product['stock_quantity']) && $product['stock_quantity'] < 500): ?>
              <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1.5">
                <i class="fas fa-certificate text-amber-600"></i> Limited Release (<?php echo $product['stock_quantity']; ?> licenses remaining)
              </span>
            <?php else: ?>
              <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center gap-1.5">
                <i class="fas fa-infinity text-indigo-600"></i> Lifetime Access • Unlimited Downloads
              </span>
            <?php endif; ?>
          <?php else: ?>
            <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200 flex items-center gap-1.5">
              <i class="fas fa-graduation-cap text-purple-600"></i> Instant LMS Course Access
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Purchase Form with Dynamic Variations -->
      <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST" id="purchase-form" class="space-y-6">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
        <input type="hidden" name="type" value="<?php echo $product['type']; ?>">
        <input type="hidden" name="title" id="form-item-title" value="<?php echo htmlspecialchars($product['title']); ?>">
        <input type="hidden" name="price" id="form-item-price" value="<?php echo $product['price']; ?>">
        <input type="hidden" name="image" value="<?php echo htmlspecialchars(zavora_image_url($product['thumbnail'])); ?>">
        <input type="hidden" name="variant_id" id="form-variant-id" value="">
        <input type="hidden" name="variant_title" id="form-variant-title" value="">

        <!-- Product Variations / Variety Selector -->
        <?php if (!empty($variants)): ?>
          <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 flex items-center gap-2">
                <i class="fas fa-sliders text-[#1B2299]"></i> Select Product Option / Variety
              </h3>
              <span class="text-xs text-gray-500"><?php echo count($variants); ?> options available</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <?php foreach ($variants as $idx => $v): ?>
                <label class="variant-option-card cursor-pointer p-3.5 rounded-xl border-2 transition-all flex items-center justify-between gap-3 bg-white hover:border-[#1B2299] <?php echo $idx === 0 ? 'border-[#1B2299] bg-indigo-50/30' : 'border-gray-200'; ?>">
                  <input type="radio" name="variant_radio" value="<?php echo $v['id']; ?>" 
                         data-price="<?php echo $v['price']; ?>"
                         data-formatted-price="<?php echo zavora_format_price($v['price']); ?>"
                         data-sku="<?php echo htmlspecialchars($v['sku']); ?>"
                         data-stock="<?php echo $v['stock_quantity']; ?>"
                         data-title="<?php echo htmlspecialchars($v['title']); ?>"
                         class="sr-only" <?php echo $idx === 0 ? 'checked' : ''; ?>>
                  <div class="flex-1">
                    <p class="text-xs font-bold text-gray-900"><?php echo htmlspecialchars($v['title']); ?></p>
                    <p class="text-[11px] text-gray-400 font-mono">SKU: <?php echo htmlspecialchars($v['sku']); ?></p>
                  </div>
                  <div class="text-right">
                    <span class="text-xs font-black text-[#1B2299] block"><?php echo zavora_format_price($v['price']); ?></span>
                    <span class="text-[10px] text-gray-400"><?php echo $v['stock_quantity']; ?> in stock</span>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Quantity & Add to Cart Action -->
        <div class="flex items-center gap-4">
          <?php if ($product['type'] === 'physical'): ?>
            <div class="w-28">
              <label for="quantity" class="sr-only">Quantity</label>
              <div class="flex items-center border border-gray-200 rounded-xl bg-gray-50 overflow-hidden">
                <button type="button" onclick="decrementQty()" class="px-3 py-3.5 text-gray-500 hover:text-gray-900 transition">-</button>
                <input type="number" id="quantity" name="quantity" value="1" min="1" max="10" 
                       class="w-full text-center font-bold text-sm bg-transparent focus:outline-none">
                <button type="button" onclick="incrementQty()" class="px-3 py-3.5 text-gray-500 hover:text-gray-900 transition">+</button>
              </div>
            </div>
          <?php else: ?>
            <input type="hidden" name="quantity" value="1">
          <?php endif; ?>

          <button type="submit" class="flex-1 py-4 bg-[#1B2299] hover:bg-[#0B132B] text-white font-extrabold text-sm rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
            <?php if (!empty($product['is_preorder'])): ?>
              <i class="fas fa-calendar-check"></i>
              <span>Pre-Order Allocation</span>
            <?php else: ?>
              <i class="fas fa-cart-plus"></i>
              <span>Add to Cart</span>
            <?php endif; ?>
          </button>

          <!-- Save to Wishlist Button -->
          <?php $isSaved = zavora_in_wishlist($product['id']); ?>
          <button type="button" onclick="toggleProductWishlist(<?php echo $product['id']; ?>)" id="btnWishlistToggle"
                  title="<?php echo $isSaved ? 'Remove from Wishlist' : 'Save to Wishlist'; ?>"
                  class="w-14 h-14 rounded-xl border <?php echo $isSaved ? 'bg-rose-50 border-rose-200 text-rose-600' : 'bg-gray-50 border-gray-200 text-gray-500 hover:text-rose-600 hover:bg-rose-50'; ?> flex items-center justify-center text-xl transition-all shadow-sm flex-shrink-0">
            <i class="<?php echo $isSaved ? 'fas text-rose-500' : 'far'; ?> fa-heart" id="wishlistIcon"></i>
          </button>
        </div>
      </form>

      <!-- Trust Assurance Badges -->
      <div class="mt-8 pt-6 border-t border-gray-150 grid grid-cols-2 gap-4 text-xs text-gray-500">
        <div class="flex items-center gap-2">
          <i class="fas fa-shield-halved text-[#16865B]"></i>
          <span>Encrypted Online Payment</span>
        </div>
        <div class="flex items-center gap-2">
          <i class="fas fa-rotate-left text-[#1B2299]"></i>
          <span>7-Day Return & Replacement Guarantee</span>
        </div>
      </div>

    </div>

  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       LOWER SECTION: JUMIA / KONGA 2-COLUMN CATALOG DOSSIER
       ════════════════════════════════════════════════════════════════════════ -->
  <div class="mt-10 grid lg:grid-cols-12 gap-8 items-start">

    <!-- Left Main Column (8 Cols): In-Depth Product Dossier -->
    <div class="lg:col-span-8 space-y-8">

      <!-- 1. KEY FEATURES & HIGHLIGHTS CARD -->
      <?php if (!empty($highlights)): ?>
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm">
          <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
            <h2 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-wider flex items-center gap-2.5">
              <i class="fas fa-list-check text-[#1B2299]"></i> Key Features & Highlights
            </h2>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full flex items-center gap-1">
              <i class="fas fa-check-circle"></i> Verified Specifications
            </span>
          </div>

          <ul class="space-y-3.5">
            <?php foreach ($highlights as $h): ?>
              <?php
                $prefix = '';
                $body = $h;
                if (strpos($h, ':') !== false) {
                    [$prefix, $body] = explode(':', $h, 2);
                    $prefix = trim($prefix) . ':';
                    $body = trim($body);
                }
              ?>
              <li class="flex items-start gap-3 text-xs sm:text-sm text-gray-700 leading-relaxed">
                <span class="w-5 h-5 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs font-bold mt-0.5">✓</span>
                <div>
                  <?php if (!empty($prefix)): ?>
                    <strong class="font-extrabold text-gray-900"><?php echo htmlspecialchars($prefix); ?></strong>
                  <?php endif; ?>
                  <span><?php echo htmlspecialchars($body); ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- 2. PRODUCT DETAILS & OVERVIEW (RICH TEXT WYSIWYG CONTAINER) -->
      <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
          <h2 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-wider flex items-center gap-2.5">
            <i class="fas fa-align-left text-[#1B2299]"></i> Product Details & Description
          </h2>
          <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Full Overview</span>
        </div>

        <div class="prose prose-slate max-w-none text-gray-700 leading-relaxed text-xs sm:text-sm space-y-4">
          <?php if (!empty($sanitizedNarrative)): ?>
            <?php echo $sanitizedNarrative; ?>
          <?php else: ?>
            <p class="text-gray-400 italic">No additional description provided for this product.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- 3. TECHNICAL SPECIFICATIONS MATRIX -->
      <?php if (!empty($parsedSpecs)): ?>
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm">
          <div class="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
            <h2 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-wider flex items-center gap-2.5">
              <i class="fas fa-microchip text-[#1B2299]"></i> Technical Specifications
            </h2>
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1">
              <i class="fas fa-certificate text-emerald-500"></i> Verified Metrics
            </span>
          </div>

          <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xs">
            <table class="w-full text-left text-xs sm:text-sm">
              <tbody class="divide-y divide-gray-150">
                <?php $sCount = 0; foreach ($parsedSpecs as $sKey => $sVal): $sCount++; ?>
                  <tr class="<?php echo ($sCount % 2 === 1) ? 'bg-slate-50/70' : 'bg-white'; ?> transition-colors hover:bg-indigo-50/30">
                    <td class="py-3 px-4 font-bold text-gray-600 w-2/5 border-r border-gray-150 text-[11px] sm:text-xs uppercase tracking-wider">
                      <?php echo htmlspecialchars($sKey); ?>
                    </td>
                    <td class="py-3 px-4 font-semibold text-gray-900">
                      <?php echo htmlspecialchars($sVal); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- 4. WHAT'S IN THE BOX CARD -->
      <?php if (!empty($boxContents)): ?>
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm">
          <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-100">
            <h2 class="text-base sm:text-lg font-black text-gray-900 uppercase tracking-wider flex items-center gap-2.5">
              <i class="fas fa-box-open text-[#1B2299]"></i> What's In The Box
            </h2>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full">
              Package Contents
            </span>
          </div>

          <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-start gap-3">
            <div class="w-8 h-8 rounded-xl bg-white border border-gray-200 text-gray-700 flex items-center justify-center flex-shrink-0 mt-0.5 shadow-2xs">
              <i class="fas fa-boxes-packing text-xs text-[#1B2299]"></i>
            </div>
            <p class="text-xs sm:text-sm font-semibold text-gray-800 leading-relaxed pt-1">
              <?php echo htmlspecialchars($boxContents); ?>
            </p>
          </div>
        </div>
      <?php endif; ?>

      <!-- 5. CUSTOMER REVIEWS & RATINGS SECTION -->
      <div id="reviews" class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-gray-150">
          <div>
            <h2 class="text-xl sm:text-2xl font-black font-display text-gray-900 mb-1">Customer Reviews & Ratings</h2>
            <p class="text-xs text-gray-500">Verified buyer feedback and authentic ratings.</p>
          </div>
          <div class="flex items-center gap-4">
            <div class="text-center sm:text-right">
              <div class="text-3xl font-black text-gray-900 leading-none"><?php echo number_format($avgRating, 1); ?></div>
              <div class="flex items-center gap-1 text-xs text-amber-500 my-1 justify-center sm:justify-end">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <i class="fas fa-star <?php echo $s <= round($avgRating) ? 'text-amber-500' : 'text-gray-200'; ?>"></i>
                <?php endfor; ?>
              </div>
              <div class="text-[11px] text-gray-400 font-medium">Based on <?php echo $reviewCount; ?> verified ratings</div>
            </div>
          </div>
        </div>

        <!-- Review Cards List -->
        <div class="py-6 space-y-4">
          <?php if (empty($reviews)): ?>
            <div class="py-8 text-center text-gray-500 text-xs">
              <p>No customer reviews yet. Be the first to share your experience with this product!</p>
            </div>
          <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
              <div class="p-5 rounded-2xl bg-gray-50 border border-gray-150 transition-colors">
                <div class="flex items-start justify-between gap-4 mb-2">
                  <div>
                    <div class="flex items-center gap-2 mb-1">
                      <span class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($rev['customer_name']); ?></span>
                      <?php if (!empty($rev['is_verified_buyer'])): ?>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                          <i class="fas fa-check-circle text-[9px]"></i> Verified Buyer
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-1 text-xs text-amber-500">
                      <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="fas fa-star <?php echo $s <= $rev['rating'] ? 'text-amber-500' : 'text-gray-200'; ?> text-[11px]"></i>
                      <?php endfor; ?>
                      <?php if (!empty($rev['title'])): ?>
                        <span class="text-xs font-bold text-gray-800 ml-1.5"><?php echo htmlspecialchars($rev['title']); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <span class="text-[11px] text-gray-400 font-mono">
                    <?php echo date('M j, Y', strtotime($rev['created_at'])); ?>
                  </span>
                </div>
                <p class="text-xs text-gray-700 leading-relaxed mt-2">
                  <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                </p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Write a Review Form -->
        <div class="pt-6 border-t border-gray-150">
          <h3 class="text-sm font-black uppercase tracking-wider text-gray-900 mb-4">Write a Customer Review</h3>
          <form action="<?php echo zavora_asset('product.php?id=' . $id); ?>" method="POST" class="space-y-4 max-w-xl">
            <input type="hidden" name="action" value="submit_review">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Your Full Name</label>
                <input type="text" name="customer_name" required placeholder="Full Name"
                       class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Rating</label>
                <select name="rating" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
                  <option value="5">★★★★★ (5 - Excellent)</option>
                  <option value="4">★★★★☆ (4 - Very Good)</option>
                  <option value="3">★★★☆☆ (3 - Average)</option>
                  <option value="2">★★☆☆☆ (2 - Below Expectations)</option>
                  <option value="1">★☆☆☆☆ (1 - Poor)</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Review Headline</label>
              <input type="text" name="review_title" placeholder="Summary of your experience"
                     class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Your Feedback</label>
              <textarea name="comment" rows="3" required placeholder="Detailed review of packaging, functionality, delivery, or build quality..."
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]"></textarea>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold rounded-xl transition-all shadow-sm">
              Submit Review
            </button>
          </form>
        </div>

      </div>

    </div>

    <!-- Right Sticky Column (4 Cols): Delivery, Warranty & Seller Profile -->
    <div class="lg:col-span-4 space-y-6">

      <!-- DELIVERY & SHIPPING CARD -->
      <div class="bg-white rounded-3xl border border-gray-200/90 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
          <h3 class="text-xs font-black uppercase tracking-wider text-gray-900 flex items-center gap-2">
            <i class="fas fa-truck-fast text-[#1B2299]"></i> Delivery & Returns
          </h3>
          <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Express Available</span>
        </div>

        <div class="space-y-3.5 text-xs">
          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-indigo-50 text-[#1B2299] flex items-center justify-center flex-shrink-0 text-xs">
              <i class="fas fa-location-dot"></i>
            </div>
            <div>
              <h5 class="font-extrabold text-gray-900">Door Delivery Across Nigeria</h5>
              <p class="text-gray-500 text-[11px] mt-0.5">Delivery within 24-72 hours across Lagos, Abuja, Port Harcourt, and 36 states.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-indigo-50 text-[#1B2299] flex items-center justify-center flex-shrink-0 text-xs">
              <i class="fas fa-box"></i>
            </div>
            <div>
              <h5 class="font-extrabold text-gray-900">Pickup Stations</h5>
              <p class="text-gray-500 text-[11px] mt-0.5">Collect your package from 150+ verified pickup hubs nationwide.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xs">
              <i class="fas fa-rotate-left"></i>
            </div>
            <div>
              <h5 class="font-extrabold text-gray-900">7-Day Free Returns</h5>
              <p class="text-gray-500 text-[11px] mt-0.5">Hassle-free replacement or full refund on defective items.</p>
            </div>
          </div>

          <div class="flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0 text-xs">
              <i class="fas fa-shield-check"></i>
            </div>
            <div>
              <h5 class="font-extrabold text-gray-900">100% Genuine Guaranteed</h5>
              <p class="text-gray-500 text-[11px] mt-0.5">Every unit is sourced directly from certified brands and authorized distributors.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- VERIFIED SELLER INFORMATION CARD -->
      <?php if (!empty($product['vendor_store_name'])): ?>
        <div class="bg-white rounded-3xl border border-gray-200/90 p-6 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-xs font-black uppercase tracking-wider text-gray-900 flex items-center gap-2">
              <i class="fas fa-store text-[#1B2299]"></i> Seller Information
            </h3>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full flex items-center gap-1">
              <i class="fas fa-check-circle"></i> Verified
            </span>
          </div>

          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-[#1B2299] text-white flex items-center justify-center font-bold text-base shadow-sm">
                <i class="fas fa-store"></i>
              </div>
              <div>
                <h4 class="text-sm font-black text-gray-900"><?php echo htmlspecialchars($product['vendor_store_name']); ?></h4>
                <p class="text-[11px] text-gray-400">Authorized Merchant Partner</p>
              </div>
            </div>

            <div class="p-3 bg-slate-50 rounded-2xl grid grid-cols-2 gap-2 text-center">
              <div>
                <span class="text-xs font-black text-emerald-600">98%</span>
                <span class="text-[10px] text-gray-400 block font-semibold">Seller Score</span>
              </div>
              <div>
                <span class="text-xs font-black text-indigo-700">99.4%</span>
                <span class="text-[10px] text-gray-400 block font-semibold">On-Time Dispatch</span>
              </div>
            </div>

            <?php if (!empty($product['vendor_slug'])): ?>
              <a href="<?php echo zavora_asset('shop.php?vendor=' . urlencode($product['vendor_slug'])); ?>" 
                 class="w-full py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5">
                <span>Visit Seller Store</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- SELLER QUICK ACTION CARD (IF AUTHORIZED) -->
      <?php if ($canEditProduct): ?>
        <div class="bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-3xl p-6 shadow-xl space-y-3">
          <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center text-xs text-amber-300">
              <i class="fas fa-pen-ruler"></i>
            </span>
            <div>
              <h4 class="text-xs font-black uppercase tracking-wider">Merchant Quick Actions</h4>
              <p class="text-[10px] text-blue-200">Catalog Listing Owner</p>
            </div>
          </div>
          <p class="text-xs text-blue-100/90 leading-relaxed">
            Update prices, edit rich text descriptions with Quill, manage key features, variety options, and stock allocations.
          </p>
          <a href="<?php echo zavora_asset('seller/product-edit.php?id=' . $product['id']); ?>" 
             class="w-full py-3 bg-amber-400 hover:bg-amber-500 text-gray-950 font-black text-xs rounded-xl shadow transition flex items-center justify-center gap-2">
            <i class="fas fa-pen-to-square"></i>
            <span>Edit Product Listing</span>
          </a>
        </div>
      <?php endif; ?>

    </div>

  </div>

</div>

<script>
function incrementQty() {
  const input = document.getElementById('quantity');
  if (input && parseInt(input.value) < parseInt(input.max || 10)) {
    input.value = parseInt(input.value) + 1;
  }
}

function decrementQty() {
  const input = document.getElementById('quantity');
  if (input && parseInt(input.value) > 1) {
    input.value = parseInt(input.value) - 1;
  }
}

// Interactive Variant Selector
document.addEventListener('DOMContentLoaded', () => {
  const variantRadios = document.querySelectorAll('input[name="variant_radio"]');
  const priceDisplay = document.getElementById('product-price-display');
  const skuDisplay = document.getElementById('product-sku-display');
  const stockDisplay = document.getElementById('product-stock-display');
  const formPrice = document.getElementById('form-item-price');
  const formVariantId = document.getElementById('form-variant-id');
  const formVariantTitle = document.getElementById('form-variant-title');
  const cards = document.querySelectorAll('.variant-option-card');

  function updateSelectedVariant(radio) {
    if (!radio) return;
    const price = radio.dataset.price;
    const formattedPrice = radio.dataset.formattedPrice;
    const sku = radio.dataset.sku;
    const stock = parseInt(radio.dataset.stock);
    const title = radio.dataset.title;
    const id = radio.value;

    if (priceDisplay) priceDisplay.textContent = formattedPrice;
    if (skuDisplay) skuDisplay.textContent = 'SKU: ' + sku;
    if (formPrice) formPrice.value = price;
    if (formVariantId) formVariantId.value = id;
    if (formVariantTitle) formVariantTitle.value = title;

    if (stockDisplay) {
      if (stock > 10) {
        stockDisplay.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> In Stock (${stock} units)</span>`;
      } else if (stock > 0) {
        stockDisplay.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span> Low Stock (Only ${stock} left)</span>`;
      } else {
        stockDisplay.innerHTML = `<span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">Out of Stock</span>`;
      }
    }

    // Highlight card style
    cards.forEach(c => {
      c.classList.remove('border-[#1B2299]', 'bg-indigo-50/30');
      c.classList.add('border-gray-200');
    });
    const parentCard = radio.closest('.variant-option-card');
    if (parentCard) {
      parentCard.classList.remove('border-gray-200');
      parentCard.classList.add('border-[#1B2299]', 'bg-indigo-50/30');
    }
  }

  variantRadios.forEach(radio => {
    radio.addEventListener('change', () => updateSelectedVariant(radio));
    if (radio.checked) updateSelectedVariant(radio);
  });
});

// Interactive Gallery Photo Switcher
function switchGalleryImage(url, btn) {
  const mainImg = document.getElementById('main-product-img');
  if (mainImg) {
    mainImg.style.opacity = '0.3';
    setTimeout(() => {
      mainImg.src = url;
      mainImg.style.opacity = '1';
    }, 120);
  }
  document.querySelectorAll('.gallery-thumb-btn').forEach(b => {
    b.classList.remove('border-[#1B2299]', 'shadow-sm', 'ring-2', 'ring-indigo-100');
    b.classList.add('border-gray-200', 'opacity-70');
  });
  if (btn) {
    btn.classList.add('border-[#1B2299]', 'shadow-sm', 'ring-2', 'ring-indigo-100');
    btn.classList.remove('border-gray-200', 'opacity-70');
  }
}

// Interactive Wishlist Toggle with Header Synchronization
function toggleProductWishlist(productId) {
  const btn = document.getElementById('btnWishlistToggle');
  const icon = document.getElementById('wishlistIcon');
  const badge = document.getElementById('headerWishlistBadge');

  fetch('<?php echo zavora_asset("wishlist.php?action=toggle&format=json&id="); ?>' + encodeURIComponent(productId))
    .then(r => r.json())
    .then(data => {
      if (data && data.success) {
        if (data.in_wishlist) {
          if (btn) {
            btn.classList.remove('bg-gray-50', 'border-gray-200', 'text-gray-500');
            btn.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-600');
            btn.setAttribute('title', 'Remove from Wishlist');
          }
          if (icon) {
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-rose-500');
          }
        } else {
          if (btn) {
            btn.classList.remove('bg-rose-50', 'border-rose-200', 'text-rose-600');
            btn.classList.add('bg-gray-50', 'border-gray-200', 'text-gray-500');
            btn.setAttribute('title', 'Save to Wishlist');
          }
          if (icon) {
            icon.classList.remove('fas', 'text-rose-500');
            icon.classList.add('far');
          }
        }

        if (badge) {
          badge.textContent = data.count;
          if (data.count > 0) {
            badge.classList.remove('hidden');
          } else {
            badge.classList.add('hidden');
          }
        }
      }
    })
    .catch(err => console.error('Wishlist error:', err));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
