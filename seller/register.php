<?php
require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();
$error = '';
$success = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $bankName = trim($_POST['bank_name'] ?? '');
    $accountNumber = trim($_POST['account_number'] ?? '');
    $accountName = trim($_POST['account_name'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($storeName) || empty($ownerName) || empty($email) || empty($password)) {
        $error = 'Please fill out all required merchant fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid business email address.';
    } else {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $storeName))) . '-' . rand(100, 999);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $trialAiCredits = intval(zavora_get_setting('vendor_trial_ai_credits', 5));
            $trialBoostCredits = intval(zavora_get_setting('vendor_trial_boost_credits', 3));

            $stmt = $pdo->prepare("
                INSERT INTO vendors (
                    store_name, slug, owner_name, email, phone, password_hash, 
                    bank_name, account_number, account_name, commission_rate, balance, status,
                    ai_credits, boost_credits, subscription_tier
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 15.00, 0.00, 'approved', ?, ?, 'free')
            ");

            $stmt->execute([
                $storeName, $slug, $ownerName, $email, $phone, $hash, 
                $bankName, $accountNumber, $accountName, $trialAiCredits, $trialBoostCredits
            ]);

            $vendorId = $pdo->lastInsertId();
            $_SESSION['zavora_vendor_id'] = $vendorId;
            $_SESSION['zavora_vendor_name'] = $storeName;

            // Log complimentary onboarding trial transaction
            try {
                $pdo->prepare("INSERT INTO ai_credit_transactions (vendor_id, action_type, credits_change, amount_paid, payment_reference, prompt_summary) VALUES (?, 'signup_trial', ?, 0, ?, ?)")
                    ->execute([$vendorId, $trialAiCredits, 'TRIAL-' . strtoupper(substr(uniqid(), -6)), "Complimentary welcome trial ({$trialAiCredits} AI generation credits granted)"]);
            } catch (\Throwable $e) {}

            header('Location: ' . zavora_asset('seller/index.php?registered=1'));
            exit;

        } catch (\Throwable $e) {
            $error = 'Store registration failed: ' . $e->getMessage();
        }
    }
}

$title = 'Open a Seller Store on ZAVORA';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 lg:px-8 py-10 max-w-xl">
  
  <div class="bg-white rounded-3xl p-8 border border-gray-200 shadow-sm">
    <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
      <div class="w-12 h-12 bg-amber-50 text-amber-700 rounded-2xl flex items-center justify-center text-xl">
        <i class="fas fa-shop"></i>
      </div>
      <div>
        <h1 class="text-2xl font-black text-[#0B132B] font-display">Open Your ZAVORA Store</h1>
        <p class="text-xs text-gray-500">Sell physical products, digital assets, templates, or masterclasses to thousands of buyers nationwide.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs font-bold flex items-center gap-2">
        <i class="fas fa-circle-exclamation text-rose-600"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Store / Brand Name *</label>
        <input type="text" name="store_name" required placeholder="Store or Business Name"
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Owner Full Name *</label>
          <input type="text" name="owner_name" required placeholder="Full Name"
                 class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Phone Number *</label>
          <input type="tel" name="phone" required placeholder="080XXXXXXXX"
                 class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Business Email *</label>
          <input type="email" name="email" required placeholder="Email address"
                 class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">Account Password *</label>
          <input type="password" name="password" required placeholder="Create password"
                 class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:ring-2 focus:ring-[#1B2299] focus:outline-none">
        </div>
      </div>

      <!-- Bank Details for Paystack Payouts -->
      <div class="pt-4 border-t border-gray-100">
        <span class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3 block flex items-center gap-1.5">
          <i class="fas fa-building-columns text-[#1B2299]"></i> Settlement Bank Account (for Paystack Payouts)
        </span>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">Select Bank *</label>
            <select name="bank_name" required class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-semibold focus:bg-white focus:outline-none">
              <option value="">Choose Bank...</option>
              <?php foreach ($nigerianBanks as $bnk): ?>
                <option value="<?php echo htmlspecialchars($bnk); ?>"><?php echo htmlspecialchars($bnk); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">Account Number *</label>
            <input type="text" name="account_number" required placeholder="10-digit NUBAN" maxlength="10"
                   class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-mono font-bold focus:bg-white focus:outline-none">
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-500 mb-1">Account Name *</label>
            <input type="text" name="account_name" required placeholder="Name on Bank Account"
                   class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none">
          </div>
        </div>
      </div>

      <div class="p-3 bg-gray-50 rounded-2xl text-[11px] text-gray-600 border border-gray-100">
        <i class="fas fa-info-circle text-[#1B2299] mr-1"></i>
        <strong>Transparent Commission:</strong> You keep <strong>85%</strong> of every sale. ZAVORA takes a standard 15% marketplace commission covering secure Paystack transaction processing, fraud protection, and nationwide buyer traffic.
      </div>

      <button type="submit" class="w-full py-3.5 bg-[#1B2299] hover:bg-[#0B132B] text-white font-bold rounded-xl text-sm transition-all shadow-md mt-4">
        Register Store & Start Selling
      </button>
    </form>

    <div class="mt-6 text-center text-xs text-gray-500">
      Already have a store account? 
      <a href="<?php echo zavora_asset('seller/login.php'); ?>" class="text-[#1B2299] font-bold hover:underline">
        Sign in to Merchant Hub
      </a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>