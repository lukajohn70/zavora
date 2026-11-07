<?php
require_once __DIR__ . '/config/config.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id = (int) ($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? 'physical';
        $title = $_POST['title'] ?? 'Item';
        $price = (float) ($_POST['price'] ?? 0);
        $image = $_POST['image'] ?? '';
        $qty = (int) ($_POST['quantity'] ?? 1);
        $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $variantTitle = !empty($_POST['variant_title']) ? trim($_POST['variant_title']) : null;

        $extra = [];
        if ($variantId) {
            $extra['variant_id'] = $variantId;
            $extra['variant_title'] = $variantTitle;
            if ($variantTitle) {
                $title .= " · " . $variantTitle;
            }
        }

        zavora_add_to_cart($id, $type, $title, $price, $image, $qty, $extra);
        header('Location: ' . zavora_asset('cart.php'));
        exit;
    }

    if ($action === 'remove') {
        $key = $_POST['key'] ?? '';
        zavora_remove_from_cart($key);
        header('Location: ' . zavora_asset('cart.php'));
        exit;
    }

    if ($action === 'clear') {
        zavora_clear_cart();
        header('Location: ' . zavora_asset('cart.php'));
        exit;
    }
}

$title = "Shopping Cart";
require_once __DIR__ . '/includes/header.php';

$cart = zavora_get_cart();
$totals = zavora_cart_totals();
?>

<div class="container mx-auto px-4 lg:px-8 py-8 sm:py-12">
  <div class="mb-8">
    <h1 class="text-3xl font-black font-display text-gray-900">Your Shopping Cart</h1>
    <p class="text-sm text-gray-500 mt-1">Review your physical products, digital assets, and course enrolments.</p>
  </div>

  <?php if (empty($cart)): ?>
    <div class="bg-white rounded-3xl border border-gray-200/90 p-12 text-center max-w-lg mx-auto shadow-sm">
      <div class="w-20 h-20 rounded-full bg-gray-50 text-gray-400 flex items-center justify-center text-3xl mx-auto mb-4">
        <i class="fas fa-bag-shopping"></i>
      </div>
      <h3 class="text-xl font-bold text-gray-900 mb-2">Your cart is empty</h3>
      <p class="text-sm text-gray-500 mb-6 leading-relaxed">
        Discover physical gadgets, creative templates, or take your skills to the next level with our online courses.
      </p>
      <div class="flex flex-col sm:flex-row justify-center gap-3">
        <a href="<?php echo zavora_asset('shop.php'); ?>" class="px-6 py-3 bg-[#1B2299] text-white text-xs font-bold rounded-xl hover:bg-[#0B132B] transition-colors">
          Browse Marketplace
        </a>
        <a href="<?php echo zavora_asset('academy.php'); ?>" class="px-6 py-3 bg-gray-100 text-gray-800 text-xs font-bold rounded-xl hover:bg-gray-200 transition-colors">
          Explore Academy
        </a>
      </div>
    </div>
  <?php else: ?>

    <div class="grid lg:grid-cols-12 gap-8 items-start">
      
      <!-- Cart Items Table (Left 8 Cols) -->
      <div class="lg:col-span-8 bg-white rounded-2xl border border-gray-200/90 p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
          <span class="text-sm font-bold text-gray-900"><?php echo count($cart); ?> Items in Cart</span>
          <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST" onsubmit="return confirm('Empty your cart?');">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="text-xs text-red-600 hover:underline font-semibold">
              <i class="far fa-trash-alt mr-1"></i>Clear Cart
            </button>
          </form>
        </div>

        <div class="divide-y divide-gray-100">
          <?php foreach ($cart as $key => $item): ?>
            <div class="py-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-gray-100 overflow-hidden flex-shrink-0 border border-gray-150">
                  <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="" class="w-full h-full object-cover">
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <?php if ($item['type'] === 'course'): ?>
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-[#87207C] border border-purple-200">
                        <i class="fas fa-graduation-cap mr-1"></i>Academy Course
                      </span>
                    <?php elseif ($item['type'] === 'digital'): ?>
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-pink-50 text-[#E4306D] border border-pink-200">
                        <i class="fas fa-download mr-1"></i>Digital Asset
                      </span>
                    <?php else: ?>
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-[#1B2299] border border-blue-200">
                        <i class="fas fa-box mr-1"></i>Physical Delivery
                      </span>
                    <?php endif; ?>
                  </div>
                  <h4 class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($item['title']); ?></h4>
                  <span class="text-xs font-black text-[#1B2299]"><?php echo zavora_format_price($item['price']); ?></span>
                </div>
              </div>

              <!-- Item Quantity & Actions -->
              <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto">
                <div class="text-xs text-gray-500 font-semibold">
                  Qty: <span class="font-bold text-gray-900"><?php echo $item['quantity']; ?></span>
                </div>
                <div class="text-right">
                  <span class="text-sm font-black text-gray-900 block">
                    <?php echo zavora_format_price($item['price'] * $item['quantity']); ?>
                  </span>
                  <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST" class="mt-1">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
                    <button type="submit" class="text-[11px] text-gray-400 hover:text-red-600 transition-colors">
                      Remove
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-100 flex items-center justify-between">
          <a href="<?php echo zavora_asset('shop.php'); ?>" class="text-xs font-bold text-[#1B2299] hover:underline flex items-center gap-1">
            <i class="fas fa-arrow-left"></i>
            <span>Continue Shopping</span>
          </a>
        </div>
      </div>

      <!-- Order Summary Card (Right 4 Cols) -->
      <div class="lg:col-span-4 bg-white rounded-2xl border border-gray-200/90 p-6 shadow-sm sticky top-24">
        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b border-gray-100">Order Summary</h3>

        <div class="space-y-3 text-sm text-gray-600 mb-6">
          <div class="flex justify-between">
            <span>Subtotal</span>
            <span class="font-bold text-gray-900"><?php echo zavora_format_price($totals['subtotal']); ?></span>
          </div>

          <div class="flex justify-between items-center">
            <div>
              <span>Delivery Fee</span>
              <?php if (!$totals['has_physical']): ?>
                <span class="block text-[11px] text-[#16865B] font-semibold">Digital & Courses (₦0 delivery)</span>
              <?php else: ?>
                <span class="block text-[11px] text-gray-400">Calculated at checkout</span>
              <?php endif; ?>
            </div>
            <span class="font-bold text-gray-900">
              <?php echo $totals['has_physical'] ? zavora_format_price($totals['delivery_fee']) : 'FREE'; ?>
            </span>
          </div>

          <div class="pt-3 border-t border-gray-100 flex justify-between text-base">
            <span class="font-black text-gray-900">Total</span>
            <span class="font-black text-xl text-[#1B2299]"><?php echo zavora_format_price($totals['total']); ?></span>
          </div>
        </div>

        <a href="<?php echo zavora_asset('checkout.php'); ?>" class="w-full py-3.5 bg-gradient-to-r from-[#1B2299] to-[#0B132B] hover:from-[#0B132B] hover:to-[#1B2299] text-white font-bold text-sm rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2 text-center">
          <span>Proceed to Checkout</span>
          <i class="fas fa-lock text-xs"></i>
        </a>

        <div class="mt-6 text-center text-xs text-gray-400 flex items-center justify-center gap-2">
          <i class="fas fa-shield-alt text-[#16865B]"></i>
          <span>Paystack 256-Bit SSL Encrypted Checkout</span>
        </div>
      </div>

    </div>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
