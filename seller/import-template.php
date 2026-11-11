<?php
/**
 * ZAVORA Vendor Central — Word (.docx) & CSV (.csv) Template Importer API
 *
 * Parses Microsoft Word (.docx) documents and CSV / Excel catalog templates,
 * extracting formatted titles, key highlights, rich narrative HTML (for Quill),
 * structured specifications matrix, and packaging contents.
 */

require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json; charset=utf-8');

// Strict Vendor Authentication
$vendor = zavora_vendor_auth();
if (!$vendor) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized session. Please log in as a merchant.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['template_file'])) {
    echo json_encode(['success' => false, 'message' => 'No template file uploaded.']);
    exit;
}

$file = $_FILES['template_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
    exit;
}

$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, ['docx', 'csv', 'txt'])) {
    echo json_encode(['success' => false, 'message' => 'Unsupported file format. Please upload a Microsoft Word (.docx) or CSV (.csv) file.']);
    exit;
}

try {
    if ($ext === 'docx') {
        $result = parseDocxFile($file['tmp_name']);
        echo json_encode([
            'success' => true,
            'format' => 'docx',
            'filename' => $filename,
            'data' => $result
        ]);
        exit;
    } elseif ($ext === 'csv') {
        $result = parseCsvFile($file['tmp_name']);
        echo json_encode([
            'success' => true,
            'format' => 'csv',
            'filename' => $filename,
            'data' => $result
        ]);
        exit;
    } else {
        // Plain text fallback
        $text = file_get_contents($file['tmp_name']);
        echo json_encode([
            'success' => true,
            'format' => 'txt',
            'filename' => $filename,
            'data' => [
                'title' => '',
                'description' => nl2br(htmlspecialchars($text)),
                'raw_text' => $text,
                'highlights' => [],
                'specifications' => [],
                'box_contents' => ''
            ]
        ]);
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to parse file: ' . $e->getMessage()]);
    exit;
}

/**
 * Parse Word (.docx) file via ZipArchive and XML parsing
 */
function parseDocxFile($filePath) {
    if (!class_exists('ZipArchive')) {
        throw new \Exception('PHP ZipArchive extension is required to parse .docx files.');
    }

    $zip = new \ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new \Exception('Unable to open .docx archive.');
    }

    $xmlIndex = $zip->locateName('word/document.xml');
    if ($xmlIndex === false) {
        $zip->close();
        throw new \Exception('Invalid Word document: word/document.xml missing.');
    }

    $xmlContent = $zip->getFromIndex($xmlIndex);
    $zip->close();

    // Use DOMDocument to parse XML
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $paragraphs = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'p');
    if ($paragraphs->length === 0) {
        $paragraphs = $dom->getElementsByTagName('p');
    }

    $htmlParts = [];
    $rawLines = [];
    $inList = false;

    foreach ($paragraphs as $p) {
        $isBullet = false;
        $isHeading = false;
        $headingLevel = 3;

        // Check paragraph properties
        $pPr = $p->getElementsByTagName('pPr')->item(0);
        if ($pPr) {
            // Check for list numbering
            if ($pPr->getElementsByTagName('numPr')->length > 0) {
                $isBullet = true;
            }
            // Check for heading styles
            $pStyle = $pPr->getElementsByTagName('pStyle')->item(0);
            if ($pStyle) {
                $val = strtolower($pStyle->getAttribute('w:val') ?: $pStyle->getAttribute('val'));
                if (strpos($val, 'heading1') !== false || strpos($val, 'title') !== false) {
                    $isHeading = true;
                    $headingLevel = 2;
                } elseif (strpos($val, 'heading2') !== false) {
                    $isHeading = true;
                    $headingLevel = 3;
                } elseif (strpos($val, 'heading3') !== false) {
                    $isHeading = true;
                    $headingLevel = 4;
                }
            }
        }

        // Extract text runs with bold/italic styling
        $runs = $p->getElementsByTagName('r');
        $pText = '';
        $pHtml = '';

        foreach ($runs as $r) {
            $t = '';
            $tElements = $r->getElementsByTagName('t');
            foreach ($tElements as $te) {
                $t .= $te->nodeValue;
            }
            if ($t === '') continue;

            $rPr = $r->getElementsByTagName('rPr')->item(0);
            $isBold = false;
            $isItalic = false;

            if ($rPr) {
                if ($rPr->getElementsByTagName('b')->length > 0) $isBold = true;
                if ($rPr->getElementsByTagName('i')->length > 0) $isItalic = true;
            }

            $escaped = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
            if ($isBold) $escaped = "<strong>{$escaped}</strong>";
            if ($isItalic) $escaped = "<em>{$escaped}</em>";

            $pHtml .= $escaped;
            $pText .= $t;
        }

        $pText = trim($pText);
        if ($pText === '') continue;

        $rawLines[] = $pText;

        // Check if paragraph text starts with bullet-like chars
        if (preg_match('/^[\x{2022}\x{2023}\x{25E6}\x{2043}\x{2219}\-•*]\s*(.*)$/u', $pText, $bulletMatch)) {
            $isBullet = true;
            $pHtml = htmlspecialchars($bulletMatch[1], ENT_QUOTES, 'UTF-8');
        }

        if ($isBullet) {
            if (!$inList) {
                $htmlParts[] = '<ul>';
                $inList = true;
            }
            $htmlParts[] = "<li>{$pHtml}</li>";
        } else {
            if ($inList) {
                $htmlParts[] = '</ul>';
                $inList = false;
            }
            if ($isHeading) {
                $htmlParts[] = "<h{$headingLevel}>{$pHtml}</h{$headingLevel}>";
            } else {
                $htmlParts[] = "<p>{$pHtml}</p>";
            }
        }
    }

    if ($inList) {
        $htmlParts[] = '</ul>';
    }

    $fullHtml = implode("\n", $htmlParts);

    // Smart Section Extraction from Word Document
    $parsedData = extractSectionsFromLines($rawLines);
    $parsedData['html_description'] = $fullHtml;

    return $parsedData;
}

/**
 * Parse CSV file into structured listing data
 */
function parseCsvFile($filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new \Exception('Unable to open CSV file.');
    }

    // Skip UTF-8 BOM if present
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle, 0, ',', '"', '\\');
    if (!$header) {
        fclose($handle);
        throw new \Exception('CSV file is empty.');
    }

    // Normalize header keys
    $headers = array_map(function($h) {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $h))));
    }, $header);

    $rows = [];
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (empty(array_filter($row))) continue;
        $rowAssoc = [];
        foreach ($headers as $idx => $key) {
            $rowAssoc[$key] = trim($row[$idx] ?? '');
        }
        $rows[] = $rowAssoc;
    }
    fclose($handle);

    if (empty($rows)) {
        throw new \Exception('No data rows found in CSV template.');
    }

    // Use the first data row
    $first = $rows[0];

    // Process Key Features (split by semicolon, newline, or bullet)
    $highlights = [];
    $rawHighlights = $first['key_features'] ?? $first['highlights'] ?? $first['features'] ?? '';
    if (!empty($rawHighlights)) {
        $parts = preg_split('/[;\n•|]+/', $rawHighlights);
        foreach ($parts as $p) {
            $p = trim(ltrim(trim($p), "-*• \t"));
            if (!empty($p) && strlen($p) > 2) {
                $highlights[] = $p;
            }
        }
    }

    // Process Specifications (split by pipe or newline)
    $specifications = [];
    $rawSpecs = $first['specifications'] ?? $first['specs'] ?? '';
    if (!empty($rawSpecs)) {
        $parts = (strpos($rawSpecs, '|') !== false) ? explode('|', $rawSpecs) : explode("\n", $rawSpecs);
        foreach ($parts as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            if (strpos($p, ':') !== false) {
                [$k, $v] = explode(':', $p, 2);
                $specifications[] = ['key' => trim($k), 'val' => trim($v)];
            }
        }
    }

    // Prepare HTML for Description
    $rawDesc = $first['description'] ?? $first['product_overview'] ?? '';
    $htmlDesc = '<p>' . nl2br(htmlspecialchars($rawDesc)) . '</p>';

    return [
        'title' => $first['title'] ?? $first['product_name'] ?? '',
        'category' => $first['category'] ?? '',
        'type' => in_array(strtolower($first['type'] ?? ''), ['digital', 'physical', 'course']) ? strtolower($first['type']) : 'physical',
        'price' => floatval($first['price'] ?? 0),
        'previous_price' => !empty($first['previous_price']) ? floatval($first['previous_price']) : null,
        'stock_quantity' => !empty($first['stock_quantity']) ? intval($first['stock_quantity']) : 50,
        'highlights' => array_slice($highlights, 0, 8),
        'html_description' => $htmlDesc,
        'specifications' => $specifications,
        'box_contents' => $first['box_contents'] ?? $first['package_contents'] ?? ''
    ];
}

/**
 * Intelligent section parsing for Word documents
 */
function extractSectionsFromLines(array $lines) {
    $title = '';
    $highlights = [];
    $specifications = [];
    $boxContents = '';
    $currentSection = 'intro';

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) continue;

        // The very first non-empty line is likely the Title
        if (empty($title) && strlen($trimmed) > 5 && strlen($trimmed) < 180) {
            $title = $trimmed;
            continue;
        }

        // Section header triggers
        if (preg_match('/^(key\s+features|highlights|key\s+highlights|features)/i', $trimmed)) {
            $currentSection = 'highlights';
            continue;
        }
        if (preg_match('/^(specifications|technical\s+specifications|specs|details)/i', $trimmed)) {
            $currentSection = 'specs';
            continue;
        }
        if (preg_match('/^(what[\'’]?s\s+in\s+the\s+box|package\s+contents|in\s+the\s+box|box\s+contents)/i', $trimmed)) {
            $currentSection = 'box';
            continue;
        }
        if (preg_match('/^(product\s+overview|description|about\s+this\s+item)/i', $trimmed)) {
            $currentSection = 'description';
            continue;
        }

        // Section handling
        if ($currentSection === 'highlights') {
            $cleanBullet = trim(ltrim($trimmed, "•-* \t"));
            if (strlen($cleanBullet) > 2) {
                $highlights[] = $cleanBullet;
            }
        } elseif ($currentSection === 'specs') {
            if (strpos($trimmed, ':') !== false) {
                [$k, $v] = explode(':', $trimmed, 2);
                $specifications[] = ['key' => trim($k), 'val' => trim($v)];
            }
        } elseif ($currentSection === 'box') {
            $cleanItem = trim(ltrim($trimmed, "•-* \t"));
            if (!empty($cleanItem)) {
                $boxContents .= ($boxContents ? ', ' : '') . $cleanItem;
            }
        }
    }

    return [
        'title' => $title,
        'highlights' => array_slice($highlights, 0, 8),
        'specifications' => $specifications,
        'box_contents' => $boxContents
    ];
}
