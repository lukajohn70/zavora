<?php
require_once __DIR__ . '/config/config.php';

$cart = zavora_get_cart();
if (empty($cart)) {
    header('Location: ' . zavora_asset('cart.php'));
    exit;
}

$totals = zavora_cart_totals();
$error = '';
$success = '';

// Handle Checkout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $email = strtolower(trim($_POST['customer_email'] ?? ''));
    $phone = trim($_POST['customer_phone'] ?? '');
    $state = trim($_POST['delivery_state'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');

    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid full name and email address.';
    } elseif ($totals['has_physical'] && (empty($address) || empty($state))) {
        $error = 'Please provide your full delivery address and state for physical items.';
    } else {
        // Calculate updated totals with selected state
        $finalTotals = zavora_cart_totals($state);
        $orderNumber = 'ZAV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $payReference = 'PSK-' . $orderNumber . '-' . rand(1000, 9999);

        $pdo = zavora_get_db();

        try {
            // 1. Create Order
            $orderStmt = $pdo->prepare("INSERT INTO orders (
                order_number, customer_name, customer_email, customer_phone, delivery_address, delivery_state, delivery_fee, subtotal, total_amount, payment_status, payment_reference, payment_method, has_physical_items, has_digital_items, has_courses
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, 'paystack', ?, ?, ?)");

            $orderStmt->execute([
                $orderNumber,
                $name,
                $email,
                $phone,
                $address,
                $state,
                $finalTotals['delivery_fee'],
                $finalTotals['subtotal'],
                $finalTotals['total'],
                $payReference,
                $finalTotals['has_physical'] ? 1 : 0,
                $finalTotals['has_digital'] ? 1 : 0,
                $finalTotals['has_courses'] ? 1 : 0,
            ]);

            $orderId = $pdo->lastInsertId();

            // 2. Insert Order Items & Provision Academy LMS Enrolments
            $itemStmt = $pdo->prepare("INSERT INTO order_items (
                order_id, product_id, product_type, title, unit_price, quantity, total_price, course_id, digital_file_url, enrolment_status, vendor_id, vendor_earnings, platform_fee, fulfillment_status, variant_id, variant_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($cart as $item) {
                $enrolmentStatus = null;
                $courseId = null;
                $vendorId = null;
                $itemTotal = $item['price'] * $item['quantity'];
                $platformFee = 0;
                $vendorEarnings = 0;
                $variantId = $item['extra']['variant_id'] ?? null;
                $variantDetails = $item['extra']['variant_title'] ?? null;

                // Look up product vendor if applicable
                if ($item['type'] !== 'course' && !empty($item['id'])) {
                    $pCheck = $pdo->prepare("SELECT vendor_id FROM products WHERE id = ?");
                    $pCheck->execute([$item['id']]);
                    $vendorId = $pCheck->fetchColumn() ?: null;

                    if ($vendorId) {
                        // Standard 15% platform commission, 85% vendor earnings
                        $platformFee = round($itemTotal * 0.15, 2);
                        $vendorEarnings = $itemTotal - $platformFee;

                        // Credit vendor account balance
                        $updVen = $pdo->prepare("UPDATE vendors SET balance = balance + ? WHERE id = ?");
                        $updVen->execute([$vendorEarnings, $vendorId]);
                    }

                    // Decrement inventory stock
                    if ($item['type'] === 'physical') {
                        if ($variantId) {
                            $pdo->prepare("UPDATE product_variants SET stock_quantity = MAX(0, stock_quantity - ?) WHERE id = ?")
                                ->execute([$item['quantity'], $variantId]);
                        }
                        $pdo->prepare("UPDATE products SET stock_quantity = MAX(0, stock_quantity - ?) WHERE id = ?")
                            ->execute([$item['quantity'], $item['id']]);
                    }
                }

                // If course, immediately call Learnerium LMS API to provision student account
                if ($item['type'] === 'course') {
                    $courseId = $item['id'];
                    $provisionRes = zavora_call_lms_api('/enrolments/provision', 'POST', [
                        'student_email'     => $email,
                        'student_name'      => $name,
                        'course_id'         => $courseId,
                        'payment_reference' => $payReference,
                        'amount_paid'       => $item['price'],
                        'currency'          => 'NGN',
                        'metadata'          => [
                            'zavora_order_id' => $orderId,
                            'customer_phone'  => $phone,
                        ]
                    ]);

                    $enrolmentStatus = !empty($provisionRes['success']) ? 'provisioned' : 'pending_retry';
                }

                $digitalUrl = ($item['type'] === 'digital') ? zavora_asset('uploads/downloads/sample_asset.zip') : null;

                $itemStmt->execute([
                    $orderId,
                    ($item['type'] !== 'course') ? $item['id'] : null,
                    $item['type'],
                    $item['title'],
                    $item['price'],
                    $item['quantity'],
                    $itemTotal,
                    $courseId,
                    $digitalUrl,
                    $enrolmentStatus,
                    $vendorId,
                    $vendorEarnings,
                    $platformFee,
                    'pending',
                    $variantId,
                    $variantDetails
                ]);
            }

            // 3. Clear shopping cart
            zavora_clear_cart();

            // 4. Redirect to order confirmation page
            header('Location: ' . zavora_asset('order_confirmation.php?order=' . urlencode($orderNumber)));
            exit;

        } catch (\Throwable $e) {
            $error = 'Order creation failed: ' . $e->getMessage();
        }
    }
}

$title = "Secure Checkout";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-8 sm:py-12 max-w-5xl">
  
  <div class="mb-8 text-center sm:text-left">
    <div class="inline-flex items-center gap-1.5 text-xs font-bold text-[#16865B] bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full mb-2">
      <i class="fas fa-lock text-[10px]"></i>
      <span>Paystack Verified 256-Bit SSL Checkout</span>
    </div>
    <h1 class="text-3xl font-black font-display text-gray-900">Checkout</h1>
    <p class="text-sm text-gray-500 mt-1">Complete your order details below.</p>
  </div>

  <?php if ($error): ?>
    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
      <i class="fas fa-circle-exclamation text-base"></i>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" class="grid lg:grid-cols-12 gap-8 items-start">
    
    <!-- Left Form Details (7 Cols) -->
    <div class="lg:col-span-7 space-y-6">
      
      <!-- Step 1: Customer Contact -->
      <div class="bg-white rounded-2xl border border-gray-200/90 p-6 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 mb-1 flex items-center gap-2">
          <span class="w-6 h-6 rounded-full bg-[#1B2299] text-white flex items-center justify-center text-xs">1</span>
          <span>Customer & Account Contact</span>
        </h2>
        <p class="text-xs text-gray-500 mb-4 pl-8">
          Your receipts, digital files, and course enrolment access links will be sent here.
        </p>

        <div class="space-y-4 pl-8">
          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Full Name *</label>
            <input type="text" name="customer_name" required placeholder="Full Name"
                   value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"
                   class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]" />
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Email Address *</label>
            <input type="email" name="customer_email" required placeholder="name@domain.com"
                   value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>"
                   class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]" />
            <span class="text-[11px] text-gray-400 mt-1 block">
              If purchasing an online course, this email matches your Learnerium learning account.
            </span>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">Phone Number (WhatsApp preferred) *</label>
            <input type="tel" name="customer_phone" required placeholder="080XXXXXXXX or +234..."
                   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                   class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]" />
          </div>
        </div>
      </div>

      <!-- Step 2: Delivery Details (Conditioned on Physical Goods) -->
      <?php if ($totals['has_physical']): ?>
        <div class="bg-white rounded-2xl border border-gray-200/90 p-6 shadow-sm">
          <h2 class="text-base font-bold text-gray-900 mb-1 flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-[#1B2299] text-white flex items-center justify-center text-xs">2</span>
            <span>Delivery Destination (Physical Goods)</span>
          </h2>
          <p class="text-xs text-gray-500 mb-4 pl-8">
            Required because your cart contains physical merchandise.
          </p>

          <div class="space-y-4 pl-8">
            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">State *</label>
              <select name="delivery_state" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
                <option value="">Select State</option>
                <option value="Lagos">Lagos (₦2,500 delivery)</option>
                <option value="Abuja">Abuja FCT (₦2,500 delivery)</option>
                <option value="Kaduna">Kaduna (₦2,500 delivery)</option>
                <option value="Rivers">Rivers / Port Harcourt (₦4,500 delivery)</option>
                <option value="Oyo">Oyo / Ibadan (₦4,500 delivery)</option>
                <option value="Kano">Kano (₦4,500 delivery)</option>
                <option value="Other">Other States Nationwide (₦4,500 delivery)</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-gray-700 mb-1">Full Street Address *</label>
              <textarea name="delivery_address" rows="2" required placeholder="House number, Street name, City, Landmark"
                        class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]"><?php echo htmlspecialchars($_POST['delivery_address'] ?? ''); ?></textarea>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-3">
          <i class="fas fa-check-circle text-lg text-[#16865B]"></i>
          <div>
            <span class="font-bold block">No Physical Shipping Required</span>
            <span>Your cart consists solely of digital assets and online courses. Access is granted instantly upon payment!</span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Step 3: Payment Method -->
      <div class="bg-white rounded-2xl border border-gray-200/90 p-6 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 mb-3 flex items-center gap-2">
          <span class="w-6 h-6 rounded-full bg-[#1B2299] text-white flex items-center justify-center text-xs">
            <?php echo $totals['has_physical'] ? '3' : '2'; ?>
          </span>
          <span>Payment Gateway</span>
        </h2>

        <div class="p-4 rounded-xl border-2 border-[#1B2299] bg-blue-50/40 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-lg bg-[#1B2299] text-white flex items-center justify-center font-bold text-sm">
              <i class="fas fa-credit-card"></i>
            </span>
            <div>
              <span class="text-xs font-bold text-gray-900 block">Paystack Verified Gateway</span>
              <span class="text-[11px] text-gray-500">Pay with Card, Bank Transfer, USSD, or Apple Pay</span>
            </div>
          </div>
          <span class="text-[10px] font-black uppercase text-[#1B2299] bg-white px-2.5 py-1 rounded-full border border-blue-200">
            Recommended
          </span>
        </div>
      </div>

    </div>

    <!-- Right Order Summary (5 Cols) -->
    <div class="lg:col-span-5 bg-white rounded-2xl border border-gray-200/90 p-6 shadow-sm sticky top-24">
      <h3 class="font-bold text-base text-gray-900 mb-4 pb-3 border-b border-gray-100">Order Items (<?php echo count($cart); ?>)</h3>

      <div class="divide-y divide-gray-100 max-h-60 overflow-y-auto mb-6 pr-2">
        <?php foreach ($cart as $item): ?>
          <div class="py-3 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2.5 min-w-0 pr-2">
              <span class="font-bold text-gray-400"><?php echo $item['quantity']; ?>x</span>
              <span class="text-gray-800 font-semibold truncate"><?php echo htmlspecialchars($item['title']); ?></span>
            </div>
            <span class="font-black text-gray-900 flex-shrink-0">
              <?php echo zavora_format_price($item['price'] * $item['quantity']); ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="space-y-2.5 text-xs text-gray-600 pb-4 border-b border-gray-100 mb-4">
        <div class="flex justify-between">
          <span>Subtotal</span>
          <span class="font-bold text-gray-900"><?php echo zavora_format_price($totals['subtotal']); ?></span>
        </div>
        <div class="flex justify-between">
          <span>Delivery</span>
          <span class="font-bold text-gray-900">
            <?php echo $totals['has_physical'] ? zavora_format_price($totals['delivery_fee']) : 'FREE (₦0.00)'; ?>
          </span>
        </div>
      </div>

      <div class="flex justify-between items-baseline mb-6">
        <span class="text-sm font-bold text-gray-900">Total Payable</span>
        <span class="text-2xl font-black text-[#1B2299]"><?php echo zavora_format_price($totals['total']); ?></span>
      </div>

      <button type="submit" name="pay_mode" value="instant" id="instantSubmitBtn" class="w-full py-4 bg-gradient-to-r from-[#1B2299] via-[#0B132B] to-[#E4306D] text-white font-black text-sm rounded-xl shadow-xl hover:scale-105 transition-all flex items-center justify-center gap-2 mb-2">
        <i class="fas fa-lock text-xs"></i>
        <span>Complete Payment &bull; <?php echo zavora_format_price($totals['total']); ?></span>
      </button>

      <button type="button" onclick="launchPaystackPopup()" class="w-full py-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold text-xs rounded-xl border border-gray-200 transition-all flex items-center justify-center gap-2">
        <i class="fas fa-credit-card text-[#1B2299]"></i>
        <span>Pay with Paystack Modal</span>
      </button>

      <p class="text-[11px] text-gray-400 text-center mt-4">
        By clicking Complete Payment, you agree to ZAVORA's Terms of Service and digital access policies.
      </p>
    </div>

  </form>
</div>

<!-- Paystack Inline JS SDK -->
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function launchPaystackPopup() {
  const nameInput = document.querySelector('input[name="customer_name"]');
  const emailInput = document.querySelector('input[name="customer_email"]');
  const phoneInput = document.querySelector('input[name="customer_phone"]');

  if (!emailInput.value || !emailInput.checkValidity()) {
    alert('Please enter a valid email address first.');
    emailInput.focus();
    return;
  }
  if (!nameInput.value) {
    alert('Please enter your full name first.');
    nameInput.focus();
    return;
  }

  const handler = PaystackPop.setup({
    key: '<?php echo htmlspecialchars(PAYSTACK_PUBLIC_KEY); ?>',
    email: emailInput.value,
    amount: <?php echo round($totals['total'] * 100); ?>,
    currency: 'NGN',
    ref: 'PSK-ZAV-' + Math.floor((Math.random() * 1000000000) + 1),
    metadata: {
      custom_fields: [
        { display_name: "Customer Name", variable_name: "customer_name", value: nameInput.value },
        { display_name: "Phone Number", variable_name: "customer_phone", value: phoneInput.value }
      ]
    },
    callback: function(response) {
      // Payment successful, submit form with response reference
      const form = document.querySelector('form');
      const refInput = document.createElement('input');
      refInput.type = 'hidden';
      refInput.name = 'paystack_reference';
      refInput.value = response.reference;
      form.appendChild(refInput);
      form.submit();
    },
    onClose: function() {
      // User cancelled
    }
  });

  handler.openIframe();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
