<?php
require_once __DIR__ . '/config/config.php';

$pdo = zavora_get_db();
$orderNumber = trim($_GET['order'] ?? '');

$order = null;
$items = [];

if ($orderNumber) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$order['id']]);
        $items = $itemStmt->fetchAll();
    }
}

if (!$order) {
    header('Location: ' . zavora_asset('index.php'));
    exit;
}

$title = "Order Confirmed — " . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-10 sm:py-16 max-w-4xl">
  
  <!-- Success Badge Banner -->
  <div class="bg-white rounded-3xl border border-gray-200/90 p-8 sm:p-12 text-center shadow-sm mb-8">
    <div class="w-20 h-20 rounded-full bg-emerald-50 text-[#16865B] border border-emerald-200 flex items-center justify-center text-3xl mx-auto mb-4 animate-bounce">
      <i class="fas fa-check"></i>
    </div>
    
    <span class="text-xs font-bold uppercase tracking-wider text-[#16865B] block mb-1">Payment Verified Successfully</span>
    <h1 class="text-2xl sm:text-4xl font-black font-display text-gray-900 mb-2">Thank you for your order!</h1>
    <p class="text-sm text-gray-500 max-w-md mx-auto">
      An official receipt and access instructions have been sent to <span class="font-bold text-gray-800"><?php echo htmlspecialchars($order['customer_email']); ?></span>.
    </p>

    <div class="mt-6 inline-flex flex-wrap items-center justify-center gap-3 text-xs">
      <span class="px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 font-mono text-gray-800">
        Order ID: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
      </span>
      <span class="px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-[#16865B] font-bold">
        Status: PAID (Paystack)
      </span>
    </div>
  </div>

  <!-- Special Access Callout: Academy Courses -->
  <?php if ($order['has_courses']): ?>
    <div class="mb-8 p-6 sm:p-8 rounded-3xl bg-gradient-to-br from-[#0B132B] via-[#1B2299] to-[#0B132B] text-white shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-[#F7DE7A] text-[#0B132B] flex items-center justify-center text-2xl flex-shrink-0 font-bold shadow-lg">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#F7DE7A] block mb-0.5">Learnerium LMS Provisioned</span>
          <h3 class="text-xl font-bold font-display text-white">Your Course Access is Ready!</h3>
          <p class="text-xs text-gray-300 mt-1">
            Your student account is active. Click below to begin watching lessons, taking quizzes, and earning certificates.
          </p>
        </div>
      </div>
      <a href="<?php echo htmlspecialchars(LEARNERIUM_URL . '/login/student'); ?>" target="_blank" rel="noopener"
         class="w-full md:w-auto px-6 py-3.5 rounded-xl bg-[#E4306D] hover:bg-[#c2285a] text-white font-black text-xs uppercase tracking-wider shadow-lg transition-transform hover:scale-105 flex items-center justify-center gap-2 flex-shrink-0">
        <span>Start Learning on Learnerium</span>
        <i class="fas fa-arrow-up-right-from-square text-xs"></i>
      </a>
    </div>
  <?php endif; ?>

  <!-- Order Items & Fulfillment Breakdown -->
  <div class="bg-white rounded-3xl border border-gray-200/90 p-6 sm:p-8 shadow-sm mb-8">
    <h3 class="font-bold text-base text-gray-900 mb-6 pb-3 border-b border-gray-100 flex items-center justify-between">
      <span>Order Breakdown</span>
      <span class="text-xs text-gray-500 font-normal"><?php echo count($items); ?> items</span>
    </h3>

    <div class="divide-y divide-gray-100">
      <?php foreach ($items as $item): ?>
        <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <?php if ($item['product_type'] === 'course'): ?>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-[#87207C] border border-purple-200">
                  Academy Course
                </span>
              <?php elseif ($item['product_type'] === 'digital'): ?>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-pink-50 text-[#E4306D] border border-pink-200">
                  Digital Asset
                </span>
              <?php else: ?>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-[#1B2299] border border-blue-200">
                  Physical Goods
                </span>
              <?php endif; ?>
            </div>
            <h4 class="font-bold text-sm text-gray-900"><?php echo htmlspecialchars($item['title']); ?></h4>
            <span class="text-gray-500"><?php echo $item['quantity']; ?> unit(s) &bull; <?php echo zavora_format_price($item['unit_price']); ?> each</span>
          </div>

          <!-- Action / Fulfillment Status -->
          <div class="flex items-center gap-3">
            <span class="text-sm font-black text-gray-900"><?php echo zavora_format_price($item['total_price']); ?></span>

            <?php if ($item['product_type'] === 'digital'): ?>
              <button onclick="alert('Digital package download initiated for <?php echo htmlspecialchars($item['title']); ?>');" 
                      class="px-3 py-1.5 bg-[#0B132B] hover:bg-[#1B2299] text-white rounded-lg text-xs font-bold flex items-center gap-1.5 transition-colors">
                <i class="fas fa-download text-[10px]"></i>
                <span>Download</span>
              </button>
            <?php elseif ($item['product_type'] === 'course'): ?>
              <?php $ssoUrl = zavora_generate_lms_sso_url($order['customer_email'], $order['customer_name'], $item['course_id']); ?>
              <a href="<?php echo htmlspecialchars($ssoUrl); ?>" target="_blank"
                 class="px-3 py-1.5 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-sm">
                <i class="fas fa-play text-[10px]"></i>
                <span>Start Learning (SSO)</span>
              </a>
            <?php else: ?>
              <span class="px-2.5 py-1 bg-blue-50 text-[#1B2299] rounded-lg text-[11px] font-bold">
                Processing Dispatch
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Totals Summary -->
    <div class="mt-6 pt-6 border-t border-gray-100 flex flex-col items-end gap-1.5 text-xs text-gray-600">
      <div class="flex justify-between w-64">
        <span>Subtotal:</span>
        <span class="font-bold text-gray-900"><?php echo zavora_format_price($order['subtotal']); ?></span>
      </div>
      <div class="flex justify-between w-64">
        <span>Delivery Fee:</span>
        <span class="font-bold text-gray-900"><?php echo zavora_format_price($order['delivery_fee']); ?></span>
      </div>
      <div class="flex justify-between w-64 text-sm font-black text-gray-900 pt-2 border-t border-gray-200">
        <span>Total Paid:</span>
        <span class="text-base text-[#1B2299]"><?php echo zavora_format_price($order['total_amount']); ?></span>
      </div>
    </div>
  </div>

  <div class="flex flex-col sm:flex-row justify-center gap-4 text-center">
    <a href="<?php echo zavora_asset('index.php'); ?>" class="px-8 py-3 bg-[#1B2299] text-white text-xs font-bold rounded-xl hover:bg-[#0B132B] transition-colors shadow-md">
      Return to Marketplace
    </a>
    <button onclick="window.print();" class="px-6 py-3 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition-colors">
      <i class="fas fa-print mr-1"></i>Print Official Receipt
    </button>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
