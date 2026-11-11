<?php
/**
 * ZAVORA Storefront — Customer Wishlist Suite
 *
 * Provides complete saved-items functionality:
 * - Session-backed persistent wishlist
 * - Real-time AJAX add / remove toggle with dynamic counters
 * - Move to Cart direct action
 * - Jumia & Konga standard responsive presentation
 */

require_once __DIR__ . '/config/config.php';
$pdo = zavora_get_db();

// Handle AJAX or POST Wishlist Actions
$action = $_REQUEST['action'] ?? '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (($_GET['format'] ?? '') === 'json');

if ($action === 'toggle') {
    $productId = intval($_REQUEST['id'] ?? $_POST['id'] ?? 0);
    $inWishlist = zavora_toggle_wishlist($productId);
    $count = zavora_wishlist_count();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'     => true,
            'product_id'  => $productId,
            'in_wishlist' => $inWishlist,
            'count'       => $count,
            'message'     => $inWishlist ? 'Saved to your Wishlist!' : 'Removed from Wishlist'
        ]);
        exit;
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? zavora_asset('wishlist.php');
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'remove') {
    $productId = intval($_GET['id'] ?? 0);
    if ($productId > 0 && zavora_in_wishlist($productId)) {
        zavora_toggle_wishlist($productId);
    }
    header('Location: ' . zavora_asset('wishlist.php'));
    exit;
}

if ($action === 'clear') {
    $_SESSION['zavora_wishlist'] = [];
    header('Location: ' . zavora_asset('wishlist.php'));
    exit;
}

// Fetch Saved Products
$wishlistIds = zavora_get_wishlist();
$savedProducts = [];

if (!empty($wishlistIds)) {
    $placeholders = implode(',', array_fill(0, count($wishlistIds), '?'));
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug, 
               v.store_name as vendor_store_name, v.slug as vendor_slug
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN vendors v ON p.vendor_id = v.id
        WHERE p.id IN ($placeholders)
        ORDER BY p.id DESC
    ");
    $stmt->execute($wishlistIds);
    $savedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$title = 'My Wishlist (' . count($savedProducts) . ') — ZAVORA';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-8 sm:py-12">

  <!-- Breadcrumbs -->
  <div class="flex items-center gap-2 text-xs text-gray-400 mb-6">
    <a href="<?php echo zavora_asset('index.php'); ?>" class="hover:text-gray-600">Home</a>
    <span>/</span>
    <a href="<?php echo zavora_asset('shop.php'); ?>" class="hover:text-gray-600">Shop</a>
    <span>/</span>
    <span class="text-gray-800 font-bold">Saved Items</span>
  </div>

  <!-- Page Header -->
  <div class="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-gray-200 mb-8">
    <div>
      <h1 class="text-2xl sm:text-3xl font-black font-display text-gray-900 flex items-center gap-3">
        <i class="fas fa-heart text-rose-500"></i>
        <span>My Saved Wishlist</span>
      </h1>
      <p class="text-xs text-gray-500 mt-1">Review saved products, monitor price changes, or move items to your cart.</p>
    </div>

    <?php if (!empty($savedProducts)): ?>
      <div class="flex items-center gap-3">
        <span class="px-3 py-1 rounded-full bg-rose-50 text-rose-600 border border-rose-200 text-xs font-black">
          <?php echo count($savedProducts); ?> <?php echo count($savedProducts) === 1 ? 'Item' : 'Items'; ?> Saved
        </span>
        <a href="<?php echo zavora_asset('wishlist.php?action=clear'); ?>" 
           onclick="return confirm('Clear all items from your wishlist?')"
           class="px-3.5 py-1.5 bg-gray-100 hover:bg-rose-50 text-gray-600 hover:text-rose-600 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
          <i class="fas fa-trash-can text-[11px]"></i>
          <span>Clear All</span>
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($savedProducts)): ?>
    <!-- Empty Wishlist State -->
    <div class="bg-white rounded-3xl border border-gray-200/90 p-12 text-center max-w-lg mx-auto shadow-sm my-8 space-y-5">
      <div class="w-20 h-20 rounded-3xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 text-3xl mx-auto shadow-inner">
        <i class="far fa-heart"></i>
      </div>
      <div>
        <h3 class="text-lg font-black text-gray-900">Your Wishlist is Empty</h3>
        <p class="text-xs text-gray-500 mt-1.5 leading-relaxed">
          You haven't saved any items yet. Tap the heart icon on any product in our marketplace to save items you want to purchase later.
        </p>
      </div>
      <div class="pt-2">
        <a href="<?php echo zavora_asset('shop.php'); ?>" 
           class="inline-flex items-center gap-2 px-6 py-3 bg-[#1B2299] hover:bg-[#0B132B] text-white font-extrabold text-xs rounded-xl shadow-md transition">
          <i class="fas fa-bag-shopping"></i>
          <span>Explore Products Now</span>
        </a>
      </div>
    </div>
  <?php else: ?>
    <!-- Wishlist Products Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($savedProducts as $item): ?>
        <div class="group bg-white rounded-2xl border border-gray-200/90 hover:border-[#1B2299]/30 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden relative">
          
          <div>
            <!-- Image & Remove Button -->
            <div class="relative bg-gray-100 aspect-square overflow-hidden">
              <a href="<?php echo zavora_asset('product.php?id=' . $item['id']); ?>" class="block w-full h-full">
                <img src="<?php echo htmlspecialchars(zavora_image_url($item['thumbnail'])); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" 
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
              </a>
              
              <!-- Remove from Wishlist Button -->
              <a href="<?php echo zavora_asset('wishlist.php?action=remove&id=' . $item['id']); ?>" 
                 title="Remove from saved items"
                 class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/95 text-rose-600 hover:bg-rose-600 hover:text-white shadow flex items-center justify-center text-xs transition">
                <i class="fas fa-trash-can"></i>
              </a>

              <!-- Type / Preorder Badge -->
              <?php if (!empty($item['is_preorder'])): ?>
                <span class="absolute top-3 left-3 bg-indigo-600 text-white text-[10px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">
                  Pre-Order
                </span>
              <?php elseif ($item['previous_price'] && $item['previous_price'] > $item['price']): ?>
                <span class="absolute top-3 left-3 bg-[#E4306D] text-white text-[10px] font-black px-2 py-0.5 rounded-md uppercase">
                  -<?php echo round((($item['previous_price'] - $item['price']) / $item['previous_price']) * 100); ?>%
                </span>
              <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="p-5">
              <div class="flex items-center justify-between text-[11px] text-gray-400 mb-1">
                <span class="uppercase font-bold text-[#1B2299]"><?php echo htmlspecialchars($item['category_name'] ?? 'General'); ?></span>
                <span class="flex items-center gap-1 text-amber-500 font-bold">
                  <i class="fas fa-star text-[10px]"></i> <?php echo number_format($item['rating'] ?: 5.0, 1); ?>
                </span>
              </div>

              <h3 class="font-bold text-sm text-gray-900 line-clamp-2 mb-2 group-hover:text-[#1B2299] transition-colors leading-snug">
                <a href="<?php echo zavora_asset('product.php?id=' . $item['id']); ?>">
                  <?php echo htmlspecialchars($item['title']); ?>
                </a>
              </h3>

              <div class="flex items-baseline gap-2 mb-2">
                <span class="text-base font-black text-[#1B2299]"><?php echo zavora_format_price($item['price']); ?></span>
                <?php if ($item['previous_price'] && $item['previous_price'] > $item['price']): ?>
                  <span class="text-xs text-gray-400 line-through"><?php echo zavora_format_price($item['previous_price']); ?></span>
                <?php endif; ?>
              </div>

              <!-- Stock Pill -->
              <div class="text-[11px]">
                <?php if ($item['stock_quantity'] > 0): ?>
                  <span class="text-emerald-700 font-bold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In Stock
                  </span>
                <?php else: ?>
                  <span class="text-rose-600 font-bold">Out of Stock</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Move to Cart Form -->
          <div class="p-5 pt-0">
            <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST" class="space-y-2">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
              <input type="hidden" name="type" value="<?php echo $item['type']; ?>">
              <input type="hidden" name="title" value="<?php echo htmlspecialchars($item['title']); ?>">
              <input type="hidden" name="price" value="<?php echo $item['price']; ?>">
              <input type="hidden" name="image" value="<?php echo htmlspecialchars(zavora_image_url($item['thumbnail'])); ?>">
              <input type="hidden" name="quantity" value="1">
              
              <button type="submit" 
                      class="w-full py-2.5 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-xl text-xs font-black shadow transition flex items-center justify-center gap-2">
                <i class="fas fa-cart-plus"></i>
                <span>Move to Cart</span>
              </button>
            </form>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
