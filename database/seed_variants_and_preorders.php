<?php
/**
 * ZAVORA Marketplace — Seed Product Variants & Pre-Orders
 */

require_once __DIR__ . '/../config/config.php';
$pdo = zavora_get_db();

echo "=== Seeding Product Variants, Pre-orders & Attributes ===\n";

// 1. Update Product 1: Sony WH-1000XM5
$pdo->exec("UPDATE products SET 
    has_variants = 1,
    is_boosted = 1,
    boost_tier = 'Sponsored',
    variant_attributes = '" . json_encode([
        'Color' => ['Silver', 'Midnight Blue', 'Stealth Black']
    ]) . "'
    WHERE id = 1");

$pdo->exec("DELETE FROM product_variants WHERE product_id = 1");
$stmt = $pdo->prepare("INSERT INTO product_variants (product_id, sku, title, attributes_json, price, stock_quantity, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
$stmt->execute([1, 'SONY-XM5-SLV', 'Silver', json_encode(['Color' => 'Silver']), 385000, 15]);
$stmt->execute([1, 'SONY-XM5-MBLU', 'Midnight Blue', json_encode(['Color' => 'Midnight Blue']), 395000, 8]);
$stmt->execute([1, 'SONY-XM5-BLK', 'Stealth Black', json_encode(['Color' => 'Stealth Black']), 385000, 22]);

// 2. Update Product 2: MacBook Air 15" M3
$pdo->exec("UPDATE products SET 
    has_variants = 1,
    variant_attributes = '" . json_encode([
        'Color' => ['Space Grey', 'Silver', 'Midnight', 'Starlight'],
        'RAM / Storage' => ['16GB RAM / 512GB SSD', '24GB RAM / 512GB SSD', '24GB RAM / 1TB SSD']
    ]) . "'
    WHERE id = 2");

$pdo->exec("DELETE FROM product_variants WHERE product_id = 2");
$stmt->execute([2, 'MBA15-M3-SG-16-512', 'Space Grey · 16GB / 512GB', json_encode(['Color' => 'Space Grey', 'RAM / Storage' => '16GB RAM / 512GB SSD']), 1850000, 12]);
$stmt->execute([2, 'MBA15-M3-SG-24-512', 'Space Grey · 24GB / 512GB', json_encode(['Color' => 'Space Grey', 'RAM / Storage' => '24GB RAM / 512GB SSD']), 2150000, 6]);
$stmt->execute([2, 'MBA15-M3-SG-24-1TB', 'Space Grey · 24GB / 1TB', json_encode(['Color' => 'Space Grey', 'RAM / Storage' => '24GB RAM / 1TB SSD']), 2450000, 4]);
$stmt->execute([2, 'MBA15-M3-MID-16-512', 'Midnight · 16GB / 512GB', json_encode(['Color' => 'Midnight', 'RAM / Storage' => '16GB RAM / 512GB SSD']), 1850000, 8]);
$stmt->execute([2, 'MBA15-M3-STL-16-512', 'Starlight · 16GB / 512GB', json_encode(['Color' => 'Starlight', 'RAM / Storage' => '16GB RAM / 512GB SSD']), 1850000, 7]);
$stmt->execute([2, 'MBA15-M3-SLV-24-1TB', 'Silver · 24GB / 1TB', json_encode(['Color' => 'Silver', 'RAM / Storage' => '24GB RAM / 1TB SSD']), 2450000, 3]);

// 3. Update Product 4: JLM Hoodie
$pdo->exec("UPDATE products SET 
    has_variants = 1,
    variant_attributes = '" . json_encode([
        'Color' => ['Charcoal', 'Obsidian Black', 'Olive Green'],
        'Size'  => ['S', 'M', 'L', 'XL', 'XXL']
    ]) . "'
    WHERE id = 4");

$pdo->exec("DELETE FROM product_variants WHERE product_id = 4");
foreach (['Charcoal', 'Obsidian Black', 'Olive Green'] as $c) {
    foreach (['S', 'M', 'L', 'XL', 'XXL'] as $s) {
        $sku = 'HOOD-' . strtoupper(substr($c, 0, 3)) . '-' . $s;
        $title = "{$c} · Size {$s}";
        $stmt->execute([4, $sku, $title, json_encode(['Color' => $c, 'Size' => $s]), 38000, 10]);
    }
}

// 4. Check if iPhone 15 Pro Max already exists
$existsIphone = $pdo->query("SELECT id FROM products WHERE slug = 'iphone-15-pro-max'")->fetchColumn();
if (!$existsIphone) {
    $ins = $pdo->prepare("INSERT INTO products (
        category_id, vendor_id, title, slug, sku, type, price, previous_price, stock_quantity, thumbnail, description, specifications, is_featured, is_boosted, boost_tier, has_variants, variant_attributes, rating, reviews_count
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $ins->execute([
        2, 1, 'Apple iPhone 15 Pro Max (Titanium Edition)', 'iphone-15-pro-max', 'IPH15PM-BASE', 'physical',
        1750000, 1920000, 45,
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><rect width="600" height="600" fill="#0f172a"/><circle cx="300" cy="300" r="220" fill="#334155" opacity="0.3"/><rect x="180" y="80" width="240" height="440" rx="42" fill="#1e293b" stroke="#64748b" stroke-width="6"/><rect x="200" y="100" width="200" height="400" rx="30" fill="#020617"/><circle cx="270" cy="150" r="28" fill="#334155" stroke="#94a3b8" stroke-width="4"/><circle cx="330" cy="150" r="28" fill="#334155" stroke="#94a3b8" stroke-width="4"/><circle cx="300" cy="205" r="28" fill="#334155" stroke="#94a3b8" stroke-width="4"/><text x="50%" y="68%" dominant-baseline="middle" text-anchor="middle" fill="#f8fafc" font-family="sans-serif" font-size="24" font-weight="bold">iPhone 15 Pro Max</text><text x="50%" y="74%" dominant-baseline="middle" text-anchor="middle" fill="#94a3b8" font-family="sans-serif" font-size="16">Titanium · A17 Pro</text></svg>'),
        'Forged in titanium and featuring the groundbreaking A17 Pro chip, customizable Action button, and the most powerful iPhone camera system ever.',
        'Display: 6.7-inch Super Retina XDR OLED 120Hz | Processor: Apple A17 Pro (3nm) | Main Camera: 48MP triple lens system with 5x optical zoom | Battery: All-day battery life (Up to 29 hrs video) | OS: iOS 17 (Upgradable)',
        1, 1, 'Sponsored Gold', 1,
        json_encode([
            'Color' => ['Natural Titanium', 'Space Black', 'Blue Titanium', 'White Titanium'],
            'Storage' => ['256GB', '512GB', '1TB']
        ]),
        4.9, 48
    ]);
    $pId = $pdo->lastInsertId();

    $stmt->execute([$pId, 'IPH15PM-NAT-256', 'Natural Titanium · 256GB', json_encode(['Color' => 'Natural Titanium', 'Storage' => '256GB']), 1750000, 18]);
    $stmt->execute([$pId, 'IPH15PM-NAT-512', 'Natural Titanium · 512GB', json_encode(['Color' => 'Natural Titanium', 'Storage' => '512GB']), 1980000, 9]);
    $stmt->execute([$pId, 'IPH15PM-NAT-1TB', 'Natural Titanium · 1TB', json_encode(['Color' => 'Natural Titanium', 'Storage' => '1TB']), 2250000, 4]);

    $stmt->execute([$pId, 'IPH15PM-BLK-256', 'Space Black · 256GB', json_encode(['Color' => 'Space Black', 'Storage' => '256GB']), 1750000, 15]);
    $stmt->execute([$pId, 'IPH15PM-BLK-512', 'Space Black · 512GB', json_encode(['Color' => 'Space Black', 'Storage' => '512GB']), 1980000, 7]);

    $stmt->execute([$pId, 'IPH15PM-BLU-256', 'Blue Titanium · 256GB', json_encode(['Color' => 'Blue Titanium', 'Storage' => '256GB']), 1750000, 12]);
    $stmt->execute([$pId, 'IPH15PM-BLU-512', 'Blue Titanium · 512GB', json_encode(['Color' => 'Blue Titanium', 'Storage' => '512GB']), 1980000, 5]);
    echo "Added iPhone 15 Pro Max with 7 variants.\n";
}

// 5. Pre-order Hot Item: Sony PlayStation 5 Pro
$existsPs5 = $pdo->query("SELECT id FROM products WHERE slug = 'sony-playstation-5-pro'")->fetchColumn();
if (!$existsPs5) {
    $ins = $pdo->prepare("INSERT INTO products (
        category_id, vendor_id, title, slug, sku, type, price, previous_price, stock_quantity, thumbnail, description, specifications, is_featured, is_boosted, boost_tier, is_preorder, preorder_release_date, preorder_deposit, has_variants, variant_attributes, rating, reviews_count
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $ins->execute([
        1, 1, 'Sony PlayStation 5 Pro Console (2TB SSD) — Official Nigerian Launch Edition', 'sony-playstation-5-pro', 'PS5-PRO-2TB', 'physical',
        1150000, 1250000, 30,
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><rect width="600" height="600" fill="#030712"/><circle cx="300" cy="300" r="230" fill="#1e1b4b" opacity="0.6"/><path d="M220,120 L380,120 L360,480 L240,480 Z" fill="#0f172a" stroke="#38bdf8" stroke-width="4"/><path d="M210,100 L390,100 L370,490 L230,490 Z" fill="none" stroke="#ffffff" stroke-width="6"/><text x="50%" y="68%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="28" font-weight="900">PS5 PRO</text><text x="50%" y="75%" dominant-baseline="middle" text-anchor="middle" fill="#38bdf8" font-family="sans-serif" font-size="16" font-weight="bold">PRE-ORDER · 2TB SSD · 4K 120Hz</text></svg>'),
        'Witness unprecedented gaming fidelity with PlayStation Spectral Super Resolution (PSSR), advanced ray tracing, and 2TB high-speed NVMe storage. Lock in your launch day delivery in Nigeria.',
        'Storage: 2TB Custom High-Speed NVMe SSD | GPU: Upgraded RDNA GPU with 67% more Compute Units | Features: Advanced Ray Tracing, PSSR AI Upscaling, 4K/60fps to 120fps gameplay | Box Includes: PS5 Pro Console, DualSense Wireless Controller, 2TB SSD, HDMI 2.1 cable, Power cord.',
        1, 1, 'Spotlight Pre-Order', 1, '2026-12-15', 350000, 1,
        json_encode([
            'Edition' => ['Standard Digital Edition', 'Console + Extra DualSense Controller Bundle']
        ]),
        5.0, 31
    ]);
    $pId = $pdo->lastInsertId();

    $stmt->execute([$pId, 'PS5-PRO-STD', 'Standard Edition (Console + 1 Controller)', json_encode(['Edition' => 'Standard Digital Edition']), 1150000, 20]);
    $stmt->execute([$pId, 'PS5-PRO-BNDL', 'Pro Bundle (+ Extra Midnight Black DualSense)', json_encode(['Edition' => 'Console + Extra DualSense Controller Bundle']), 1260000, 10]);
    echo "Added Sony PS5 Pro Pre-Order with 2 edition variants.\n";
}

echo "=== Seeding Complete ===\n";
