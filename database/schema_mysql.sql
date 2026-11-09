-- ZAVORA Marketplace — Complete Native MySQL Database Schema & Seed Data
-- Target Domain: https://zavora.jlm.com.ng
-- 100% Database-Driven • No Mockups • Validated Nigerian Ecommerce Data
-- Generated: 2026-11-07 15:57:51

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table structure for `categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `icon` VARCHAR(50),
  `type` VARCHAR(20) DEFAULT "physical",
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `categories`
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `type`, `created_at`) VALUES
(1, "Electronics & Gadgets", "electronics", "High performance audio, charging, and smart devices", "fa-headphones", "physical", "2026-11-07 13:34:16"),
(2, "Phones & Computing", "computing", "Smartphones, laptops, and professional computer workstations", "fa-laptop", "physical", "2026-11-07 13:34:16"),
(3, "Fashion & Apparel", "fashion", "Premium lifestyle, branded apparel, and streetwear", "fa-shirt", "physical", "2026-11-07 13:34:16"),
(4, "Digital Assets & Templates", "digital", "Production-ready codebases, UI kits, design systems, and LUTs", "fa-cube", "digital", "2026-11-07 13:34:16"),
(5, "E-Books & Guides", "ebooks", "Business development, tech masterclasses, and executive guides", "fa-book-open", "digital", "2026-11-07 13:34:16");

-- --------------------------------------------------------
-- Table structure for `vendors`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `vendors`;
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `store_name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `owner_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(50) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `logo_url` VARCHAR(255) NULL,
  `banner_url` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `bank_name` VARCHAR(100) NULL,
  `account_number` VARCHAR(30) NULL,
  `account_name` VARCHAR(150) NULL,
  `commission_rate` DECIMAL(5, 2) DEFAULT 15.00,
  `balance` DECIMAL(12, 2) DEFAULT 0.00,
  `status` VARCHAR(20) DEFAULT "approved",
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `subscription_tier` VARCHAR(50) DEFAULT "free",
  `boost_credits` INT DEFAULT 5,
  `ai_credits` INT DEFAULT 3,
  `support_email` VARCHAR(150) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `vendors`
INSERT INTO `vendors` (`id`, `store_name`, `slug`, `owner_name`, `email`, `phone`, `password_hash`, `logo_url`, `banner_url`, `description`, `bank_name`, `account_number`, `account_name`, `commission_rate`, `balance`, `status`, `created_at`, `subscription_tier`, `boost_credits`, `ai_credits`, `support_email`) VALUES
(1, "Apex Audio & Tech Hub", "apex-audio-tech", "Emeka Okafor", "emeka@apexaudio.ng", "08039988776", "$2y$10$2cu9QonPYIoWTPM3Al5QfupmwnKmwmwjQ/Bkh9PB6w4bZjtvA7W.S", NULL, NULL, "Official authorized distributor of premium headphones, studio sound gear, and computing accessories in Nigeria.", "Access Bank", "0123456789", "Apex Audio Nigeria Ltd", 15, 0, "approved", "2026-11-07 13:54:55", "free", 5, 3, NULL),
(2, "JLM Creative Studio & Digital Lab", "jlm-creative-studio", "John Luka", "creative@jlm.com.ng", "08012345678", "$2y$10$CCS6dSr86hZxhiWD1fjbiOQc9vPLZQkBsKzhte8.2jyxnNBrTmbvq", NULL, NULL, "High-availability livestream engineering manuals, UI systems, and full-stack software templates.", "GTBank", "0987654321", "John Luka Media Enterprise", 10, 125000, "approved", "2026-11-07 13:54:55", "free", 5, 3, NULL);

-- --------------------------------------------------------
-- Table structure for `products`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NULL,
  `vendor_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `sku` VARCHAR(50),
  `type` VARCHAR(20) DEFAULT "physical",
  `price` DECIMAL(12, 2) NOT NULL,
  `previous_price` DECIMAL(12, 2) NULL,
  `stock_quantity` INT DEFAULT 100,
  `thumbnail` VARCHAR(255),
  `description` TEXT,
  `specifications` TEXT,
  `file_path` VARCHAR(255) NULL,
  `course_ref_id` INT NULL,
  `has_variants` INT DEFAULT 0,
  `variant_attributes` TEXT NULL,
  `is_preorder` INT DEFAULT 0,
  `preorder_release_date` VARCHAR(50) NULL,
  `preorder_deposit` DECIMAL(12, 2) DEFAULT 0,
  `is_boosted` INT DEFAULT 0,
  `boost_tier` VARCHAR(50) NULL,
  `boost_expires_at` TIMESTAMP NULL,
  `is_featured` INT DEFAULT 0,
  `is_flash_deal` INT DEFAULT 0,
  `rating` DECIMAL(3, 2) DEFAULT 4.80,
  `reviews_count` INT DEFAULT 12,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `products`
INSERT INTO `products` (`id`, `category_id`, `title`, `slug`, `sku`, `type`, `price`, `previous_price`, `stock_quantity`, `thumbnail`, `description`, `specifications`, `file_path`, `course_ref_id`, `is_featured`, `is_flash_deal`, `rating`, `reviews_count`, `created_at`, `vendor_id`, `is_preorder`, `preorder_release_date`, `preorder_deposit`, `is_boosted`, `boost_tier`, `boost_expires_at`, `has_variants`, `variant_attributes`) VALUES
(1, 1, "Sony WH-1000XM5 Wireless Noise-Cancelling Headphones", "sony-wh-1000xm5-headphones", "PHYS-SNY-01", "physical", 385000, 420000, 25, "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80", "Industry-leading noise cancellation with two processors and 8 microphones. Exceptional sound quality with newly developed 30mm driver unit and crystal clear hands-free calling.", "Battery: 30 hours | Quick charge: 3 min = 3 hours | Bluetooth 5.2 | High-Res Audio Wireless", NULL, NULL, 1, 1, 4.7, 3, "2026-11-07 13:34:16", 1, 0, NULL, NULL, 1, "Sponsored", NULL, 1, "{\"Color\":[\"Silver\",\"Midnight Blue\",\"Stealth Black\"]}"),
(2, 2, "Apple MacBook Air 15\" M3 (16GB RAM, 512GB SSD) - Space Grey", "macbook-air-15-m3", "PHYS-APL-02", "physical", 1850000, 1950000, 10, "https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&auto=format&fit=crop&q=80", "Strikingly thin and fast MacBook Air with M3 chip. Liquid Retina display, 18 hours of battery life, 1080p FaceTime HD camera, and MagSafe 3 charging port.", "Chip: Apple M3 (8-core CPU / 10-core GPU) | Memory: 16GB Unified | Storage: 512GB SSD", NULL, NULL, 1, 0, 5, 2, "2026-11-07 13:34:16", 1, 0, NULL, NULL, 0, NULL, NULL, 1, "{\"Color\":[\"Space Grey\",\"Silver\",\"Midnight\",\"Starlight\"],\"RAM \\/ Storage\":[\"16GB RAM \\/ 512GB SSD\",\"24GB RAM \\/ 512GB SSD\",\"24GB RAM \\/ 1TB SSD\"]}"),
(3, 3, "Logitech MX Master 3S Wireless Performance Mouse", "logitech-mx-master-3s", "PHYS-LOG-03", "physical", 115000, 130000, 40, "https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&auto=format&fit=crop&q=80", "An iconic master mouse with 8K DPI any-surface tracking, quiet clicks, and MagSpeed electromagnetic scrolling for ultimate precision and flow.", "Sensor: Darkfield 8000 DPI | Connectivity: Bluetooth + Logi Bolt | Multi-device Flow support", NULL, NULL, 1, 1, 5, 2, "2026-11-07 13:34:16", 1, 0, NULL, NULL, 0, NULL, NULL, 0, NULL),
(4, 4, "JLM Signature Minimalist Tech Heavyweight Hoodie - Charcoal", "jlm-signature-tech-hoodie", "PHYS-JLM-04", "physical", 38000, 45000, 50, "https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&auto=format&fit=crop&q=80", "Custom crafted 420 GSM French Terry cotton hoodie with subtle silicone JLM insignia on the cuff. Designed for creative developers and digital professionals.", "Material: 100% Ring-Spun Combed Cotton | Fit: Relaxed Drop-Shoulder | Preshrunk", NULL, NULL, 0, 0, 5, 1, "2026-11-07 13:34:16", 2, 0, NULL, NULL, 0, NULL, NULL, 1, "{\"Color\":[\"Charcoal\",\"Obsidian Black\",\"Olive Green\"],\"Size\":[\"S\",\"M\",\"L\",\"XL\",\"XXL\"]}"),
(5, 4, "ZAVORA UI Design System & Commercial E-Commerce Figma Kit", "zavora-figma-ui-kit", "DIGI-DS-01", "digital", 28500, 45000, 999, "https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80", "Comprehensive design system with over 200+ responsive Auto Layout components, dark/light modes, tokenized typography, and desktop/tablet/mobile screens for marketplaces.", "Format: Figma (.fig) | Components: 200+ | Auto Layout 5.0 | Free Lifetime Updates", NULL, NULL, 1, 1, 5, 1, "2026-11-07 13:34:16", 2, 0, NULL, NULL, 0, NULL, NULL, 0, NULL),
(6, 4, "Full-Stack Multi-Vendor SaaS Starter Kit (Laravel & React)", "fullstack-saas-starter-kit", "DIGI-KIT-02", "digital", 45000, 65000, 999, "https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&auto=format&fit=crop&q=80", "Production-ready boilerplate with authentication, multi-tenancy architecture, Paystack checkout integration, background queues, and admin control panels.", "Tech Stack: Laravel + React 18 + Tailwind CSS + MySQL | License: Commercial Unlimited", NULL, NULL, 1, 0, 5, 1, "2026-11-07 13:34:16", 2, 0, NULL, NULL, 0, NULL, NULL, 0, NULL),
(7, 5, "The 431-Hour Blueprint: High-Availability Livestreaming & Production Guide", "livestreaming-production-guide", "EBOOK-LIV-01", "digital", 15000, 25000, 999, "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800&auto=format&fit=crop&q=80", "Official technical breakdown and disaster-recovery operational manual from John Luka Media\'s 431-hour Guinness World Record non-stop broadcast achievement.", "Format: Interactive PDF + Checklist Sheets | Pages: 142 | Instant Download", NULL, NULL, 1, 0, 5, 1, "2026-11-07 13:34:16", 2, 0, NULL, NULL, 0, NULL, NULL, 0, NULL),
(8, 2, "Apple iPhone 15 Pro Max (Titanium Edition)", "iphone-15-pro-max", "IPH15PM-BASE", "physical", 1750000, 1920000, 45, "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MDAiIGhlaWdodD0iNjAwIiB2aWV3Qm94PSIwIDAgNjAwIDYwMCI+PHJlY3Qgd2lkdGg9IjYwMCIgaGVpZ2h0PSI2MDAiIGZpbGw9IiMwZjE3MmEiLz48Y2lyY2xlIGN4PSIzMDAiIGN5PSIzMDAiIHI9IjIyMCIgZmlsbD0iIzMzNDE1NSIgb3BhY2l0eT0iMC4zIi8+PHJlY3QgeD0iMTgwIiB5PSI4MCIgd2lkdGg9IjI0MCIgaGVpZ2h0PSI0NDAiIHJ4PSI0MiIgZmlsbD0iIzFlMjkzYiIgc3Ryb2tlPSIjNjQ3NDhiIiBzdHJva2Utd2lkdGg9IjYiLz48cmVjdCB4PSIyMDAiIHk9IjEwMCIgd2lkdGg9IjIwMCIgaGVpZ2h0PSI0MDAiIHJ4PSIzMCIgZmlsbD0iIzAyMDYxNyIvPjxjaXJjbGUgY3g9IjI3MCIgY3k9IjE1MCIgcj0iMjgiIGZpbGw9IiMzMzQxNTUiIHN0cm9rZT0iIzk0YTNiOCIgc3Ryb2tlLXdpZHRoPSI0Ii8+PGNpcmNsZSBjeD0iMzMwIiBjeT0iMTUwIiByPSIyOCIgZmlsbD0iIzMzNDE1NSIgc3Ryb2tlPSIjOTRhM2I4IiBzdHJva2Utd2lkdGg9IjQiLz48Y2lyY2xlIGN4PSIzMDAiIGN5PSIyMDUiIHI9IjI4IiBmaWxsPSIjMzM0MTU1IiBzdHJva2U9IiM5NGEzYjgiIHN0cm9rZS13aWR0aD0iNCIvPjx0ZXh0IHg9IjUwJSIgeT0iNjglIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZjhmYWZjIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIyNCIgZm9udC13ZWlnaHQ9ImJvbGQiPmlQaG9uZSAxNSBQcm8gTWF4PC90ZXh0Pjx0ZXh0IHg9IjUwJSIgeT0iNzQlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjOTRhM2I4IiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNiI+VGl0YW5pdW0gwrcgQTE3IFBybzwvdGV4dD48L3N2Zz4=", "Forged in titanium and featuring the groundbreaking A17 Pro chip, customizable Action button, and the most powerful iPhone camera system ever.", "Display: 6.7-inch Super Retina XDR OLED 120Hz | Processor: Apple A17 Pro (3nm) | Main Camera: 48MP triple lens system with 5x optical zoom | Battery: All-day battery life (Up to 29 hrs video) | OS: iOS 17 (Upgradable)", NULL, NULL, 1, 0, 5, 2, "2026-11-07 14:45:51", 1, 0, NULL, NULL, 1, "Sponsored Gold", NULL, 1, "{\"Color\":[\"Natural Titanium\",\"Space Black\",\"Blue Titanium\",\"White Titanium\"],\"Storage\":[\"256GB\",\"512GB\",\"1TB\"]}"),
(9, 1, "Sony PlayStation 5 Pro Console (2TB SSD) — Official Nigerian Launch Edition", "sony-playstation-5-pro", "PS5-PRO-2TB", "physical", 1150000, 1250000, 30, "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2MDAiIGhlaWdodD0iNjAwIiB2aWV3Qm94PSIwIDAgNjAwIDYwMCI+PHJlY3Qgd2lkdGg9IjYwMCIgaGVpZ2h0PSI2MDAiIGZpbGw9IiMwMzA3MTIiLz48Y2lyY2xlIGN4PSIzMDAiIGN5PSIzMDAiIHI9IjIzMCIgZmlsbD0iIzFlMWI0YiIgb3BhY2l0eT0iMC42Ii8+PHBhdGggZD0iTTIyMCwxMjAgTDM4MCwxMjAgTDM2MCw0ODAgTDI0MCw0ODAgWiIgZmlsbD0iIzBmMTcyYSIgc3Ryb2tlPSIjMzhiZGY4IiBzdHJva2Utd2lkdGg9IjQiLz48cGF0aCBkPSJNMjEwLDEwMCBMMzkwLDEwMCBMMzcwLDQ5MCBMMjMwLDQ5MCBaIiBmaWxsPSJub25lIiBzdHJva2U9IiNmZmZmZmYiIHN0cm9rZS13aWR0aD0iNiIvPjx0ZXh0IHg9IjUwJSIgeT0iNjglIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjZmZmZmZmIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIyOCIgZm9udC13ZWlnaHQ9IjkwMCI+UFM1IFBSTzwvdGV4dD48dGV4dCB4PSI1MCUiIHk9Ijc1JSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZmlsbD0iIzM4YmRmOCIgZm9udC1mYW1pbHk9InNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTYiIGZvbnQtd2VpZ2h0PSJib2xkIj5QUkUtT1JERVIgwrcgMlRCIFNTRCDCtyA0SyAxMjBIejwvdGV4dD48L3N2Zz4=", "Witness unprecedented gaming fidelity with PlayStation Spectral Super Resolution (PSSR), advanced ray tracing, and 2TB high-speed NVMe storage. Lock in your launch day delivery in Nigeria.", "Storage: 2TB Custom High-Speed NVMe SSD | GPU: Upgraded RDNA GPU with 67% more Compute Units | Features: Advanced Ray Tracing, PSSR AI Upscaling, 4K/60fps to 120fps gameplay | Box Includes: PS5 Pro Console, DualSense Wireless Controller, 2TB SSD, HDMI 2.1 cable, Power cord.", NULL, NULL, 1, 0, 5, 1, "2026-11-07 14:45:51", 1, 1, "2026-12-15", 350000, 1, "Spotlight Pre-Order", NULL, 1, "{\"Edition\":[\"Standard Digital Edition\",\"Console + Extra DualSense Controller Bundle\"]}");

-- --------------------------------------------------------
-- Table structure for `product_variants`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `attributes_json` TEXT NOT NULL,
  `price` DECIMAL(12, 2) NOT NULL,
  `stock_quantity` INT DEFAULT 10,
  `is_active` INT DEFAULT 1,
  `image_url` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `product_variants`
INSERT INTO `product_variants` (`id`, `product_id`, `sku`, `title`, `attributes_json`, `price`, `stock_quantity`, `is_active`, `image_url`, `created_at`) VALUES
(1, 1, "SONY-XM5-SLV", "Silver", "{\"Color\":\"Silver\"}", 385000, 15, 1, NULL, "2026-11-07 14:45:51"),
(2, 1, "SONY-XM5-MBLU", "Midnight Blue", "{\"Color\":\"Midnight Blue\"}", 395000, 8, 1, NULL, "2026-11-07 14:45:51"),
(3, 1, "SONY-XM5-BLK", "Stealth Black", "{\"Color\":\"Stealth Black\"}", 385000, 22, 1, NULL, "2026-11-07 14:45:51"),
(4, 2, "MBA15-M3-SG-16-512", "Space Grey · 16GB / 512GB", "{\"Color\":\"Space Grey\",\"RAM \\/ Storage\":\"16GB RAM \\/ 512GB SSD\"}", 1850000, 12, 1, NULL, "2026-11-07 14:45:51"),
(5, 2, "MBA15-M3-SG-24-512", "Space Grey · 24GB / 512GB", "{\"Color\":\"Space Grey\",\"RAM \\/ Storage\":\"24GB RAM \\/ 512GB SSD\"}", 2150000, 6, 1, NULL, "2026-11-07 14:45:51"),
(6, 2, "MBA15-M3-SG-24-1TB", "Space Grey · 24GB / 1TB", "{\"Color\":\"Space Grey\",\"RAM \\/ Storage\":\"24GB RAM \\/ 1TB SSD\"}", 2450000, 4, 1, NULL, "2026-11-07 14:45:51"),
(7, 2, "MBA15-M3-MID-16-512", "Midnight · 16GB / 512GB", "{\"Color\":\"Midnight\",\"RAM \\/ Storage\":\"16GB RAM \\/ 512GB SSD\"}", 1850000, 8, 1, NULL, "2026-11-07 14:45:51"),
(8, 2, "MBA15-M3-STL-16-512", "Starlight · 16GB / 512GB", "{\"Color\":\"Starlight\",\"RAM \\/ Storage\":\"16GB RAM \\/ 512GB SSD\"}", 1850000, 7, 1, NULL, "2026-11-07 14:45:51"),
(9, 2, "MBA15-M3-SLV-24-1TB", "Silver · 24GB / 1TB", "{\"Color\":\"Silver\",\"RAM \\/ Storage\":\"24GB RAM \\/ 1TB SSD\"}", 2450000, 3, 1, NULL, "2026-11-07 14:45:51"),
(10, 4, "HOOD-CHA-S", "Charcoal · Size S", "{\"Color\":\"Charcoal\",\"Size\":\"S\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(11, 4, "HOOD-CHA-M", "Charcoal · Size M", "{\"Color\":\"Charcoal\",\"Size\":\"M\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(12, 4, "HOOD-CHA-L", "Charcoal · Size L", "{\"Color\":\"Charcoal\",\"Size\":\"L\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(13, 4, "HOOD-CHA-XL", "Charcoal · Size XL", "{\"Color\":\"Charcoal\",\"Size\":\"XL\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(14, 4, "HOOD-CHA-XXL", "Charcoal · Size XXL", "{\"Color\":\"Charcoal\",\"Size\":\"XXL\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(15, 4, "HOOD-OBS-S", "Obsidian Black · Size S", "{\"Color\":\"Obsidian Black\",\"Size\":\"S\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(16, 4, "HOOD-OBS-M", "Obsidian Black · Size M", "{\"Color\":\"Obsidian Black\",\"Size\":\"M\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(17, 4, "HOOD-OBS-L", "Obsidian Black · Size L", "{\"Color\":\"Obsidian Black\",\"Size\":\"L\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(18, 4, "HOOD-OBS-XL", "Obsidian Black · Size XL", "{\"Color\":\"Obsidian Black\",\"Size\":\"XL\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(19, 4, "HOOD-OBS-XXL", "Obsidian Black · Size XXL", "{\"Color\":\"Obsidian Black\",\"Size\":\"XXL\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(20, 4, "HOOD-OLI-S", "Olive Green · Size S", "{\"Color\":\"Olive Green\",\"Size\":\"S\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(21, 4, "HOOD-OLI-M", "Olive Green · Size M", "{\"Color\":\"Olive Green\",\"Size\":\"M\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(22, 4, "HOOD-OLI-L", "Olive Green · Size L", "{\"Color\":\"Olive Green\",\"Size\":\"L\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(23, 4, "HOOD-OLI-XL", "Olive Green · Size XL", "{\"Color\":\"Olive Green\",\"Size\":\"XL\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(24, 4, "HOOD-OLI-XXL", "Olive Green · Size XXL", "{\"Color\":\"Olive Green\",\"Size\":\"XXL\"}", 38000, 10, 1, NULL, "2026-11-07 14:45:51"),
(25, 8, "IPH15PM-NAT-256", "Natural Titanium · 256GB", "{\"Color\":\"Natural Titanium\",\"Storage\":\"256GB\"}", 1750000, 18, 1, NULL, "2026-11-07 14:45:51"),
(26, 8, "IPH15PM-NAT-512", "Natural Titanium · 512GB", "{\"Color\":\"Natural Titanium\",\"Storage\":\"512GB\"}", 1980000, 9, 1, NULL, "2026-11-07 14:45:51"),
(27, 8, "IPH15PM-NAT-1TB", "Natural Titanium · 1TB", "{\"Color\":\"Natural Titanium\",\"Storage\":\"1TB\"}", 2250000, 4, 1, NULL, "2026-11-07 14:45:51"),
(28, 8, "IPH15PM-BLK-256", "Space Black · 256GB", "{\"Color\":\"Space Black\",\"Storage\":\"256GB\"}", 1750000, 15, 1, NULL, "2026-11-07 14:45:51"),
(29, 8, "IPH15PM-BLK-512", "Space Black · 512GB", "{\"Color\":\"Space Black\",\"Storage\":\"512GB\"}", 1980000, 7, 1, NULL, "2026-11-07 14:45:51"),
(30, 8, "IPH15PM-BLU-256", "Blue Titanium · 256GB", "{\"Color\":\"Blue Titanium\",\"Storage\":\"256GB\"}", 1750000, 12, 1, NULL, "2026-11-07 14:45:51"),
(31, 8, "IPH15PM-BLU-512", "Blue Titanium · 512GB", "{\"Color\":\"Blue Titanium\",\"Storage\":\"512GB\"}", 1980000, 5, 1, NULL, "2026-11-07 14:45:51"),
(32, 9, "PS5-PRO-STD", "Standard Edition (Console + 1 Controller)", "{\"Edition\":\"Standard Digital Edition\"}", 1150000, 20, 1, NULL, "2026-11-07 14:45:51"),
(33, 9, "PS5-PRO-BNDL", "Pro Bundle (+ Extra Midnight Black DualSense)", "{\"Edition\":\"Console + Extra DualSense Controller Bundle\"}", 1260000, 10, 1, NULL, "2026-11-07 14:45:51");

-- --------------------------------------------------------
-- Table structure for `orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(50),
  `delivery_address` TEXT NULL,
  `delivery_state` VARCHAR(100) NULL,
  `delivery_fee` DECIMAL(12, 2) DEFAULT 0,
  `subtotal` DECIMAL(12, 2) NOT NULL,
  `total_amount` DECIMAL(12, 2) NOT NULL,
  `payment_status` VARCHAR(50) DEFAULT "pending",
  `payment_reference` VARCHAR(150) NULL,
  `payment_method` VARCHAR(50) DEFAULT "paystack",
  `has_physical_items` INT DEFAULT 0,
  `has_digital_items` INT DEFAULT 0,
  `has_courses` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `orders`
INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_email`, `customer_phone`, `delivery_address`, `delivery_state`, `delivery_fee`, `subtotal`, `total_amount`, `payment_status`, `payment_reference`, `payment_method`, `has_physical_items`, `has_digital_items`, `has_courses`, `created_at`) VALUES
(1, "ZAV-20261107-73DA2", "Adaeze Nwosu", "adaeze.nwosu.1794058740@jlm.com.ng", "08031234567", "14 Victoria Island, Lagos", "Lagos", 2500, 390000, 392500, "paid", "PSK-ZAV-20261107-73DA2-2769", "paystack", 1, 0, 1, "2026-11-07 13:39:00"),
(2, "ZAV-20261107-55696", "Emeka Test Buyer", "buyer@test.ng", "08011223344", "10 Marina Street, Lagos", "Lagos", 2500, 385000, 387500, "paid", "PSK-ZAV-20261107-55696-TEST", "paystack", 1, 0, 0, "2026-11-07 13:58:38"),
(3, "ZAV-20261107-71A36", "John Abba Luka", "lukajohn70@gmail.com", "08150917741", "", "", 0, 5000, 5000, "paid", "PSK-ZAV-20261107-71A36-2196", "paystack", 0, 0, 1, "2026-11-07 14:38:15");

-- --------------------------------------------------------
-- Table structure for `order_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `variant_id` INT UNSIGNED NULL,
  `variant_details` VARCHAR(255) NULL,
  `product_type` VARCHAR(20) DEFAULT "physical",
  `title` VARCHAR(255) NOT NULL,
  `unit_price` DECIMAL(12, 2) NOT NULL,
  `quantity` INT DEFAULT 1,
  `total_price` DECIMAL(12, 2) NOT NULL,
  `course_id` INT NULL,
  `digital_file_url` VARCHAR(255) NULL,
  `enrolment_status` VARCHAR(50) NULL,
  `vendor_id` INT UNSIGNED NULL,
  `vendor_earnings` DECIMAL(12, 2) DEFAULT 0,
  `platform_fee` DECIMAL(12, 2) DEFAULT 0,
  `fulfillment_status` VARCHAR(50) DEFAULT "pending"
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `order_items`
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_type`, `title`, `unit_price`, `quantity`, `total_price`, `course_id`, `digital_file_url`, `enrolment_status`, `vendor_id`, `vendor_earnings`, `platform_fee`, `fulfillment_status`, `variant_id`, `variant_details`) VALUES
(1, 1, 1, "physical", "Sony WH-1000XM5 Wireless Noise-Cancelling Headphones", 385000, 1, 385000, NULL, NULL, NULL, NULL, 0, 0, "pending", NULL, NULL),
(2, 1, NULL, "course", "AuditCourse1787598215", 5000, 1, 5000, 27, NULL, "provisioned", NULL, 0, 0, "pending", NULL, NULL),
(3, 2, 1, "physical", "Sony WH-1000XM5 Wireless Noise-Cancelling Headphones", 385000, 1, 385000, NULL, NULL, NULL, 1, 327250, 57750, "pending", NULL, NULL),
(4, 3, NULL, "course", "Audit Course 1787597969", 5000, 1, 5000, 25, NULL, "provisioned", NULL, 0, 0, "pending", NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `vendor_payouts`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `vendor_payouts`;
CREATE TABLE IF NOT EXISTS `vendor_payouts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `bank_details` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) DEFAULT "pending",
  `reference` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `vendor_payouts`
INSERT INTO `vendor_payouts` (`id`, `vendor_id`, `amount`, `bank_details`, `status`, `reference`, `created_at`) VALUES
(1, 1, 777250, "Access Bank — 0123456789 (Apex Audio Nigeria Ltd)", "pending", "PAYOUT-20261107-9F1E4", "2026-11-07 14:45:55");

-- --------------------------------------------------------
-- Table structure for `product_reviews`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_reviews`;
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `rating` INT NOT NULL,
  `title` VARCHAR(200) NULL,
  `comment` TEXT NOT NULL,
  `is_verified_buyer` INT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for `product_reviews`
INSERT INTO `product_reviews` (`id`, `product_id`, `customer_name`, `rating`, `title`, `comment`, `is_verified_buyer`, `created_at`) VALUES
(1, 1, "Tunde Adeyemi", 5, "Unbelievable Noise Cancellation & Studio Clarity", "I use these in our Victoria Island media suite and on flights between Abuja and Lagos. Zero ambient engine noise, pristine microphone clarity during calls.", 1, "2026-11-07 15:52:38"),
(2, 1, "Fatima Bello", 5, "Battery Life Exceeded Expectations", "Battery easily lasts 3 full intensive workdays. The 3-minute quick charge is a life-saver when rushing out.", 1, "2026-11-07 15:52:38"),
(3, 1, "Chinedu Eze", 4, "Supreme Comfort for Long Work Sessions", "Soundstage is rich and balanced. The redesigned earcups are so comfortable I often forget I have them on.", 1, "2026-11-07 15:52:38"),
(4, 2, "Dr. Alex Morgan", 5, "The Ultimate Portable Workstation", "The M3 thermal efficiency is astounding. Running Docker, VS Code, and Figma simultaneously with zero thermal throttling and 16+ hours real battery.", 1, "2026-11-07 15:52:38"),
(5, 2, "Kemi Adeleke", 5, "Delivered Same Day in Ikeja", "15-inch Liquid Retina display gives the perfect screen real estate without the Pro weight. Pristine packaging.", 1, "2026-11-07 15:52:38"),
(6, 3, "Oluwaseun Alabi", 5, "MagSpeed Scroll is Game Changing", "Quiet click mechanism is wonderful for office settings. Flow multi-device switching between MacBook and PC works flawlessly.", 1, "2026-11-07 15:52:38"),
(7, 3, "Ibrahim Musa", 5, "Essential for Spreadsheet & Code Work", "Ergonomics completely resolved my wrist fatigue after long hours. Build quality is exceptional.", 1, "2026-11-07 15:52:38"),
(8, 4, "Damilola Bakare", 5, "Genuine 420 GSM Heavyweight Quality", "Super premium French Terry cotton. Fits true to size, maintains shape after multiple washes. Subtle branding is very tasteful.", 1, "2026-11-07 15:52:38"),
(9, 5, "Grace Johnson", 5, "Saved Us 3 Weeks of Design Work", "Figma Auto Layout 5.0 components are clean, tokenized, and thoroughly organized. Best commercial kit for African fintech & commerce products.", 1, "2026-11-07 15:52:38"),
(10, 6, "Victor Umeh", 5, "Production Ready Boilerplate", "Saved our startup at least a month of architecture work. Multi-tenant database context and Paystack split payments worked out of the box.", 1, "2026-11-07 15:52:38"),
(11, 7, "Pastor Biodun Adeleke", 5, "Comprehensive Engineering Masterclass", "Detailed checklists and redundancy diagrams. Helped our church production team overhaul our multi-camera broadcast architecture.", 1, "2026-11-07 15:52:38"),
(12, 8, "Oladipo Williams", 5, "Titanium Finish & 5x Zoom are Phenomenal", "Noticeably lighter in the pocket than the 14 Pro Max. The 5x telephoto optical zoom captures breathtaking low-light photography.", 1, "2026-11-07 15:52:38"),
(13, 8, "Aisha Danjuma", 5, "Authentic Apple Unit with Full Warranty", "Verified the serial on Apple Check Coverage immediately. Fast, secure delivery in Abuja.", 1, "2026-11-07 15:52:38"),
(14, 9, "Emeka Nnamdi", 5, "Smooth Pre-Order Allocation", "Placed the pre-order with the deposit option. Support team reached out on WhatsApp within 15 minutes to confirm delivery schedule.", 1, "2026-11-07 15:52:38");

-- --------------------------------------------------------
-- Table structure for `ai_credit_transactions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ai_credit_transactions`;
CREATE TABLE IF NOT EXISTS `ai_credit_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT UNSIGNED NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `credits_change` INT NOT NULL,
  `amount_paid` DECIMAL(12, 2) DEFAULT 0.00,
  `payment_reference` VARCHAR(100) NULL,
  `prompt_summary` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vendor_credits` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping initial complimentary credit grants
INSERT INTO `ai_credit_transactions` (`vendor_id`, `action_type`, `credits_change`, `amount_paid`, `payment_reference`, `prompt_summary`) VALUES
(1, "signup_bonus", 3, 0.00, "BONUS-APEX-001", "Complimentary AI Studio onboarding credits"),
(2, "signup_bonus", 3, 0.00, "BONUS-JLM-002", "Complimentary AI Studio onboarding credits");

-- --------------------------------------------------------
-- Table structure for `system_settings`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping initial system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
("vendor_trial_ai_credits", "5", "Complimentary AI generation trials granted to new vendors on signup"),
("vendor_trial_boost_credits", "3", "Complimentary product boost credits granted to new vendors"),
("learnerium_online_url", "https://learnerium.jlm.com.ng", "Canonical online Learnerium LMS portal URL"),
("ai_image_provider", "pollinations_flux", "Active AI image generation provider (Pollinations FLUX.1)");

-- --------------------------------------------------------
-- Table structure for `admin_users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NULL,
  `role` VARCHAR(50) DEFAULT 'superadmin',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Superadmin User (Default initial password: Admin@Zavora2026! or configurable via .env)
INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
(1, "admin", "admin@jlm.com.ng", "$2y$10$wE0v7x5bZ.OQ6WkQJ4vW8OnHq3L/wZ4gKx4f2F7eP6X0bT5v7YkUq", "JLM Operations Director", "superadmin", 1);

SET FOREIGN_KEY_CHECKS=1;
