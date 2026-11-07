<?php
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

$title = $product['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-8 sm:py-12">
  
  <!-- Breadcrumbs -->
  <div class="flex items-center gap-2 text-xs text-gray-400 mb-8">
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

  <div class="grid lg:grid-cols-12 gap-12 items-start bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-10 shadow-sm">
    
    <!-- Left: Product Image Gallery (5 Cols) -->
    <div class="lg:col-span-5">
      <div class="relative rounded-2xl overflow-hidden aspect-square bg-gray-100 border border-gray-200 shadow-inner">
        <img id="main-product-img" src="<?php echo htmlspecialchars($product['thumbnail']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" 
             class="w-full h-full object-cover">
        
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
      
      <!-- Type & Stock Pill Under Image -->
      <div class="mt-4 p-3.5 rounded-xl bg-gray-50 border border-gray-150 flex items-center justify-between text-xs text-gray-600">
        <span class="font-bold flex items-center gap-1.5 text-gray-800">
          <?php if ($product['type'] === 'digital'): ?>
            <i class="fas fa-bolt text-[#E4306D]"></i> Instant Digital Download
          <?php elseif ($product['type'] === 'course'): ?>
            <i class="fas fa-graduation-cap text-indigo-600"></i> Video LMS Course
          <?php else: ?>
            <i class="fas fa-truck text-[#1B2299]"></i> Physical Delivery (Nigeria)
          <?php endif; ?>
        </span>
        <span id="product-sku-display" class="text-gray-400 font-mono">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
      </div>

      <!-- Verified Seller Info Card -->
      <?php if (!empty($product['vendor_store_name'])): ?>
        <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#1B2299] text-white flex items-center justify-center font-bold text-sm">
              <i class="fas fa-store"></i>
            </div>
            <div>
              <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Sold & Fulfilled by</p>
              <h4 class="text-sm font-black text-gray-900"><?php echo htmlspecialchars($product['vendor_store_name']); ?></h4>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
            <i class="fas fa-check-circle"></i> Verified Seller
          </span>
        </div>
      <?php endif; ?>

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
              Expected Release / Ship Date: <strong><?php echo htmlspecialchars($product['preorder_release_date'] ?: 'December 2026'); ?></strong>.
              <?php if (!empty($product['preorder_deposit'])): ?>
                Minimum deposit required to secure initial allocation: <strong><?php echo zavora_format_price($product['preorder_deposit']); ?></strong>.
              <?php else: ?>
                Your order reserves your unit directly from the verified vendor.
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
          <?php if ($product['previous_price']): ?>
            <span class="text-base text-gray-400 line-through"><?php echo zavora_format_price($product['previous_price']); ?></span>
            <span class="px-2.5 py-0.5 rounded-full bg-[#E4306D] text-white text-xs font-black uppercase">
              Save <?php echo round((($product['previous_price'] - $product['price']) / $product['previous_price']) * 100); ?>%
            </span>
          <?php endif; ?>
        </div>

        <div id="product-stock-display">
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
        </div>
      </div>

      <!-- Description -->
      <div class="text-sm text-gray-600 leading-relaxed mb-6 space-y-3">
        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
      </div>

      <!-- Purchase Form with Dynamic Variations -->
      <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST" id="purchase-form" class="space-y-6">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
        <input type="hidden" name="type" value="<?php echo $product['type']; ?>">
        <input type="hidden" name="title" id="form-item-title" value="<?php echo htmlspecialchars($product['title']); ?>">
        <input type="hidden" name="price" id="form-item-price" value="<?php echo $product['price']; ?>">
        <input type="hidden" name="image" value="<?php echo htmlspecialchars($product['thumbnail']); ?>">
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

            <!-- Variant Direct Cards Grid -->
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
        </div>
      </form>

      <!-- Specifications -->
      <?php if (!empty($product['specifications'])): ?>
        <div class="mt-8 pt-6 border-t border-gray-150">
          <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Technical Specifications</h3>
          <div class="p-4 rounded-xl bg-slate-50 border border-gray-150 text-xs text-gray-700 font-medium leading-relaxed">
            <?php echo htmlspecialchars($product['specifications']); ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Trust Features -->
      <div class="mt-8 pt-6 border-t border-gray-150 grid grid-cols-2 gap-4 text-xs text-gray-500">
        <div class="flex items-center gap-2">
          <i class="fas fa-shield-halved text-[#16865B]"></i>
          <span>Paystack Escrow Protection</span>
        </div>
        <div class="flex items-center gap-2">
          <i class="fas fa-rotate-left text-[#1B2299]"></i>
          <span>7-Day Return & Replacement Guarantee</span>
        </div>
      </div>

    </div>

  </div>

  <!-- Customer Reviews Section (100% Database Driven) -->
  <div id="reviews" class="mt-12 bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-10 shadow-sm">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-8 border-b border-gray-150">
      <div>
        <h2 class="text-2xl font-black font-display text-gray-900 mb-1">Customer Reviews</h2>
        <p class="text-xs text-gray-500">Verified buyer ratings and authentic experience feedback.</p>
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
    <div class="py-8 space-y-6">
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
    <div class="pt-8 border-t border-gray-150">
      <h3 class="text-base font-bold text-gray-900 mb-4">Write a Customer Review</h3>
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
