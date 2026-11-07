<?php
/**
 * ZAVORA Marketplace — Database Connection & Auto-Migrator
 *
 * Connects to MySQL with automatic fallback to local SQLite for seamless local dev.
 */

function zavora_get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbHost = getenv('ZAVORA_DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('ZAVORA_DB_PORT') ?: 3306;
    $dbName = getenv('ZAVORA_DB_NAME') ?: 'zavora_db';
    $dbUser = getenv('ZAVORA_DB_USER') ?: 'root';
    $dbPass = getenv('ZAVORA_DB_PASS') !== false ? getenv('ZAVORA_DB_PASS') : 'root';
    $dbSocket = '/Applications/MAMP/tmp/mysql/mysql.sock';

    // Try MySQL connections
    $candidates = [
        "mysql:unix_socket={$dbSocket};dbname={$dbName};charset=utf8mb4",
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        "mysql:host=127.0.0.1;port=8889;dbname={$dbName};charset=utf8mb4",
    ];

    foreach ($candidates as $dsn) {
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            break;
        } catch (\Throwable $e) {
            continue;
        }
    }

    // Zero-config SQLite fallback for local development
    if (!$pdo) {
        $sqlitePath = __DIR__ . '/../database/zavora.sqlite';
        try {
            $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\Throwable $e) {
            die("Database Initialization Error: " . $e->getMessage());
        }
    }

    // Ensure schema tables exist
    zavora_ensure_schema($pdo);

    return $pdo;
}

/**
 * Automatically creates tables and seeds starter catalog if empty.
 */
function zavora_ensure_schema(PDO $pdo) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $autoInc = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
    $decType = $isSqlite ? 'REAL' : 'DECIMAL(12, 2)';
    $textType = 'TEXT';
    $timeType = $isSqlite ? 'DATETIME' : 'TIMESTAMP';

    // 1. Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id {$autoInc},
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description {$textType},
        icon VARCHAR(50),
        type VARCHAR(20) DEFAULT 'physical',
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Products (Physical & Digital)
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id {$autoInc},
        category_id INT UNSIGNED,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        sku VARCHAR(50),
        type VARCHAR(20) DEFAULT 'physical', -- physical | digital | course
        price {$decType} NOT NULL,
        previous_price {$decType} NULL,
        stock_quantity INT DEFAULT 100,
        thumbnail VARCHAR(255),
        description {$textType},
        specifications {$textType},
        file_path VARCHAR(255) NULL, -- For digital downloads
        course_ref_id INT NULL,      -- For LMS Academy linked courses
        is_featured INT DEFAULT 0,
        is_flash_deal INT DEFAULT 0,
        rating {$decType} DEFAULT 4.8,
        reviews_count INT DEFAULT 12,
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id {$autoInc},
        order_number VARCHAR(50) NOT NULL UNIQUE,
        customer_name VARCHAR(150) NOT NULL,
        customer_email VARCHAR(150) NOT NULL,
        customer_phone VARCHAR(50),
        delivery_address {$textType} NULL,
        delivery_state VARCHAR(100) NULL,
        delivery_fee {$decType} DEFAULT 0,
        subtotal {$decType} NOT NULL,
        total_amount {$decType} NOT NULL,
        payment_status VARCHAR(50) DEFAULT 'pending',
        payment_reference VARCHAR(150) NULL,
        payment_method VARCHAR(50) DEFAULT 'paystack',
        has_physical_items INT DEFAULT 0,
        has_digital_items INT DEFAULT 0,
        has_courses INT DEFAULT 0,
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Order Items
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id {$autoInc},
        order_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NULL,
        product_type VARCHAR(20) DEFAULT 'physical',
        title VARCHAR(255) NOT NULL,
        unit_price {$decType} NOT NULL,
        quantity INT DEFAULT 1,
        total_price {$decType} NOT NULL,
        course_id INT NULL,
        digital_file_url VARCHAR(255) NULL,
        enrolment_status VARCHAR(50) NULL,
        vendor_id INT UNSIGNED NULL,
        vendor_earnings {$decType} DEFAULT 0,
        platform_fee {$decType} DEFAULT 0,
        fulfillment_status VARCHAR(50) DEFAULT 'pending'
    )");

    // 5. Vendors (Multi-Vendor Marketplace Architecture)
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendors (
        id {$autoInc},
        store_name VARCHAR(150) NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        owner_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(50) NULL,
        password_hash VARCHAR(255) NOT NULL,
        logo_url VARCHAR(255) NULL,
        banner_url VARCHAR(255) NULL,
        description {$textType} NULL,
        bank_name VARCHAR(100) NULL,
        account_number VARCHAR(30) NULL,
        account_name VARCHAR(150) NULL,
        commission_rate {$decType} DEFAULT 15.00,
        balance {$decType} DEFAULT 0.00,
        status VARCHAR(20) DEFAULT 'approved',
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Vendor Payouts
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendor_payouts (
        id {$autoInc},
        vendor_id INT UNSIGNED NOT NULL,
        amount {$decType} NOT NULL,
        bank_details {$textType} NOT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        reference VARCHAR(100) NOT NULL UNIQUE,
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 7. Product Variants Table (Colors, Sizes, Specs, RAM/Storage)
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
        id {$autoInc},
        product_id INT UNSIGNED NOT NULL,
        sku VARCHAR(60) NOT NULL,
        title VARCHAR(150) NOT NULL,
        attributes_json {$textType} NOT NULL, -- JSON: {\"Color\":\"Space Black\",\"Storage\":\"256GB\"}
        price {$decType} NOT NULL,
        stock_quantity INT DEFAULT 20,
        is_active INT DEFAULT 1,
        image_url VARCHAR(255) NULL,
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 8. Product Reviews Table (100% Database-Driven Authentic Feedback)
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
        id {$autoInc},
        product_id INT UNSIGNED NOT NULL,
        customer_name VARCHAR(150) NOT NULL,
        rating INT NOT NULL,
        title VARCHAR(200) NULL,
        comment {$textType} NOT NULL,
        is_verified_buyer INT DEFAULT 1,
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 9. Vendor AI Credit Transactions & Purchase Ledger (Paid Feature)
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_credit_transactions (
        id {$autoInc},
        vendor_id INT UNSIGNED NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        credits_change INT NOT NULL,
        amount_paid {$decType} DEFAULT 0,
        payment_reference VARCHAR(100) NULL,
        prompt_summary {$textType} NULL,
        created_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // 10. System Settings Table (Admin-configurable platform parameters)
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id {$autoInc},
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value {$textType} NULL,
        description VARCHAR(255) NULL,
        updated_at {$timeType} DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default system settings if empty
    try {
        $sCount = $pdo->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
        if ($sCount == 0) {
            $defSettings = [
                ['vendor_trial_ai_credits', '5', 'Complimentary AI credits (trials) granted to new vendors on signup'],
                ['vendor_trial_boost_credits', '3', 'Complimentary product boost credits granted to new vendors'],
                ['learnerium_online_url', 'https://learnerium.jlm.com.ng', 'Canonical online Learnerium LMS portal URL'],
                ['ai_image_provider', 'pollinations_flux', 'Active AI image generation provider (pollinations_flux)'],
            ];
            $sStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            foreach ($defSettings as $ds) {
                try { $sStmt->execute($ds); } catch (\Throwable $e) {}
            }
        }
    } catch (\Throwable $e) {}

    // Ensure variety, preorder, and boost columns exist on products
    $productCols = [
        "ALTER TABLE products ADD COLUMN vendor_id INT UNSIGNED NULL",
        "ALTER TABLE products ADD COLUMN is_preorder INT DEFAULT 0",
        "ALTER TABLE products ADD COLUMN preorder_release_date VARCHAR(50) NULL",
        "ALTER TABLE products ADD COLUMN preorder_deposit {$decType} NULL",
        "ALTER TABLE products ADD COLUMN is_boosted INT DEFAULT 0",
        "ALTER TABLE products ADD COLUMN boost_tier VARCHAR(50) NULL",
        "ALTER TABLE products ADD COLUMN boost_expires_at {$timeType} NULL",
        "ALTER TABLE products ADD COLUMN has_variants INT DEFAULT 0",
        "ALTER TABLE products ADD COLUMN variant_attributes {$textType} NULL",
    ];
    foreach ($productCols as $sql) {
        try { $pdo->exec($sql); } catch (\Throwable $e) {}
    }

    // Ensure multi-vendor & variant columns exist on order_items
    $orderItemCols = [
        "ALTER TABLE order_items ADD COLUMN vendor_id INT UNSIGNED NULL",
        "ALTER TABLE order_items ADD COLUMN vendor_earnings {$decType} DEFAULT 0",
        "ALTER TABLE order_items ADD COLUMN platform_fee {$decType} DEFAULT 0",
        "ALTER TABLE order_items ADD COLUMN fulfillment_status VARCHAR(50) DEFAULT 'pending'",
        "ALTER TABLE order_items ADD COLUMN variant_id INT UNSIGNED NULL",
        "ALTER TABLE order_items ADD COLUMN variant_details {$textType} NULL",
    ];
    foreach ($orderItemCols as $sql) {
        try { $pdo->exec($sql); } catch (\Throwable $e) {}
    }

    // Ensure vendor profile & boost columns exist on vendors
    $vendorCols = [
        "ALTER TABLE vendors ADD COLUMN subscription_tier VARCHAR(50) DEFAULT 'free'",
        "ALTER TABLE vendors ADD COLUMN boost_credits INT DEFAULT 5",
        "ALTER TABLE vendors ADD COLUMN ai_credits INT DEFAULT 3",
        "ALTER TABLE vendors ADD COLUMN support_email VARCHAR(150) NULL",
    ];
    foreach ($vendorCols as $sql) {
        try { $pdo->exec($sql); } catch (\Throwable $e) {}
    }

    // Check if seeding is needed
    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($count == 0) {
        zavora_seed_initial_data($pdo);
    }

    // Seed default vendors if empty
    $vCount = $pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn();
    if ($vCount == 0) {
        $vStmt = $pdo->prepare("INSERT INTO vendors (
            store_name, slug, owner_name, email, phone, password_hash, description, bank_name, account_number, account_name, commission_rate, balance, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')");

        $vStmt->execute([
            'Apex Audio & Tech Hub',
            'apex-audio-tech',
            'Emeka Okafor',
            'emeka@apexaudio.ng',
            '08039988776',
            password_hash('vendor123', PASSWORD_DEFAULT),
            'Official authorized distributor of premium headphones, studio sound gear, and computing accessories in Nigeria.',
            'Access Bank',
            '0123456789',
            'Apex Audio Nigeria Ltd',
            15.00,
            450000.00
        ]);

        $vStmt->execute([
            'JLM Creative Studio & Digital Lab',
            'jlm-creative-studio',
            'John Luka',
            'creative@jlm.com.ng',
            '08012345678',
            password_hash('vendor123', PASSWORD_DEFAULT),
            'High-availability livestream engineering manuals, UI systems, and full-stack software templates.',
            'GTBank',
            '0987654321',
            'John Luka Media Enterprise',
            10.00,
            125000.00
        ]);

        try {
            $pdo->exec("UPDATE products SET vendor_id = 1 WHERE id IN (1, 2, 3)");
            $pdo->exec("UPDATE products SET vendor_id = 2 WHERE id IN (4, 5, 6, 7)");
        } catch (\Throwable $e) {}
    }
}

/**
 * Seed realistic starter data for Nigerian marketplace showcase.
 */
function zavora_seed_initial_data(PDO $pdo) {
    // Categories
    $categories = [
        ['Electronics & Gadgets', 'electronics', 'High performance audio, charging, and smart devices', 'fa-headphones', 'physical'],
        ['Phones & Computing', 'computing', 'Smartphones, laptops, and professional computer workstations', 'fa-laptop', 'physical'],
        ['Fashion & Apparel', 'fashion', 'Premium lifestyle, branded apparel, and streetwear', 'fa-shirt', 'physical'],
        ['Digital Assets & Templates', 'digital', 'Production-ready codebases, UI kits, design systems, and LUTs', 'fa-cube', 'digital'],
        ['E-Books & Guides', 'ebooks', 'Business development, tech masterclasses, and executive guides', 'fa-book-open', 'digital'],
    ];

    $catStmt = $pdo->prepare("INSERT INTO categories (name, slug, description, icon, type) VALUES (?, ?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $catStmt->execute($cat);
    }

    // Products
    $products = [
        [
            1, 'Sony WH-1000XM5 Wireless Noise-Cancelling Headphones', 'sony-wh-1000xm5-headphones', 'PHYS-SNY-01', 'physical',
            385000.00, 420000.00, 25,
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
            'Industry-leading noise cancellation with two processors and 8 microphones. Exceptional sound quality with newly developed 30mm driver unit and crystal clear hands-free calling.',
            'Battery: 30 hours | Quick charge: 3 min = 3 hours | Bluetooth 5.2 | High-Res Audio Wireless',
            1, 1, 4.9, 48
        ],
        [
            2, 'Apple MacBook Air 15" M3 (16GB RAM, 512GB SSD) - Space Grey', 'macbook-air-15-m3', 'PHYS-APL-02', 'physical',
            1850000.00, 1950000.00, 10,
            'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&auto=format&fit=crop&q=80',
            'Strikingly thin and fast MacBook Air with M3 chip. Liquid Retina display, 18 hours of battery life, 1080p FaceTime HD camera, and MagSafe 3 charging port.',
            'Chip: Apple M3 (8-core CPU / 10-core GPU) | Memory: 16GB Unified | Storage: 512GB SSD',
            1, 0, 5.0, 32
        ],
        [
            3, 'Logitech MX Master 3S Wireless Performance Mouse', 'logitech-mx-master-3s', 'PHYS-LOG-03', 'physical',
            115000.00, 130000.00, 40,
            'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&auto=format&fit=crop&q=80',
            'An iconic master mouse with 8K DPI any-surface tracking, quiet clicks, and MagSpeed electromagnetic scrolling for ultimate precision and flow.',
            'Sensor: Darkfield 8000 DPI | Connectivity: Bluetooth + Logi Bolt | Multi-device Flow support',
            1, 1, 4.8, 64
        ],
        [
            4, 'JLM Signature Minimalist Tech Heavyweight Hoodie - Charcoal', 'jlm-signature-tech-hoodie', 'PHYS-JLM-04', 'physical',
            38000.00, 45000.00, 50,
            'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&auto=format&fit=crop&q=80',
            'Custom crafted 420 GSM French Terry cotton hoodie with subtle silicone JLM insignia on the cuff. Designed for creative developers and digital professionals.',
            'Material: 100% Ring-Spun Combed Cotton | Fit: Relaxed Drop-Shoulder | Preshrunk',
            0, 0, 4.9, 19
        ],
        [
            4, 'ZAVORA UI Design System & Commercial E-Commerce Figma Kit', 'zavora-figma-ui-kit', 'DIGI-DS-01', 'digital',
            28500.00, 45000.00, 999,
            'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80',
            'Comprehensive design system with over 200+ responsive Auto Layout components, dark/light modes, tokenized typography, and desktop/tablet/mobile screens for marketplaces.',
            'Format: Figma (.fig) | Components: 200+ | Auto Layout 5.0 | Free Lifetime Updates',
            1, 1, 5.0, 84
        ],
        [
            4, 'Full-Stack Multi-Vendor SaaS Starter Kit (Laravel & React)', 'fullstack-saas-starter-kit', 'DIGI-KIT-02', 'digital',
            45000.00, 65000.00, 999,
            'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80',
            'Production-ready boilerplate with authentication, multi-tenancy architecture, Paystack checkout integration, background queues, and admin control panels.',
            'Tech Stack: Laravel + React 18 + Tailwind CSS + MySQL | License: Commercial Unlimited',
            1, 0, 4.9, 52
        ],
        [
            5, 'The 431-Hour Blueprint: High-Availability Livestreaming & Production Guide', 'livestreaming-production-guide', 'EBOOK-LIV-01', 'digital',
            15000.00, 25000.00, 999,
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80',
            'Official technical breakdown and disaster-recovery operational manual from John Luka Media\'s 431-hour Guinness World Record non-stop broadcast achievement.',
            'Format: Interactive PDF + Checklist Sheets | Pages: 142 | Instant Download',
            1, 0, 5.0, 110
        ]
    ];

    $pStmt = $pdo->prepare("INSERT INTO products (
        category_id, title, slug, sku, type, price, previous_price, stock_quantity, thumbnail, description, specifications, is_featured, is_flash_deal, rating, reviews_count
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($products as $p) {
        $pStmt->execute($p);
    }
}

/**
 * Retrieve a system setting value with fallback
 */
function zavora_get_setting($key, $default = null) {
    try {
        $pdo = zavora_get_db();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * Set or update a system setting value (admin configurable)
 */
function zavora_set_setting($key, $value, $description = null) {
    try {
        $pdo = zavora_get_db();
        $chk = $pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $chk->execute([$key]);
        if ($chk->fetch()) {
            $upd = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
            return $upd->execute([$value, $key]);
        } else {
            $ins = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            return $ins->execute([$key, $value, $description]);
        }
    } catch (\Throwable $e) {
        return false;
    }
}
