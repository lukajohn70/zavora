<?php
/**
 * ZAVORA Vendor Central — Product Catalog Template Downloader
 * Generates an official CSV template with headers and sample product rows.
 */

require_once __DIR__ . '/../config/config.php';
$vendor = zavora_vendor_require_auth();

$filename = 'zavora_product_template_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header Row
fputcsv($out, [
    'title',
    'category',
    'type',
    'price',
    'previous_price',
    'stock_quantity',
    'key_features',
    'description',
    'specifications',
    'box_contents'
], ',', '"', '\\');

// Sample Physical Product Row
fputcsv($out, [
    'Apple iPhone 15 Pro Max 256GB Natural Titanium',
    'Phones & Tablets',
    'physical',
    '1850000',
    '2100000',
    '25',
    '6.7-inch Super Retina XDR OLED Display with ProMotion 120Hz; Grade 5 Aerospace Titanium frame; A17 Pro 3nm high-performance chip; 48MP triple camera system with 5x optical telephoto; USB-C port with USB 3 speeds up to 10Gb/s; All-day battery with fast MagSafe wireless charging',
    'The Apple iPhone 15 Pro Max represents the pinnacle of mobile innovation. Crafted from aerospace-grade titanium, it is both our lightest and strongest Pro model ever built. Powered by the groundbreaking A17 Pro chip, it delivers console-level gaming performance and next-generation computational photography with a versatile 48MP main sensor and a 5x optical telephoto lens. Official 1-year brand warranty included across all service centers in Nigeria.',
    'Display: 6.7-inch Super Retina XDR | Processor: Apple A17 Pro 3nm | Storage: 256GB NVMe | Camera: 48MP + 12MP + 12MP | Battery: Up to 29 hours video playback | Operating System: iOS 17 | SIM: Dual Nano-SIM / eSIM',
    '1x iPhone 15 Pro Max, 1x USB-C Woven Charging Cable (1m), 1x Documentation and Warranty Card'
], ',', '"', '\\');

// Sample Digital / Course Product Row
fputcsv($out, [
    'Complete Full-Stack Web Development Bootcamp: Zero to Hero',
    'Online Courses',
    'digital',
    '45000',
    '85000',
    '1000',
    'Over 65 hours of HD video training; Complete modern stack: HTML5, Tailwind CSS, PHP 8, Laravel, Vue.js; 12 real-world production projects built from scratch; Verified John Luka Media digital certificate of completion; Lifetime access including all future platform updates',
    'Master modern web development from foundational HTML and CSS to advanced backend microservices with PHP and modern JavaScript. Designed specifically for ambitious African developers, this curriculum teaches production-grade coding practices, database design, API integration, and cloud deployment.',
    'Format: Digital Video Course | Total Duration: 65 Hours | Skill Level: Beginner to Advanced | Certificate: Included | Language: English | Access: Lifetime LMS Streaming',
    'Instant Learnerium Academy LMS Portal Access, Downloadable Source Code Repositories, Project Asset Packs'
], ',', '"', '\\');

fclose($out);
exit;
