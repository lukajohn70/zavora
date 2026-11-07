# ZAVORA — Multi-Category E-Commerce & Multi-Vendor Marketplace

> **"Everything you need. One place."**  
> A high-performance, mobile-first unified commerce platform for physical goods, digital assets, and accredited e-learning courses across the **John Luka Media (JLM)** digital ecosystem.

---

## 🌟 Overview

**ZAVORA** is an all-in-one e-commerce marketplace built for modern commerce in Nigeria and across Africa. It bridges physical retail, digital software/media distribution, and academic course enrollment under a single seamless checkout and vendor management portal.

### Core Capabilities

1. **Multi-Variant Physical Products**:
   - Dynamic SKU management for colours, sizes (S–XXL), dimensions, storage/RAM tiers (e.g. 256GB / 512GB / 1TB, 16GB / 24GB unified memory).
   - Real-time client-side price, SKU, stock count, and attribute switching.
   - Distinct shopping cart line-item keys per variant (`{$type}_{$id}_{$variantId}`).

2. **Multi-Vendor Self-Service Portal (`/seller`)**:
   - **Store Profile & Branding**: Store banner, logo, business bio, and customer support channels.
   - **Direct Bank Payouts**: Real-time NUBAN bank account configuration supporting **18 leading Nigerian banks** (Access Bank, GTBank, Zenith Bank, First Bank, UBA, Kuda MFB, OPay, PalmPay, Moniepoint, Stanbic IBTC, and more).
   - **Product Variety Creation**: Interactive variety generator with instant SKU matrix creation.
   - **Live Inventory Controls**: Inline stock editor to adjust inventory on the fly.
   - **1-Click Boost Listings**: Promote products to top search/category positions (`Sponsored Gold` tier).
   - **Pre-Order Engine**: Flag upcoming releases, target delivery dates, and required deposit amounts.

3. **Digital & E-Learning Catalog**:
   - **Digital Downloads**: Instant cryptographically secure tokenized download links with expiry protection.
   - **Learnerium LMS Academy**: Real-time course provisioning upon checkout completion via HMAC-authenticated ecosystem APIs.
   - **LMS Result Portal Bridge**: Direct link for academic result checking and school portal switching.

4. **Payments & Checkout**:
   - Paystack payment gateway (Debit/Credit Cards, Bank Transfer, USSD).
   - Nigerian states delivery fee calculation engine.
   - Guest and customer account order tracking.

---

## 📁 Repository Structure

```
zavora/
├── account/                    # Customer account & order history
├── admin/                      # Platform administration portal
├── assets/                     # CSS, SVG vector logos, JavaScript
├── config/
│   ├── config.php              # Core bootstrap, environment, and helper functions
│   ├── config.production.php   # Production configuration preset for cPanel/VPS
│   └── database.php            # Auto-migrating MySQL/SQLite dual driver
├── database/
│   ├── schema.sql              # Clean SQL schema dump for MySQL/SQLite
│   ├── seed_variants_and_preorders.php # Catalog seeder with variant matrices
│   └── .htaccess               # Security hardening against direct database access
├── includes/
│   ├── header.php              # Responsive ecosystem navigation & search
│   └── footer.php              # Footnotes, links, newsletter, and bank badges
├── seller/
│   ├── index.php               # Vendor dashboard (Shop, Payouts, Inventory, Pre-orders)
│   ├── login.php               # Vendor authentication
│   └── register.php            # Vendor onboarding wizard
├── uploads/                    # Product imagery & digital assets (gitignored)
├── academy.php                 # LMS Course catalog
├── cart.php                    # Shopping cart with multi-variant grouping
├── checkout.php                # Checkout & Paystack payment dispatcher
├── index.php                   # Storefront landing page & flash sales
├── order_confirmation.php      # Order receipt, download tokens, and SSO links
├── product.php                 # Interactive product detail page with variant selectors
├── shop.php                    # Product catalog with category, price & type filters
├── .env.example                # Example environment variables
└── README.md                   # Project documentation
```

---

## 🚀 Quick Start

### Requirements
- **PHP**: 7.4.33+ or 8.x
- **Extensions**: `pdo`, `pdo_mysql`, `pdo_sqlite`, `curl`, `json`, `mbstring`
- **Web Server**: Apache (`mod_rewrite` enabled) or Nginx
- **Database**: MySQL 5.7+ / MariaDB 10.3+ (Local dev supports zero-config SQLite)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/lukajohn70/zavora.git
   cd zavora
   ```

2. **Configure environment**:
   ```bash
   cp .env.example .env
   ```
   Open `.env` and fill in your Paystack keys, database credentials, and ecosystem URLs.

3. **Run on MAMP / Local Server**:
   - Move or symlink `zavora` to your web server document root (e.g., `/Applications/MAMP/htdocs/zavora`).
   - Navigate to `http://localhost/zavora/` in your browser.
   - The auto-migrator in `config/database.php` will automatically create all tables and starter categories/products on first load!

4. **Seed Rich Variants & Pre-Orders**:
   ```bash
   php database/seed_variants_and_preorders.php
   ```

---

## 🔒 Security Hardening

- Sensitive `.env` files and SQLite databases are protected by Apache `.htaccess` rules denying direct HTTP access (`Require all denied`).
- All user inputs are sanitized and parameterized with PDO prepared statements.
- Inter-service ecosystem communication uses `HMAC-SHA256` signatures with time-to-live (`sso_token_ttl`) expiry checks.

---

## 📄 License & Attribution

Part of the **John Luka Media (JLM)** Unified Digital Ecosystem.  
Proprietary software &copy; 2026 John Luka Media. All rights reserved.
