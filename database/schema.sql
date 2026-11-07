CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        icon VARCHAR(50),
        type VARCHAR(20) DEFAULT 'physical',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INT UNSIGNED,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        sku VARCHAR(50),
        type VARCHAR(20) DEFAULT 'physical', -- physical | digital | course
        price REAL NOT NULL,
        previous_price REAL NULL,
        stock_quantity INT DEFAULT 100,
        thumbnail VARCHAR(255),
        description TEXT,
        specifications TEXT,
        file_path VARCHAR(255) NULL, -- For digital downloads
        course_ref_id INT NULL,      -- For LMS Academy linked courses
        is_featured INT DEFAULT 0,
        is_flash_deal INT DEFAULT 0,
        rating REAL DEFAULT 4.8,
        reviews_count INT DEFAULT 12,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    , vendor_id INT UNSIGNED NULL, is_preorder INT DEFAULT 0, preorder_release_date VARCHAR(50) NULL, preorder_deposit REAL NULL, is_boosted INT DEFAULT 0, boost_tier VARCHAR(50) NULL, boost_expires_at DATETIME NULL, has_variants INT DEFAULT 0, variant_attributes TEXT NULL);
CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_number VARCHAR(50) NOT NULL UNIQUE,
        customer_name VARCHAR(150) NOT NULL,
        customer_email VARCHAR(150) NOT NULL,
        customer_phone VARCHAR(50),
        delivery_address TEXT NULL,
        delivery_state VARCHAR(100) NULL,
        delivery_fee REAL DEFAULT 0,
        subtotal REAL NOT NULL,
        total_amount REAL NOT NULL,
        payment_status VARCHAR(50) DEFAULT 'pending',
        payment_reference VARCHAR(150) NULL,
        payment_method VARCHAR(50) DEFAULT 'paystack',
        has_physical_items INT DEFAULT 0,
        has_digital_items INT DEFAULT 0,
        has_courses INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NULL,
        product_type VARCHAR(20) DEFAULT 'physical',
        title VARCHAR(255) NOT NULL,
        unit_price REAL NOT NULL,
        quantity INT DEFAULT 1,
        total_price REAL NOT NULL,
        course_id INT NULL,
        digital_file_url VARCHAR(255) NULL,
        enrolment_status VARCHAR(50) NULL
    , vendor_id INT UNSIGNED NULL, vendor_earnings REAL DEFAULT 0, platform_fee REAL DEFAULT 0, fulfillment_status VARCHAR(50) DEFAULT 'pending', variant_id INT UNSIGNED NULL, variant_details TEXT NULL);
CREATE TABLE vendors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_name VARCHAR(150) NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        owner_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        phone VARCHAR(50) NULL,
        password_hash VARCHAR(255) NOT NULL,
        logo_url VARCHAR(255) NULL,
        banner_url VARCHAR(255) NULL,
        description TEXT NULL,
        bank_name VARCHAR(100) NULL,
        account_number VARCHAR(30) NULL,
        account_name VARCHAR(150) NULL,
        commission_rate REAL DEFAULT 15.00,
        balance REAL DEFAULT 0.00,
        status VARCHAR(20) DEFAULT 'approved',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    , subscription_tier VARCHAR(50) DEFAULT 'free', boost_credits INT DEFAULT 5, support_email VARCHAR(150) NULL);
CREATE TABLE vendor_payouts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vendor_id INT UNSIGNED NOT NULL,
        amount REAL NOT NULL,
        bank_details TEXT NOT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        reference VARCHAR(100) NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
CREATE TABLE product_variants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INT UNSIGNED NOT NULL,
        sku VARCHAR(60) NOT NULL,
        title VARCHAR(150) NOT NULL,
        attributes_json TEXT NOT NULL, -- JSON: {"Color":"Space Black","Storage":"256GB"}
        price REAL NOT NULL,
        stock_quantity INT DEFAULT 20,
        is_active INT DEFAULT 1,
        image_url VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
