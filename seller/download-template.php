<?php
/**
 * ZAVORA Vendor Central — Product Catalog Template Downloader
 * Supports both Microsoft Word (.docx) and CSV / Excel (.csv) templates.
 */

require_once __DIR__ . '/../config/config.php';
$vendor = zavora_vendor_require_auth();

$format = strtolower(trim($_GET['format'] ?? $_GET['type'] ?? 'csv'));

if ($format === 'docx' || $format === 'word') {
    downloadWordTemplate();
} else {
    downloadCsvTemplate();
}

/**
 * Download Microsoft Word (.docx) Product Description Template
 */
function downloadWordTemplate() {
    if (!class_exists('ZipArchive')) {
        die('PHP ZipArchive extension is required to generate .docx files.');
    }

    $tempZip = tempnam(sys_get_temp_dir(), 'zavora_docx_');
    $zip = new ZipArchive();
    if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Unable to initialize Word document archive.');
    }

    // 1. [Content_Types].xml
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';

    // 2. _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';

    // 3. word/document.xml
    $docXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <!-- Template Header -->
    <w:p>
      <w:pPr><w:jc w:val="center"/></w:pPr>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="38"/></w:rPr>
        <w:t>ZAVORA Catalog Product Description Template</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:pPr><w:jc w:val="center"/></w:pPr>
      <w:r>
        <w:rPr><w:i/><w:color w:val="666666"/><w:sz w:val="19"/></w:rPr>
        <w:t>Edit this Word document and upload it in Vendor Central to auto-fill your listing in 1 click.</w:t>
      </w:r>
    </w:p>
    <w:p/>

    <!-- Section: Product Title -->
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="26"/></w:rPr>
        <w:t>Product Title</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:sz w:val="24"/></w:rPr>
        <w:t>Apple iPhone 15 Pro Max 256GB Natural Titanium</w:t>
      </w:r>
    </w:p>
    <w:p/>

    <!-- Section: Category & Pricing -->
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="26"/></w:rPr>
        <w:t>Category &amp; Pricing</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Category: </w:t></w:r>
      <w:r><w:t>Phones &amp; Tablets</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Price: </w:t></w:r>
      <w:r><w:t>1,850,000</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:rPr><w:b/></w:rPr><w:t>Regular Price: </w:t></w:r>
      <w:r><w:t>2,100,000</w:t></w:r>
    </w:p>
    <w:p/>

    <!-- Section: Key Features & Highlights -->
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="26"/></w:rPr>
        <w:t>Key Features &amp; Highlights</w:t>
      </w:r>
    </w:p>
    <w:p><w:r><w:t>• 6.7-inch Super Retina XDR OLED Display with ProMotion 120Hz</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Grade 5 Aerospace Titanium frame with Ceramic Shield front</w:t></w:r></w:p>
    <w:p><w:r><w:t>• A17 Pro 3nm high-performance chip with 6-core GPU for console gaming</w:t></w:r></w:p>
    <w:p><w:r><w:t>• 48MP triple camera system with 5x optical telephoto periscope lens</w:t></w:r></w:p>
    <w:p><w:r><w:t>• USB-C port with USB 3 speeds up to 10Gb/s for lightning file transfers</w:t></w:r></w:p>
    <w:p><w:r><w:t>• All-day battery with fast MagSafe wireless charging support</w:t></w:r></w:p>
    <w:p/>

    <!-- Section: Product Overview & Narrative Description -->
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="26"/></w:rPr>
        <w:t>Product Overview</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>The Apple iPhone 15 Pro Max represents the pinnacle of modern mobile engineering. Forged in aerospace-grade Grade 5 titanium, it is significantly lighter yet stronger than previous generations, featuring contoured edges and the thinnest borders ever on an iPhone.</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>Powered by the revolutionary A17 Pro silicon, this flagship delivers unmatched efficiency, hardware-accelerated ray tracing for immersive mobile gaming, and next-generation computational photography. The advanced 48MP main sensor captures ultra-high-resolution photos with remarkable detail and color fidelity.</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>With universal USB-C connectivity, high-speed MagSafe wireless charging, and official 1-year brand warranty across all authorized service hubs in Nigeria, your purchase is backed by complete peace of mind.</w:t>
      </w:r>
    </w:p>
    <w:p/>

    <!-- Section: Technical Specifications -->
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="26"/></w:rPr>
        <w:t>Technical Specifications</w:t>
      </w:r>
    </w:p>
    <w:p><w:r><w:t>Display: 6.7-inch Super Retina XDR OLED (2796 x 1290 pixels, 120Hz)</w:t></w:r></w:p>
    <w:p><w:r><w:t>Processor: Apple A17 Pro 3nm 6-Core CPU with 16-Core Neural Engine</w:t></w:r></w:p>
    <w:p><w:r><w:t>Storage: 256GB NVMe High-Speed Storage</w:t></w:r></w:p>
    <w:p><w:r><w:t>Main Camera: 48MP Wide (f/1.78) + 12MP Ultra-Wide + 12MP 5x Telephoto</w:t></w:r></w:p>
    <w:p><w:r><w:t>Front Camera: 12MP TrueDepth Camera with Autofocus and 4K HDR Video</w:t></w:r></w:p>
    <w:p><w:r><w:t>Battery Life: Up to 29 hours video playback with Fast USB-C Charging</w:t></w:r></w:p>
    <w:p><w:r><w:t>Operating System: iOS 17 with Dynamic Island &amp; Always-On Display</w:t></w:r></w:p>
    <w:p><w:r><w:t>Connectivity: 5G Sub-6GHz, Wi-Fi 6E, Bluetooth 5.3, Ultra Wideband 2</w:t></w:r></w:p>
    <w:p><w:r><w:t>SIM Capability: Dual SIM (Nano-SIM and digital eSIM)</w:t></w:r></w:p>
    <w:p/>

    <!-- Section: Package Contents -->
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:color w:val="1B2299"/><w:sz w:val="26"/></w:rPr>
        <w:t>What&apos;s in the box</w:t>
      </w:r>
    </w:p>
    <w:p><w:r><w:t>• 1x Apple iPhone 15 Pro Max 256GB Natural Titanium</w:t></w:r></w:p>
    <w:p><w:r><w:t>• 1x USB-C Woven Charging Cable (1 meter)</w:t></w:r></w:p>
    <w:p><w:r><w:t>• 1x Quick Start Guide and Official Warranty Documentation</w:t></w:r></w:p>
  </w:body>
</w:document>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $docXml);
    $zip->close();

    $downloadName = 'zavora_product_template_' . date('Y-m-d') . '.docx';

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tempZip));

    readfile($tempZip);
    @unlink($tempZip);
    exit;
}

/**
 * Download CSV Spreadsheet Template
 */
function downloadCsvTemplate() {
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
}
