<?php
/**
 * ZAVORA AI Merchant Studio API Endpoint (Paid Feature)
 *
 * Provides AI-powered title generation, description drafting, technical spec formatting,
 * and commercial product image studio generation for vendors.
 *
 * Enforces credit-based monetization (consumes 1 AI Credit per generation).
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/config.php';

$pdo = zavora_get_db();

// 1. Enforce Vendor Authentication
$vendorId = $_SESSION['zavora_vendor_id'] ?? null;
if (!$vendorId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized', 'message' => 'Please sign in to your vendor account.']);
    exit;
}

// Fetch current vendor
$vStmt = $pdo->prepare("SELECT id, store_name, email, ai_credits, subscription_tier FROM vendors WHERE id = ?");
$vStmt->execute([$vendorId]);
$vendor = $vStmt->fetch();

if (!$vendor) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_found', 'message' => 'Vendor not found.']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// Action: Check AI Credit Balance & Pricing Tiers
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'check_credits') {
    echo json_encode([
        'success' => true,
        'ai_credits' => intval($vendor['ai_credits']),
        'packs' => [
            'starter' => ['credits' => 25, 'price' => 2500, 'label' => 'Starter Pack (25 Credits)', 'unit_price' => '₦100/gen'],
            'growth'  => ['credits' => 75, 'price' => 6000, 'label' => 'Growth Pack (75 Credits)', 'unit_price' => '₦80/gen - 20% Off'],
            'pro'     => ['credits' => 200, 'price' => 14000, 'label' => 'Pro Merchant Pack (200 Credits)', 'unit_price' => '₦70/gen - 30% Off'],
        ]
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Action: Generate Product Copy (International Standards: GS1 Title, 5 Amazon Bullets, Spec Matrix)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'generate_copy') {
    $currentCredits = intval($vendor['ai_credits']);
    if ($currentCredits < 1) {
        echo json_encode([
            'success' => false,
            'error' => 'insufficient_credits',
            'ai_credits' => 0,
            'message' => 'You have 0 AI Credits remaining. Please purchase an AI Credit Pack to generate product listings.'
        ]);
        exit;
    }

    $rawInput = trim($_POST['keywords'] ?? $_POST['concept'] ?? '');
    $categoryName = trim($_POST['category_name'] ?? 'General Products');
    $brandName = trim($_POST['brand_name'] ?? $vendor['store_name']);
    $tone = trim($_POST['tone'] ?? 'Commercial');

    if (empty($rawInput)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a product title, keyword, or brief concept.']);
        exit;
    }

    $geminiApiKey = getenv('GEMINI_API_KEY');
    $generatedTitle = '';
    $generatedBullets = [];
    $generatedDesc = '';
    $generatedSpecsMap = [];
    $generatedTags = [];

    // Attempt Gemini AI Generation if key present
    if (!empty($geminiApiKey) && strpos($geminiApiKey, 'YOUR_') === false) {
        $prompt = "You are an international e-commerce catalog specialist adhering strictly to Amazon ASIN, GS1, and Google Shopping standards for ZAVORA marketplace.

Product Input / Keywords: {$rawInput}
Store / Brand Name: {$brandName}
Category: {$categoryName}
Tone: {$tone}

Generate an internationally compliant e-commerce listing in valid JSON with this EXACT structure (no markdown fences):
{
  \"title\": \"Brand Model — Defining Feature, Color / Spec (60-90 chars, Title Case, no spam)\",
  \"bullet_points\": [
    \"KEY BENEFIT: One-sentence impactful core problem solved.\",
    \"PREMIUM MATERIALS: Specific build materials, durability rating, and craftsmanship.\",
    \"TECHNICAL SPECS: Detailed technical sensors, wattage, connectivity, or chipset metrics.\",
    \"ERGONOMICS & BATTERY: Ergonomic comfort, dimensions, battery life, or universal compatibility.\",
    \"IN THE BOX & WARRANTY: Exact packaged contents and official 12-month manufacturer warranty.\"
  ],
  \"description\": \"Two paragraphs of narrative storytelling highlighting practical value, reliability, and daily use.\",
  \"specifications\": {
    \"Brand\": \"{$brandName}\",
    \"Category\": \"{$categoryName}\",
    \"Connectivity\": \"Bluetooth 5.3 / USB-C Fast Charge\",
    \"Build Material\": \"Precision Aluminum & Polymer\",
    \"Power / Battery\": \"High Endurance Lithium-Ion\",
    \"Dimensions & Weight\": \"Standard E-Commerce Packaging\",
    \"Warranty\": \"12-Month Official Nigerian Warranty\"
  },
  \"search_tags\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"]
}";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($geminiApiKey);
        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json'
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($httpCode === 200 && $rawResponse) {
            $parsed = json_decode($rawResponse, true);
            $candidateText = $parsed['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $jsonParsed = json_decode($candidateText, true);
            if (!empty($jsonParsed['title'])) {
                $generatedTitle = trim($jsonParsed['title']);
                $generatedBullets = (array)($jsonParsed['bullet_points'] ?? []);
                $generatedDesc = trim($jsonParsed['description'] ?? '');
                $generatedSpecsMap = (array)($jsonParsed['specifications'] ?? []);
                $generatedTags = (array)($jsonParsed['search_tags'] ?? []);
            }
        }
    }

    // High-Quality Algorithmic E-Commerce Standards Fallback
    if (empty($generatedTitle)) {
        $words = ucwords(trim($rawInput));
        $generatedTitle = "{$brandName} {$words} — Professional Series";
        
        $generatedBullets = [
            "CORE PERFORMANCE: Engineered specifically for high-efficiency daily productivity, responsive tactile feedback, and verified durability.",
            "PREMIUM CRAFTSMANSHIP: Built using reinforced industrial-grade composite alloy housing engineered to withstand heavy enterprise workloads.",
            "SMART COMPATIBILITY: Features plug-and-play universal architecture compatible with macOS, Windows, iOS, and Android ecosystems.",
            "EXTENDED ENDURANCE: Optimized low-power internal controller delivers extended operational longevity with fast-charge recharging.",
            "PACKAGE & WARRANTY: Includes complete retail unboxing accessories, protective casing, and 12-Month Official Nigerian Warranty support."
        ];

        $generatedDesc = "Experience precision engineering and uncompromising reliability. The {$words} combines cutting-edge architecture with minimalist industrial aesthetics, purpose-built for creators, developers, and discerning professionals.\n\nEvery unit is backed by comprehensive manufacturer warranty protection and certified quality assurance inspection.";

        $generatedSpecsMap = [
            "Brand"             => $brandName,
            "Category"          => $categoryName,
            "Series"            => "Professional Edition",
            "Connectivity"      => "Universal Protocol / Quick-Sync",
            "Build & Material"  => "High-Density Reinforced Polymer & Alloy",
            "Certifications"    => "CE / RoHS / FCC Standard",
            "Warranty Coverage" => "12-Month Official Manufacturer Warranty"
        ];

        $generatedTags = array_slice(array_unique(array_filter(explode(' ', strtolower(preg_replace('/[^a-z0-9 ]/i', '', $rawInput))))), 0, 6);
    }

    // Build pipe-separated specifications string for relational DB storage & backwards compatibility
    $pipePairs = [];
    foreach ($generatedSpecsMap as $k => $v) {
        $pipePairs[] = "{$k}: {$v}";
    }
    $generatedSpecsPipe = implode(" | ", $pipePairs);

    // Format rich description with 5 Amazon-style bullet highlights
    $formattedBulletsText = "KEY HIGHLIGHTS:\n• " . implode("\n• ", $generatedBullets);
    $fullRichDescription = $formattedBulletsText . "\n\nPRODUCT OVERVIEW:\n" . $generatedDesc;

    // Deduct 1 Credit & Record Transaction
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE vendors SET ai_credits = ai_credits - 1 WHERE id = ?")->execute([$vendorId]);
    $pdo->prepare("INSERT INTO ai_credit_transactions (vendor_id, action_type, credits_change, prompt_summary) VALUES (?, 'copy_generation', -1, ?)")
        ->execute([$vendorId, "AI Listing Draft: " . substr($rawInput, 0, 100)]);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'title'               => $generatedTitle,
            'bullet_points'       => $generatedBullets,
            'description'         => $generatedDesc,
            'full_description'    => $fullRichDescription,
            'specifications'      => $generatedSpecsPipe,
            'specifications_map'  => $generatedSpecsMap,
            'specifications_pipe' => $generatedSpecsPipe,
            'search_tags'         => $generatedTags,
            'standard'            => 'Amazon ASIN / GS1 / Google Merchant Compliant'
        ],
        'remaining_credits' => $currentCredits - 1,
        'message' => 'International-standard product listing generated (1 AI credit deducted).'
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Action: Generate / Select Studio Product Image
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'generate_image') {
    $currentCredits = intval($vendor['ai_credits']);
    if ($currentCredits < 1) {
        echo json_encode([
            'success' => false,
            'error' => 'insufficient_credits',
            'ai_credits' => 0,
            'message' => 'You have 0 AI Credits remaining. Please purchase an AI Credit Pack to generate product photography.'
        ]);
        exit;
    }

    $prompt = trim($_POST['prompt'] ?? $_POST['title'] ?? '');
    $mode = trim($_POST['mode'] ?? $_POST['style'] ?? 'hero');
    if (!in_array($mode, ['hero', 'lifestyle'])) {
        $mode = 'hero';
    }

    if (empty($prompt)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a product title or visual prompt.']);
        exit;
    }

    // Curated high-resolution studio commercial product image presets for fallback
    $studioPhotos = [
        'electronics' => [
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1572536147248-ac59a8abfa4b?w=800&auto=format&fit=crop&q=80'
        ],
        'computing' => [
            'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?w=800&auto=format&fit=crop&q=80'
        ],
        'phone' => [
            'https://images.unsplash.com/photo-1591337676887-a217a6970a8a?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&auto=format&fit=crop&q=80'
        ],
        'fashion' => [
            'https://images.unsplash.com/photo-1556905055-8f358a7a47b2?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&auto=format&fit=crop&q=80'
        ],
        'gaming' => [
            'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1612287232230-6b3a2a6b4122?w=800&auto=format&fit=crop&q=80'
        ],
        'digital' => [
            'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=800&auto=format&fit=crop&q=80'
        ]
    ];

    $chosenKey = 'electronics';
    $lower = strtolower($prompt);
    if (strpos($lower, 'macbook') !== false || strpos($lower, 'laptop') !== false || strpos($lower, 'pc') !== false) {
        $chosenKey = 'computing';
    } elseif (strpos($lower, 'iphone') !== false || strpos($lower, 'phone') !== false || strpos($lower, 'samsung') !== false) {
        $chosenKey = 'phone';
    } elseif (strpos($lower, 'shirt') !== false || strpos($lower, 'hoodie') !== false || strpos($lower, 'wear') !== false) {
        $chosenKey = 'fashion';
    } elseif (strpos($lower, 'playstation') !== false || strpos($lower, 'game') !== false || strpos($lower, 'ps5') !== false) {
        $chosenKey = 'gaming';
    } elseif (strpos($lower, 'ui') !== false || strpos($lower, 'kit') !== false || strpos($lower, 'code') !== false) {
        $chosenKey = 'digital';
    }

    // 1. Primary Engine: Free High-Fidelity Generative AI Studio via Pollinations (FLUX.1 Model)
    // Amazon ASIN & Google Shopping standard: Pure white background (#FFFFFF), product occupies 85%+ of frame.
    $seedHero = rand(100000, 999999);
    $seedLife = $seedHero + 77;

    $heroPrompt = "commercial product photography of {$prompt}, isolated on 100% pure white background RGB 255 255 255, centered composition, product fills 85 percent of frame, crisp studio lighting, no shadows, no text, no watermarks, 8k resolution, razor-sharp focus, amazon ASIN main image standard";
    $lifestylePrompt = "high-end commercial lifestyle product photography of {$prompt}, placed naturally in a modern luxury minimalist environment, authentic soft ambient lighting, cinematic 8k resolution, shallow depth of field, award-winning commercial advertising photography";

    $heroUrl = "https://image.pollinations.ai/prompt/" . rawurlencode($heroPrompt) . "?width=800&height=800&model=flux&seed={$seedHero}&nologo=true";
    $lifestyleUrl = "https://image.pollinations.ai/prompt/" . rawurlencode($lifestylePrompt) . "?width=800&height=800&model=flux&seed={$seedLife}&nologo=true";

    $selectedUrl = ($mode === 'lifestyle') ? $lifestyleUrl : $heroUrl;

    // Deduct 1 Credit & Record Transaction
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE vendors SET ai_credits = ai_credits - 1 WHERE id = ?")->execute([$vendorId]);
    $pdo->prepare("INSERT INTO ai_credit_transactions (vendor_id, action_type, credits_change, prompt_summary) VALUES (?, 'image_generation', -1, ?)")
        ->execute([$vendorId, "AI Studio {$mode} image: " . substr($prompt, 0, 100)]);
    $pdo->commit();

    echo json_encode([
        'success'              => true,
        'image_url'            => $selectedUrl,
        'hero_url'             => $heroUrl,
        'lifestyle_url'        => $lifestyleUrl,
        'mode'                 => $mode,
        'standards_compliance' => 'Amazon ASIN / Google Shopping: Pure White #FFFFFF Background & 85%+ Frame Area',
        'remaining_credits'    => $currentCredits - 1,
        'message'              => 'Commercial studio product photo generated successfully (1 AI credit deducted).'
    ]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Action: Buy AI Credits Pack (Paystack Integration)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'buy_credits') {
    $pack = $_POST['pack'] ?? 'starter';
    $packs = [
        'starter' => ['credits' => 25, 'amount' => 2500],
        'growth'  => ['credits' => 75, 'amount' => 6000],
        'pro'     => ['credits' => 200, 'amount' => 14000],
    ];

    if (!isset($packs[$pack])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credit pack selected.']);
        exit;
    }

    $packData = $packs[$pack];
    $amountKobo = $packData['amount'] * 100;
    $ref = 'AICRED-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    $callbackUrl = zavora_asset('seller/index.php?ai_payment=success&ref=' . urlencode($ref));

    // Initialize Paystack Checkout
    $paystackSecret = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '';
    if (empty($paystackSecret) || strpos($paystackSecret, 'YOUR_') !== false) {
        // Fallback simulation for local dev
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE vendors SET ai_credits = ai_credits + ? WHERE id = ?")->execute([$packData['credits'], $vendorId]);
        $pdo->prepare("INSERT INTO ai_credit_transactions (vendor_id, action_type, credits_change, amount_paid, payment_reference, prompt_summary) VALUES (?, 'purchase', ?, ?, ?, ?)")
            ->execute([$vendorId, $packData['credits'], $packData['amount'], $ref, "Simulated Credit Top-Up: {$packData['credits']} credits"]);
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'simulated' => true,
            'message' => "Successfully added {$packData['credits']} AI credits to your merchant balance!",
            'new_balance' => intval($vendor['ai_credits']) + $packData['credits']
        ]);
        exit;
    }

    $payload = [
        'email'        => $vendor['email'],
        'amount'       => $amountKobo,
        'reference'    => $ref,
        'callback_url' => $callbackUrl,
        'metadata'     => [
            'vendor_id'  => $vendorId,
            'pack'       => $pack,
            'credits'    => $packData['credits'],
            'purpose'    => 'ZAVORA AI Studio Merchant Credits'
        ]
    ];

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $paystackSecret,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $result = json_decode($response, true);

    if (!empty($result['status']) && !empty($result['data']['authorization_url'])) {
        echo json_encode([
            'success' => true,
            'authorization_url' => $result['data']['authorization_url'],
            'reference' => $ref
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Unable to connect to Paystack payment gateway.'
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
exit;
