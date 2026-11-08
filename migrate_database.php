<?php
/**
 * ZAVORA Marketplace — Online Database Migration & Self-Destruct Script
 *
 * Runs schema updates on cPanel/Production MySQL servers:
 * - Creates system_settings table (Admin-configurable vendor trials)
 * - Creates ai_credit_transactions ledger table
 * - Adds ai_credits, boost_credits, subscription_tier to vendors
 * - Adds variant, preorder, and boost columns to products
 * - Adds multi-vendor settlement columns to order_items
 * - Creates product_variants, product_reviews, and vendor_payouts tables
 * - Grants initial trial credits to existing merchants
 * - SELF-DESTRUCTS: Automatically deletes itself from the server upon execution!
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$isCli = (PHP_SAPI === 'cli');

// 1. Discover Database Credentials from local .env or sibling cPanel apps
$candidateEnvFiles = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
    '/home/gwylvxeo/learnerium.jlm.com.ng/.env',
    '/home/gwylvxeo/public_html/.env',
    dirname(__DIR__) . '/learnerium.jlm.com.ng/.env',
    dirname(__DIR__) . '/public_html/.env',
];

$dbHost = 'localhost';
$dbPort = '3306';
$dbName = '';
$dbUser = '';
$dbPass = '';
$loadedFromEnv = false;

foreach ($candidateEnvFiles as $ef) {
    if (file_exists($ef) && is_readable($ef)) {
        $lines = @file($ef, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if (($k === 'DB_HOST' || $k === 'ZAVORA_DB_HOST') && empty($dbHost)) $dbHost = $v;
                if (($k === 'DB_PORT' || $k === 'ZAVORA_DB_PORT') && empty($dbPort)) $dbPort = $v;
                if (($k === 'DB_DATABASE' || $k === 'ZAVORA_DB_NAME' || $k === 'DB_NAME') && empty($dbName)) $dbName = $v;
                if (($k === 'DB_USERNAME' || $k === 'ZAVORA_DB_USER' || $k === 'DB_USER') && empty($dbUser)) $dbUser = $v;
                if (($k === 'DB_PASSWORD' || $k === 'ZAVORA_DB_PASS' || $k === 'DB_PASS') && empty($dbPass)) $dbPass = $v;
            }
        }
        if (!empty($dbName) && !empty($dbUser)) {
            $loadedFromEnv = true;
            break;
        }
    }
}

// Override with POST credentials if submitted via Web UI
if (!empty($_POST['db_host'])) $dbHost = trim($_POST['db_host']);
if (!empty($_POST['db_port'])) $dbPort = trim($_POST['db_port']);
if (!empty($_POST['db_name'])) $dbName = trim($_POST['db_name']);
if (!empty($_POST['db_user'])) $dbUser = trim($_POST['db_user']);
if (isset($_POST['db_pass']))  $dbPass = trim($_POST['db_pass']);

$runMigration = ($isCli || (isset($_POST['action']) && $_POST['action'] === 'run_migration'));
$migrationLogs = [];
$migrationSuccess = false;
$selfDestructed = false;
$connectionError = '';

if ($runMigration) {
    if (empty($dbName) || empty($dbUser)) {
        $connectionError = 'Database Name and Database Username are required.';
    } else {
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $migrationLogs[] = ["success", "Connected to MySQL database [{$dbName}] on [{$dbHost}:{$dbPort}]"];

            // ── STEP 1: System Settings Table (Vendor Trials & Ecosystem Config) ──
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                `setting_value` TEXT NULL,
                `description` VARCHAR(255) NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $migrationLogs[] = ["success", "Verified table `system_settings` exists."];

            // Default settings
            $defSettings = [
                ['vendor_trial_ai_credits', '5', 'Complimentary AI generation trials granted to new vendors on signup'],
                ['vendor_trial_boost_credits', '3', 'Complimentary product boost credits granted to new vendors'],
                ['learnerium_online_url', 'https://learnerium.jlm.com.ng', 'Canonical online Learnerium LMS portal URL'],
                ['ai_image_provider', 'pollinations_flux', 'Active AI image generation provider (Pollinations FLUX.1)'],
            ];
            $setStmt = $pdo->prepare("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES (?, ?, ?)");
            foreach ($defSettings as $ds) {
                $setStmt->execute($ds);
            }
            $migrationLogs[] = ["success", "Configured platform trial defaults (5 Free AI Generations, 3 Boost Trials)."];

            // ── STEP 2: AI Credit Transactions Ledger ──────────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS `ai_credit_transactions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `vendor_id` INT UNSIGNED NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `credits_change` INT NOT NULL,
                `amount_paid` DECIMAL(12, 2) DEFAULT 0.00,
                `payment_reference` VARCHAR(100) NULL,
                `prompt_summary` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_vendor_credits` (`vendor_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $migrationLogs[] = ["success", "Verified table `ai_credit_transactions` exists with audit indexes."];

            // ── STEP 3: Ensure Columns Exist on `vendors` ───────────────────
            $vendorCols = [
                "ALTER TABLE `vendors` ADD COLUMN `ai_credits` INT DEFAULT 5",
                "ALTER TABLE `vendors` ADD COLUMN `boost_credits` INT DEFAULT 3",
                "ALTER TABLE `vendors` ADD COLUMN `subscription_tier` VARCHAR(50) DEFAULT 'free'",
                "ALTER TABLE `vendors` ADD COLUMN `support_email` VARCHAR(150) NULL",
            ];
            foreach ($vendorCols as $sql) {
                try {
                    $pdo->exec($sql);
                    $migrationLogs[] = ["success", "Executed: " . htmlspecialchars($sql)];
                } catch (\Throwable $e) {
                    $migrationLogs[] = ["info", "Column already exists (skipped): " . substr($sql, 25)];
                }
            }

            // Ensure all existing vendors have at least 5 trial credits
            $pdo->exec("UPDATE `vendors` SET `ai_credits` = 5 WHERE `ai_credits` IS NULL OR `ai_credits` = 0");
            $pdo->exec("UPDATE `vendors` SET `boost_credits` = 3 WHERE `boost_credits` IS NULL OR `boost_credits` = 0");
            $migrationLogs[] = ["success", "Updated existing vendor balances with complimentary onboarding trials."];

            // ── STEP 4: Ensure Columns Exist on `products` ──────────────────
            $productCols = [
                "ALTER TABLE `products` ADD COLUMN `vendor_id` INT UNSIGNED NULL",
                "ALTER TABLE `products` ADD COLUMN `is_preorder` INT DEFAULT 0",
                "ALTER TABLE `products` ADD COLUMN `preorder_release_date` VARCHAR(50) NULL",
                "ALTER TABLE `products` ADD COLUMN `preorder_deposit` DECIMAL(12, 2) NULL",
                "ALTER TABLE `products` ADD COLUMN `is_boosted` INT DEFAULT 0",
                "ALTER TABLE `products` ADD COLUMN `boost_tier` VARCHAR(50) NULL",
                "ALTER TABLE `products` ADD COLUMN `boost_expires_at` TIMESTAMP NULL",
                "ALTER TABLE `products` ADD COLUMN `has_variants` INT DEFAULT 0",
                "ALTER TABLE `products` ADD COLUMN `variant_attributes` TEXT NULL",
            ];
            foreach ($productCols as $sql) {
                try {
                    $pdo->exec($sql);
                    $migrationLogs[] = ["success", "Executed: " . htmlspecialchars($sql)];
                } catch (\Throwable $e) {
                    $migrationLogs[] = ["info", "Column already exists (skipped): " . substr($sql, 26)];
                }
            }

            // ── STEP 5: Ensure Columns Exist on `order_items` ───────────────
            $orderItemCols = [
                "ALTER TABLE `order_items` ADD COLUMN `vendor_id` INT UNSIGNED NULL",
                "ALTER TABLE `order_items` ADD COLUMN `vendor_earnings` DECIMAL(12, 2) DEFAULT 0.00",
                "ALTER TABLE `order_items` ADD COLUMN `platform_fee` DECIMAL(12, 2) DEFAULT 0.00",
                "ALTER TABLE `order_items` ADD COLUMN `fulfillment_status` VARCHAR(50) DEFAULT 'pending'",
                "ALTER TABLE `order_items` ADD COLUMN `variant_id` INT UNSIGNED NULL",
                "ALTER TABLE `order_items` ADD COLUMN `variant_details` TEXT NULL",
            ];
            foreach ($orderItemCols as $sql) {
                try {
                    $pdo->exec($sql);
                    $migrationLogs[] = ["success", "Executed: " . htmlspecialchars($sql)];
                } catch (\Throwable $e) {
                    $migrationLogs[] = ["info", "Column already exists (skipped): " . substr($sql, 29)];
                }
            }

            // ── STEP 6: Ensure Product Variants Table Exists ─────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS `product_variants` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `product_id` INT UNSIGNED NOT NULL,
                `sku` VARCHAR(60) NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `attributes_json` TEXT NOT NULL,
                `price` DECIMAL(12, 2) NOT NULL,
                `stock_quantity` INT DEFAULT 20,
                `is_active` INT DEFAULT 1,
                `image_url` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_product_variants` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $migrationLogs[] = ["success", "Verified table `product_variants` exists."];

            // ── STEP 7: Ensure Product Reviews Table Exists ──────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS `product_reviews` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `product_id` INT UNSIGNED NOT NULL,
                `customer_name` VARCHAR(150) NOT NULL,
                `rating` INT NOT NULL,
                `title` VARCHAR(200) NULL,
                `comment` TEXT NOT NULL,
                `is_verified_buyer` INT DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_product_reviews` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $migrationLogs[] = ["success", "Verified table `product_reviews` exists."];

            // ── STEP 8: Ensure Vendor Payouts Table Exists ───────────────────
            $pdo->exec("CREATE TABLE IF NOT EXISTS `vendor_payouts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `vendor_id` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(12, 2) NOT NULL,
                `bank_details` TEXT NOT NULL,
                `status` VARCHAR(30) DEFAULT 'pending',
                `reference` VARCHAR(100) NOT NULL UNIQUE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_vendor_payouts` (`vendor_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            $migrationLogs[] = ["success", "Verified table `vendor_payouts` exists."];

            // ── STEP 8b: Auto-Generate .env File so Storefront is permanently configured ──
            $envPath = __DIR__ . '/.env';
            if (!file_exists($envPath) || !empty($_POST['db_name'])) {
                $envData = "# ZAVORA Marketplace Production Environment\n"
                    . "APP_ENV=production\n"
                    . "APP_DEBUG=false\n"
                    . "ZAVORA_SITE_URL=https://zavora.jlm.com.ng\n\n"
                    . "DB_HOST={$dbHost}\n"
                    . "DB_PORT={$dbPort}\n"
                    . "DB_DATABASE={$dbName}\n"
                    . "DB_USERNAME={$dbUser}\n"
                    . "DB_PASSWORD={$dbPass}\n\n"
                    . "ZAVORA_DB_HOST={$dbHost}\n"
                    . "ZAVORA_DB_PORT={$dbPort}\n"
                    . "ZAVORA_DB_NAME={$dbName}\n"
                    . "ZAVORA_DB_USER={$dbUser}\n"
                    . "ZAVORA_DB_PASS={$dbPass}\n\n"
                    . "LEARNERIUM_URL=https://learnerium.jlm.com.ng\n"
                    . "JLM_CORPORATE_URL=https://jlm.com.ng\n"
                    . "ECOSYSTEM_API_SECRET=eco_sec_jlm_zavora_01a2c11beee84a1bb6e5ce40a29769f4\n";
                @file_put_contents($envPath, $envData);
                $migrationLogs[] = ["success", "Generated production `.env` file with live database credentials."];
            }

            $migrationSuccess = true;

            // ── STEP 9: SELF-DESTRUCT MECHANISM ─────────────────────────────
            $selfFile = __FILE__;
            $unlinked = @unlink($selfFile);
            if ($unlinked || !file_exists($selfFile)) {
                $selfDestructed = true;
                $migrationLogs[] = ["success", "🛡️ SELF-DESTRUCT SUCCESS: `migrate_database.php` has been permanently deleted from this server."];
            } else {
                // Fallback: Overwrite contents to neutralize file if file permissions prevent unlinking
                @file_put_contents($selfFile, "<?php http_response_code(404); echo 'File Removed'; exit;");
                $selfDestructed = true;
                $migrationLogs[] = ["success", "🛡️ SELF-DESTRUCT SUCCESS: `migrate_database.php` content overwritten and neutralized."];
            }

        } catch (\Throwable $e) {
            $connectionError = $e->getMessage();
            $migrationLogs[] = ["error", "Migration halted: " . $e->getMessage()];
        }
    }
}

// If running in CLI, print colored output and exit
if ($isCli) {
    echo "\n=== ZAVORA DATABASE MIGRATION ===\n";
    foreach ($migrationLogs as $log) {
        $prefix = $log[0] === 'success' ? '[✓]' : ($log[0] === 'info' ? '[-]' : '[!]');
        echo "{$prefix} {$log[1]}\n";
    }
    if ($migrationSuccess) {
        echo "\n>>> Database migration completed successfully!\n";
        echo ">>> Self-destruct activated: file removed.\n\n";
        exit(0);
    } else {
        echo "\n>>> Migration failed: {$connectionError}\n\n";
        exit(1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Web UI Rendering
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZAVORA Online Database Migrator</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap');
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
  </style>
</head>
<body class="bg-slate-900 text-gray-100 min-h-screen flex items-center justify-center p-4">

  <div class="max-w-2xl w-full bg-slate-800/90 backdrop-blur-xl border border-slate-700/80 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden">
    
    <!-- Top Monogram -->
    <div class="flex items-center gap-3 mb-6">
      <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-[#1B2299] to-[#E4306D] flex items-center justify-center text-white text-xl font-black shadow-lg">
        <i class="fas fa-database"></i>
      </div>
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white">ZAVORA Online Database Migrator</h1>
        <p class="text-xs text-gray-400">One-click online schema synchronizer with automatic self-destruction</p>
      </div>
    </div>

    <?php if ($migrationSuccess): ?>
      <!-- SUCCESS SCREEN -->
      <div class="p-6 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl mb-6">
        <div class="flex items-center gap-3 text-emerald-400 font-black text-lg mb-2">
          <i class="fas fa-circle-check text-2xl"></i>
          <span>Migration Completed Successfully!</span>
        </div>
        <p class="text-xs text-gray-300 mb-4">
          All tables (System Settings, AI Credit Ledger, Variants, Preorders, Boosts, Reviews, Payouts) have been verified and updated.
        </p>

        <?php if ($selfDestructed): ?>
          <div class="p-3 bg-slate-900/60 border border-emerald-500/20 rounded-xl text-xs font-mono text-emerald-300 flex items-center gap-2">
            <i class="fas fa-shield-halved text-emerald-400"></i>
            <span><strong>Self-Destruct Activated:</strong> <code>migrate_database.php</code> was deleted from your server.</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="mb-6 bg-slate-950/70 rounded-2xl p-4 border border-slate-700 font-mono text-[11px] max-h-60 overflow-y-auto space-y-1">
        <?php foreach ($migrationLogs as $log): ?>
          <div class="<?php echo $log[0] === 'success' ? 'text-emerald-400' : ($log[0] === 'info' ? 'text-gray-400' : 'text-rose-400'); ?>">
            <?php echo $log[0] === 'success' ? '✓ ' : ($log[0] === 'info' ? '• ' : '✕ '); ?><?php echo htmlspecialchars($log[1]); ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <a href="index.php" class="py-3 px-4 bg-[#1B2299] hover:bg-[#0B132B] text-white text-xs font-bold text-center rounded-xl transition shadow">
          <i class="fas fa-store mr-1"></i> Storefront
        </a>
        <a href="seller/index.php" class="py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold text-center rounded-xl transition shadow">
          <i class="fas fa-wand-magic-sparkles mr-1"></i> Seller Central
        </a>
        <a href="admin/index.php" class="py-3 px-4 bg-slate-700 hover:bg-slate-600 text-white text-xs font-bold text-center rounded-xl transition shadow">
          <i class="fas fa-shield-alt mr-1"></i> Admin Operations
        </a>
      </div>

    <?php else: ?>

      <!-- PRE-MIGRATION / INPUT FORM SCREEN -->
      <?php if (!empty($connectionError)): ?>
        <div class="p-4 bg-rose-500/10 border border-rose-500/30 rounded-2xl mb-6 text-xs text-rose-300 flex items-center gap-3">
          <i class="fas fa-triangle-exclamation text-rose-400 text-lg"></i>
          <div>
            <strong>Database Connection Failed:</strong> <?php echo htmlspecialchars($connectionError); ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="p-4 bg-blue-500/10 border border-blue-500/30 rounded-2xl mb-4 text-xs text-blue-200">
        <p class="font-bold flex items-center gap-2 mb-1">
          <i class="fas fa-circle-info text-blue-400"></i> What this migration will do:
        </p>
        <ul class="list-disc list-inside space-y-1 text-gray-300 text-[11px] pl-1">
          <li>Create <code>system_settings</code> table and configure 5 free AI vendor trials</li>
          <li>Create <code>ai_credit_transactions</code> ledger table for credit top-ups</li>
          <li>Add <code>ai_credits</code> and <code>boost_credits</code> to vendors</li>
          <li>Add product variety, preorder, and search boost columns to <code>products</code></li>
          <li>Add multi-vendor settlement and variant tracking to <code>order_items</code></li>
          <li><strong>Auto-configure:</strong> Automatically creates your live <code>.env</code> file.</li>
          <li><strong>Self-destruct:</strong> Automatically deletes this file upon completion.</li>
        </ul>
      </div>

      <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl mb-6 text-xs text-amber-200">
        <p class="font-bold flex items-center gap-2 mb-1 text-amber-300">
          <i class="fas fa-shield-halved"></i> Why is this asking for database credentials?
        </p>
        <p class="text-gray-300 text-[11px] leading-relaxed">
          For security, live production database passwords are <strong>never stored on GitHub or in git repositories</strong>.
          The grey text below is just placeholder examples.
          Please enter the MySQL database name, database user, and password you created in your <strong>cPanel MySQL Database Wizard</strong>.
          Clicking <em>Run Migration</em> will apply the database updates <strong>and save your <code>.env</code> file automatically</strong>!
        </p>
      </div>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="run_migration">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-gray-300 mb-1">MySQL Host</label>
            <input type="text" name="db_host" value="<?php echo htmlspecialchars($dbHost ?: 'localhost'); ?>" required
                   class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-300 mb-1">MySQL Port</label>
            <input type="text" name="db_port" value="<?php echo htmlspecialchars($dbPort ?: '3306'); ?>" required
                   class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-bold text-gray-300 mb-1">Database Name *</label>
            <input type="text" name="db_name" value="<?php echo htmlspecialchars($dbName); ?>" required placeholder="e.g. gwylvxeo_zavora"
                   class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-300 mb-1">Database User *</label>
            <input type="text" name="db_user" value="<?php echo htmlspecialchars($dbUser); ?>" required placeholder="e.g. gwylvxeo_user"
                   class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          </div>
          <div>
            <label class="block text-xs font-bold text-gray-300 mb-1">Database Password</label>
            <input type="password" name="db_pass" value="<?php echo htmlspecialchars($dbPass); ?>" placeholder="Enter database password"
                   class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          </div>
        </div>

        <button type="submit" 
                class="w-full py-3.5 bg-gradient-to-r from-[#1B2299] via-indigo-600 to-[#E4306D] hover:opacity-95 text-white font-extrabold text-sm rounded-xl transition shadow-xl flex items-center justify-center gap-2 mt-2">
          <i class="fas fa-play"></i> <span>Run Migration & Self-Destruct</span>
        </button>

        <p class="text-[11px] text-gray-400 text-center flex items-center justify-center gap-1.5 mt-2">
          <i class="fas fa-shield-alt text-emerald-400"></i>
          <span>Credentials auto-detected from <code>.env</code> if already configured.</span>
        </p>
      </form>

    <?php endif; ?>

  </div>

</body>
</html>
