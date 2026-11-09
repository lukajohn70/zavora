<?php
/**
 * ZAVORA AI Merchant Studio — Conversational Chat Copilot & Dynamic Live Canvas
 *
 * Modeled after Gemini Workspace, ChatGPT Canvas, and Claude Artifacts.
 * Features:
 * - Conversational multi-turn chat stream with step badges
 * - Suggested quick-prompt chips
 * - Real-time synchronized Live Canvas (Listing Document, Photo Gallery, Storefront Simulator)
 * - 4-Photo Generative & Online Sourced Photo Matcher
 * - 1-Click Direct Catalog Publishing
 */

require_once __DIR__ . '/../config/config.php';
$pdo = zavora_get_db();

// 1. Enforce Strict Vendor Session Control Guard
$vendor = zavora_vendor_require_auth();
$vendorId = (int)$vendor['id'];

// 2. Fetch Categories
$catsStmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name ASC");
$categories = $catsStmt->fetchAll();

$title = 'AI Merchant Studio Copilot — ZAVORA Vendor Central';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans">

  <!-- Top App Navigation Bar -->
  <header class="bg-slate-950/90 backdrop-blur border-b border-slate-800 px-4 lg:px-8 py-3 sticky top-0 z-40">
    <div class="flex items-center justify-between gap-4">
      
      <!-- Left: Logo & Studio Info -->
      <div class="flex items-center gap-3">
        <a href="<?php echo zavora_asset('seller/index.php'); ?>" 
           class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center transition" title="Back to Vendor Central">
          <i class="fas fa-arrow-left text-sm"></i>
        </a>
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-[#1B2299] via-indigo-500 to-purple-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
            <i class="fas fa-sparkles text-sm"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-sm font-black font-display text-white tracking-wide">ZAVORA AI Studio</h1>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-black bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 uppercase tracking-wider">Copilot v2.5</span>
            </div>
            <p class="text-[10px] text-slate-400 font-medium">Conversational Listing Architect & Photo Studio</p>
          </div>
        </div>
      </div>

      <!-- Center: Active Model Indicator -->
      <div class="hidden md:flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900 border border-slate-800 text-xs text-slate-300">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
        <span class="font-bold">Gemini 1.5 Flash</span>
        <span class="text-slate-600">•</span>
        <span class="text-slate-400">FLUX.1 Studio</span>
        <span class="text-slate-600">•</span>
        <span class="text-slate-400">Openverse Sourcing</span>
      </div>

      <!-- Right: Credits, Reset & Publish -->
      <div class="flex items-center gap-3">
        <!-- Credit Pill -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300">
          <i class="fas fa-coins text-xs text-amber-400"></i>
          <span class="text-xs font-black" id="headerCreditCount"><?php echo intval($vendor['ai_credits']); ?> Credits</span>
          <a href="<?php echo zavora_asset('seller/index.php#vtab-ai'); ?>" class="text-[10px] text-amber-400 hover:text-amber-300 font-bold underline">+ Top Up</a>
        </div>

        <button type="button" onclick="resetChatSession()" 
                class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
          <i class="fas fa-plus text-[10px]"></i>
          <span class="hidden sm:inline">New Session</span>
        </button>

        <button type="button" onclick="publishLiveCanvasProduct()" id="btnTopPublish"
                class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-emerald-500/20 transition flex items-center gap-2">
          <i class="fas fa-cloud-arrow-up"></i>
          <span>Publish to Store</span>
        </button>
      </div>

    </div>
  </header>

  <!-- Workspace Body: Split Screen (50% Chat Copilot | 50% Live Canvas) -->
  <main class="flex-1 flex flex-col lg:flex-row overflow-hidden">

    <!-- ══════════════════════════════════════════════════════════════════════════
         LEFT PANE: CONVERSATIONAL CHAT COPILOT (Gemini & ChatGPT Interface)
         ══════════════════════════════════════════════════════════════════════════ -->
    <section class="lg:w-1/2 flex flex-col border-b lg:border-b-0 lg:border-r border-slate-800 bg-slate-950/60 relative">

      <!-- Scrollable Message Stream -->
      <div id="chatMessageStream" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 max-h-[calc(100vh-190px)]">

        <!-- Welcome Greeting Card -->
        <div id="welcomeGreetingCard" class="p-6 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950/40 to-slate-900 border border-slate-800 text-center space-y-4 my-4">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-[#1B2299] to-purple-600 flex items-center justify-center mx-auto text-white text-2xl shadow-xl shadow-indigo-500/20">
            <i class="fas fa-wand-magic-sparkles"></i>
          </div>
          <div>
            <h2 class="text-lg font-black text-white font-display">Welcome to ZAVORA AI Studio</h2>
            <p class="text-xs text-slate-400 max-w-md mx-auto mt-1 leading-relaxed">
              Your conversational e-commerce copilot. Chat naturally to draft international GS1 listings, generate pure white studio photography, format technical specs, or optimize pricing for the Nigerian market.
            </p>
          </div>

          <!-- Suggested Quick Prompt Chips -->
          <div class="pt-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 block mb-2.5">Suggested Prompts — Click to Start:</span>
            <div class="flex flex-wrap gap-2 justify-center">
              <button type="button" onclick="sendSuggestedPrompt(this.innerText)" 
                      class="px-3 py-1.5 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-medium transition text-left">
                ✨ List a Sony WH-1000XM5 wireless ANC headphone in Midnight Black
              </button>
              <button type="button" onclick="sendSuggestedPrompt(this.innerText)" 
                      class="px-3 py-1.5 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-medium transition text-left">
                🎒 Draft luxury full-grain leather backpack with laptop compartment
              </button>
              <button type="button" onclick="sendSuggestedPrompt(this.innerText)" 
                      class="px-3 py-1.5 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-medium transition text-left">
                ⌨️ Mechanical gaming keyboard with RGB tactile blue switches
              </button>
              <button type="button" onclick="sendSuggestedPrompt(this.innerText)" 
                      class="px-3 py-1.5 rounded-full bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 text-xs font-medium transition text-left">
                📸 Generate 4 studio product photos on pure white background
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- Typing Indicator -->
      <div id="chatTypingIndicator" class="hidden px-6 py-2 flex items-center gap-2 text-xs text-indigo-400">
        <div class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-indigo-400 animate-bounce"></span>
          <span class="w-2 h-2 rounded-full bg-indigo-400 animate-bounce [animation-delay:0.2s]"></span>
          <span class="w-2 h-2 rounded-full bg-indigo-400 animate-bounce [animation-delay:0.4s]"></span>
        </div>
        <span class="font-medium text-[11px] text-slate-400">AI Studio is thinking, drafting copy & sourcing photos...</span>
      </div>

      <!-- Sticky Bottom Prompt Bar -->
      <div class="p-4 bg-slate-950 border-t border-slate-800">
        <form onsubmit="handleChatSubmit(event)" class="space-y-2">
          <div class="relative flex items-end bg-slate-900 border border-slate-700 rounded-2xl p-2 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 transition-all">
            <textarea id="chatInputText" rows="2" 
                      placeholder="Tell AI Studio what product to draft, ask for pricing advice in ₦, or request photos..."
                      onkeydown="handleTextareaKeydown(event)"
                      class="w-full bg-transparent text-sm text-slate-100 placeholder-slate-500 resize-none px-3 py-1.5 focus:outline-none"></textarea>
            
            <button type="submit" id="btnSendPrompt"
                    class="w-10 h-10 rounded-xl bg-[#1B2299] hover:bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 transition shadow-md shadow-indigo-900/40 ml-2">
              <i class="fas fa-arrow-up text-sm"></i>
            </button>
          </div>

          <div class="flex items-center justify-between text-[11px] text-slate-500 px-1">
            <div class="flex items-center gap-2">
              <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] text-slate-400 border border-slate-700 font-mono">Enter</kbd> to send</span>
              <span class="text-slate-700">•</span>
              <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-slate-800 text-[10px] text-slate-400 border border-slate-700 font-mono">Shift+Enter</kbd> new line</span>
            </div>
            <span class="text-slate-400 font-medium">1 Credit / generation</span>
          </div>
        </form>
      </div>

    </section>

    <!-- ══════════════════════════════════════════════════════════════════════════
         RIGHT PANE: DYNAMIC LIVE CANVAS & ARTIFACT WORKSPACE (50% Width)
         ══════════════════════════════════════════════════════════════════════════ -->
    <section class="lg:w-1/2 flex flex-col bg-slate-900/90 relative">

      <!-- Canvas Header & Mode Tabs -->
      <div class="flex items-center justify-between px-4 sm:px-6 py-3 border-b border-slate-800 bg-slate-900">
        <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl text-xs font-bold border border-slate-800">
          <button type="button" onclick="switchCanvasTab('document')" id="cTabBtnDoc"
                  class="px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white shadow-sm flex items-center gap-1.5 transition">
            <i class="fas fa-file-lines text-[11px]"></i>
            <span>Listing Document</span>
          </button>
          <button type="button" onclick="switchCanvasTab('photos')" id="cTabBtnPhotos"
                  class="px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 flex items-center gap-1.5 transition">
            <i class="fas fa-camera text-[11px]"></i>
            <span>Photo Studio (<span id="canvasPhotosCount">4</span>)</span>
          </button>
          <button type="button" onclick="switchCanvasTab('preview')" id="cTabBtnPreview"
                  class="px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 flex items-center gap-1.5 transition">
            <i class="fas fa-desktop text-[11px]"></i>
            <span>Storefront View</span>
          </button>
        </div>

        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Live Sync</span>
          </span>
        </div>
      </div>

      <!-- Canvas Scrollable Workspace -->
      <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-6 max-h-[calc(100vh-190px)]">

        <!-- ───────────────────────────────────────────────────────────────────
             CANVAS TAB 1: LISTING DOCUMENT
             ─────────────────────────────────────────────────────────────────── -->
        <div id="cTabDocument" class="space-y-6">

          <!-- Title Card -->
          <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
              <label class="text-[11px] font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <i class="fas fa-tag text-indigo-400"></i> GS1 Product Title
              </label>
              <span id="canvasTitleCounter" class="text-[10px] font-mono font-bold text-emerald-400 px-2 py-0.5 rounded bg-emerald-950 border border-emerald-800">
                0 / 90 chars
              </span>
            </div>
            <input type="text" id="cv_title" 
                   placeholder="Your GS1-formula product title will sync here..."
                   oninput="onCanvasTitleInput(this.value)"
                   class="w-full px-3.5 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-indigo-500">
            <p class="text-[10px] text-slate-500">Standard Formula: <code>[Brand] + [Model] + [Defining Feature] + [Color/Spec]</code></p>
          </div>

          <!-- Pricing & Stock Row -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800">
              <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Selling Price (₦)</label>
              <input type="number" id="cv_price" value="45000" oninput="updateCanvasDiscount()"
                     class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm font-black text-emerald-400 focus:outline-none">
            </div>
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800">
              <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Compare-at Price (₦)</label>
              <input type="number" id="cv_prev_price" value="60000" oninput="updateCanvasDiscount()"
                     class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-slate-500 line-through focus:outline-none">
            </div>
            <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800">
              <div class="flex items-center justify-between mb-1">
                <label class="text-[10px] font-black uppercase text-slate-400">Discount</label>
                <span id="cv_discount_badge" class="text-[10px] font-black text-rose-400 bg-rose-950/60 px-1.5 py-0.5 rounded">25% OFF</span>
              </div>
              <input type="number" id="cv_stock" value="50"
                     class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-slate-200 focus:outline-none">
            </div>
          </div>

          <!-- 5 Amazon Bullets Card ("About this item") -->
          <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
              <h4 class="text-xs font-black uppercase tracking-wider text-slate-200 flex items-center gap-2">
                <i class="fas fa-list-check text-indigo-400"></i> Amazon "About this item" 5 Bullets
              </h4>
              <span class="text-[10px] font-bold text-emerald-400">Conversion Standard</span>
            </div>
            <div class="space-y-2" id="canvasBulletsContainer">
              <input type="text" id="cv_bullet_1" placeholder="Bullet 1: CORE BENEFIT..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none">
              <input type="text" id="cv_bullet_2" placeholder="Bullet 2: PREMIUM MATERIALS..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none">
              <input type="text" id="cv_bullet_3" placeholder="Bullet 3: TECHNICAL SPECS..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none">
              <input type="text" id="cv_bullet_4" placeholder="Bullet 4: ERGONOMICS & BATTERY..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none">
              <input type="text" id="cv_bullet_5" placeholder="Bullet 5: IN THE BOX & WARRANTY..." class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none">
            </div>
          </div>

          <!-- Technical Specs Table -->
          <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
              <h4 class="text-xs font-black uppercase tracking-wider text-slate-200 flex items-center gap-2">
                <i class="fas fa-microchip text-indigo-400"></i> Technical Specifications Matrix
              </h4>
              <button type="button" onclick="addCanvasSpecRow()" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300">+ Add Metric</button>
            </div>
            <div id="canvasSpecsContainer" class="space-y-1.5 text-xs">
              <!-- Dynamically populated rows -->
            </div>
          </div>

          <!-- Narrative Story -->
          <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-300">Narrative Product Story</h4>
            <textarea id="cv_narrative" rows="3" 
                      placeholder="Storytelling overview..."
                      class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-300 leading-relaxed focus:outline-none"></textarea>
          </div>

        </div>

        <!-- ───────────────────────────────────────────────────────────────────
             CANVAS TAB 2: PHOTOGRAPHY & SOURCING GALLERY
             ─────────────────────────────────────────────────────────────────── -->
        <div id="cTabPhotos" class="hidden space-y-4">
          <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
            <div>
              <h4 class="text-xs font-black uppercase tracking-wider text-slate-200">Matching Photography Suite</h4>
              <p class="text-[10px] text-slate-400">Click any card to select it as the main thumbnail.</p>
            </div>
            <span class="text-[10px] font-bold text-purple-400 bg-purple-950 px-2 py-0.5 rounded-full border border-purple-800">FLUX.1 + Real Sourced</span>
          </div>

          <!-- 4 Photo Cards Grid -->
          <div class="grid grid-cols-2 gap-3" id="canvasPhotoGrid">
            <!-- Will be rendered by JavaScript -->
          </div>

          <!-- Active Thumbnail Input -->
          <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
            <label class="block text-[10px] font-black uppercase text-slate-400">Selected Product Thumbnail URL</label>
            <input type="url" id="cv_thumbnail_url" 
                   value="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800"
                   oninput="onCanvasThumbnailChange(this.value)"
                   class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-mono text-slate-400 focus:outline-none">
          </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────────────
             CANVAS TAB 3: STOREFRONT BUYER SIMULATOR
             ─────────────────────────────────────────────────────────────────── -->
        <div id="cTabPreview" class="hidden space-y-4">
          <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 text-xs text-slate-400 flex items-center justify-between">
            <span><i class="fas fa-desktop text-emerald-400 mr-1.5"></i> Live Storefront Mockup on <code>zavora.jlm.com.ng</code></span>
            <span class="text-[10px] font-bold text-emerald-400">Buyer View</span>
          </div>

          <!-- Storefront Product Card Mockup -->
          <div class="bg-white text-gray-900 rounded-3xl p-6 shadow-2xl border border-gray-200 space-y-4">
            <div class="aspect-square rounded-2xl bg-white border border-gray-100 flex items-center justify-center p-4 relative overflow-hidden">
              <img id="storefrontSimImg" src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800" class="w-full h-full object-contain">
              <span id="storefrontSimBadge" class="absolute top-3 left-3 px-2 py-0.5 rounded-md text-[10px] font-black bg-[#E4306D] text-white">
                -25%
              </span>
            </div>

            <div>
              <div class="flex items-center justify-between text-[11px] text-gray-400 uppercase font-bold mb-1">
                <span id="storefrontSimCategory">Electronics</span>
                <span class="text-emerald-600 flex items-center gap-1 font-bold"><i class="fas fa-shield-halved"></i> Verified Merchant</span>
              </div>

              <h3 id="storefrontSimTitle" class="text-base font-black text-gray-900 leading-snug line-clamp-2">
                Sony WH-1000XM5 — Wireless Active Noise Canceling Headphones, Midnight Black
              </h3>

              <div class="flex items-baseline gap-2.5 my-2">
                <span id="storefrontSimPrice" class="text-xl font-black text-[#1B2299]">₦45,000</span>
                <span id="storefrontSimOldPrice" class="text-xs text-gray-400 line-through">₦60,000</span>
              </div>

              <!-- About this item mini box -->
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5 text-xs text-gray-700">
                <span class="text-[10px] font-black uppercase text-gray-900 block">About this item</span>
                <div id="storefrontSimBullets" class="space-y-1 text-[11px]">
                  <!-- Bullets injected dynamically -->
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Canvas Sticky Bottom Action Footer -->
      <div class="p-4 bg-slate-950 border-t border-slate-800 flex items-center justify-between gap-3">
        <a href="<?php echo zavora_asset('seller/product-create.php'); ?>" id="btnOpenInFullEditor"
           class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition flex items-center gap-1.5">
          <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
          <span>Open in Full Editor</span>
        </a>

        <button type="button" onclick="publishLiveCanvasProduct()" id="btnBottomPublish"
                class="flex-1 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/20 transition flex items-center justify-center gap-2">
          <i class="fas fa-cloud-arrow-up"></i>
          <span>Publish Product to Storefront</span>
        </button>
      </div>

    </section>

  </main>
</div>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// Client-Side AI Studio State Manager
// ─────────────────────────────────────────────────────────────────────────────
const chatHistory = [];
let currentCanvasState = {
  title: 'Sony WH-1000XM5 — Wireless Active Noise Canceling Headphones, Midnight Black',
  price: 45000,
  previous_price: 60000,
  stock_quantity: 50,
  category_name: 'Electronics',
  thumbnail: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800',
  bullet_points: [
    'CORE BENEFIT: Industry-leading active noise cancelation with dual integrated processors and 8 microphones.',
    'PREMIUM CRAFTSMANSHIP: Soft-fit synthetic leather headband and lightweight composite architectural frame.',
    'TECHNICAL SPECS: Custom 30mm carbon fiber drivers, LDAC high-resolution wireless audio codec support.',
    'ERGONOMICS & BATTERY: Up to 30 hours continuous playback with USB-PD 3-minute quick charge for 3 hours.',
    'IN THE BOX & WARRANTY: Protective hard case, USB-C cable, 3.5mm audio lead, and 12-Month Official Warranty.'
  ],
  specifications_map: {
    'Brand': 'Sony',
    'Series': 'WH-1000XM5',
    'Connectivity': 'Bluetooth 5.3 & 3.5mm Aux',
    'Battery Life': '30 Hours Continuous Playback',
    'Driver Unit': '30mm Carbon Composite',
    'Warranty': '12-Month Official Manufacturer Warranty'
  },
  narrative_description: 'Experience pure sonic immersion and distraction-free audio engineering. The Sony WH-1000XM5 combines cutting-edge noise cancelation with luxurious ergonomic comfort.',
  photo_matches: [
    {
      id: 'hero',
      type: 'ai_hero',
      title: 'Studio Hero (Pure White #FFFFFF • 85%+ fill)',
      url: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800',
      badge: 'Amazon ASIN Standard'
    },
    {
      id: 'lifestyle',
      type: 'ai_lifestyle',
      title: 'Lifestyle In-Context',
      url: 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=800',
      badge: 'Commercial Context'
    }
  ]
};

// ─────────────────────────────────────────────────────────────────────────────
// Chat Interaction Handlers
// ─────────────────────────────────────────────────────────────────────────────
function handleTextareaKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    handleChatSubmit(e);
  }
}

function sendSuggestedPrompt(text) {
  document.getElementById('chatInputText').value = text;
  handleChatSubmit(new Event('submit'));
}

function handleChatSubmit(e) {
  if (e) e.preventDefault();
  const inputEl = document.getElementById('chatInputText');
  const message = inputEl.value.trim();
  if (!message) return;

  // Clear input
  inputEl.value = '';

  // Append user message to stream
  appendUserMessage(message);
  chatHistory.push({ role: 'user', content: message });

  // Hide welcome greeting card once conversation starts
  const welcomeCard = document.getElementById('welcomeGreetingCard');
  if (welcomeCard) welcomeCard.classList.add('hidden');

  // Show typing indicator
  showTypingIndicator(true);

  // Send to backend chat copilot endpoint
  const fd = new FormData();
  fd.append('message', message);
  fd.append('history', JSON.stringify(chatHistory));
  fd.append('canvas_state', JSON.stringify(currentCanvasState));

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=chat"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    showTypingIndicator(false);

    if (data.success) {
      appendAssistantMessage(data.reply, data.badges || []);
      chatHistory.push({ role: 'assistant', content: data.reply });

      // Apply Canvas Update
      if (data.canvas_update) {
        syncCanvasUpdate(data.canvas_update);
      }

      // Update credit balance
      if (data.remaining_credits !== undefined) {
        updateStudioCredits(data.remaining_credits);
      }
    } else if (data.error === 'insufficient_credits') {
      appendSystemAlert(data.message || '0 AI Credits remaining. Please top up your credit pack to continue chatting.');
    } else {
      appendSystemAlert(data.message || 'Error processing AI instruction.');
    }
  })
  .catch(err => {
    console.error(err);
    showTypingIndicator(false);
    appendSystemAlert('Network connection error connecting to AI Studio.');
  });
}

function appendUserMessage(text) {
  const stream = document.getElementById('chatMessageStream');
  const div = document.createElement('div');
  div.className = 'flex justify-end gap-3';
  div.innerHTML = `
    <div class="max-w-[85%] bg-gradient-to-r from-[#1B2299] to-indigo-700 text-white p-4 rounded-3xl rounded-tr-sm shadow-md text-xs leading-relaxed font-medium">
      ${escapeHtml(text)}
    </div>
    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs flex-shrink-0 font-bold">
      ${escapeHtml('<?php echo substr($vendor["store_name"], 0, 1); ?>')}
    </div>
  `;
  stream.appendChild(div);
  stream.scrollTop = stream.scrollHeight;
}

function appendAssistantMessage(text, badges = []) {
  const stream = document.getElementById('chatMessageStream');
  const div = document.createElement('div');
  div.className = 'flex items-start gap-3';

  const badgeHtml = badges.map(b => `
    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
      <i class="fas fa-check text-[9px] text-emerald-400"></i> ${escapeHtml(b)}
    </span>
  `).join('');

  div.innerHTML = `
    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-[#1B2299] to-purple-600 text-white flex items-center justify-center text-xs flex-shrink-0 shadow-md">
      <i class="fas fa-sparkles text-amber-300"></i>
    </div>
    <div class="flex-1 space-y-2 max-w-[90%]">
      ${badges.length ? `<div class="flex flex-wrap gap-1.5">${badgeHtml}</div>` : ''}
      <div class="bg-slate-900 border border-slate-800 text-slate-200 p-4 rounded-3xl rounded-tl-sm text-xs leading-relaxed">
        ${formatMarkdownText(text)}
      </div>
    </div>
  `;
  stream.appendChild(div);
  stream.scrollTop = stream.scrollHeight;
}

function appendSystemAlert(text) {
  const stream = document.getElementById('chatMessageStream');
  const div = document.createElement('div');
  div.className = 'p-3 bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs rounded-2xl flex items-center gap-2';
  div.innerHTML = `<i class="fas fa-circle-exclamation text-rose-400"></i> <span>${escapeHtml(text)}</span>`;
  stream.appendChild(div);
  stream.scrollTop = stream.scrollHeight;
}

function showTypingIndicator(show) {
  const el = document.getElementById('chatTypingIndicator');
  if (el) el.classList.toggle('hidden', !show);
}

// ─────────────────────────────────────────────────────────────────────────────
// Dynamic Live Canvas Sync
// ─────────────────────────────────────────────────────────────────────────────
function syncCanvasUpdate(update) {
  // Merge into currentCanvasState
  Object.assign(currentCanvasState, update);

  // Sync Title
  if (update.title) {
    document.getElementById('cv_title').value = update.title;
    onCanvasTitleInput(update.title);
  }

  // Sync Pricing
  if (update.price !== undefined) {
    document.getElementById('cv_price').value = update.price;
  }
  if (update.previous_price !== undefined) {
    document.getElementById('cv_prev_price').value = update.previous_price;
  }
  updateCanvasDiscount();

  // Sync Bullets
  if (Array.isArray(update.bullet_points)) {
    for (let i = 0; i < 5; i++) {
      const el = document.getElementById('cv_bullet_' + (i + 1));
      if (el) el.value = update.bullet_points[i] || '';
    }
  }

  // Sync Narrative
  if (update.narrative_description) {
    document.getElementById('cv_narrative').value = update.narrative_description;
  }

  // Sync Specs Matrix
  if (update.specifications_map && typeof update.specifications_map === 'object') {
    renderCanvasSpecs(update.specifications_map);
  }

  // Sync Photos
  if (Array.isArray(update.photo_matches) && update.photo_matches.length > 0) {
    renderCanvasPhotos(update.photo_matches);
    document.getElementById('canvasPhotosCount').innerText = update.photo_matches.length;
  }

  // Sync Thumbnail
  if (update.thumbnail) {
    document.getElementById('cv_thumbnail_url').value = update.thumbnail;
    onCanvasThumbnailChange(update.thumbnail);
  }

  // Sync Storefront Preview
  renderStorefrontSimulator();
}

function onCanvasTitleInput(val) {
  const len = (val || '').length;
  const counter = document.getElementById('canvasTitleCounter');
  if (counter) {
    counter.innerText = len + ' / 90 chars';
    counter.className = 'text-[10px] font-mono font-bold px-2 py-0.5 rounded ' + (len >= 60 && len <= 90 ? 'text-emerald-400 bg-emerald-950 border border-emerald-800' : 'text-amber-400 bg-amber-950 border border-amber-800');
  }
  renderStorefrontSimulator();
}

function updateCanvasDiscount() {
  const p = parseFloat(document.getElementById('cv_price').value) || 0;
  const prev = parseFloat(document.getElementById('cv_prev_price').value) || 0;
  const badge = document.getElementById('cv_discount_badge');

  if (prev > p && p > 0) {
    const pct = Math.round(((prev - p) / prev) * 100);
    badge.innerText = pct + '% OFF';
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
  renderStorefrontSimulator();
}

function renderCanvasSpecs(specsMap) {
  const container = document.getElementById('canvasSpecsContainer');
  if (!container) return;

  container.innerHTML = Object.entries(specsMap).map(([k, v]) => `
    <div class="grid grid-cols-12 gap-2 cv-spec-row">
      <input type="text" value="${escapeHtml(k)}" class="col-span-5 px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-slate-400 font-bold focus:outline-none">
      <input type="text" value="${escapeHtml(v)}" class="col-span-6 px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-slate-200 font-semibold focus:outline-none">
      <button type="button" onclick="this.closest('.cv-spec-row').remove()" class="col-span-1 text-slate-600 hover:text-rose-400 transition"><i class="fas fa-trash-can text-xs"></i></button>
    </div>
  `).join('');
}

function addCanvasSpecRow() {
  const container = document.getElementById('canvasSpecsContainer');
  const div = document.createElement('div');
  div.className = 'grid grid-cols-12 gap-2 cv-spec-row';
  div.innerHTML = `
    <input type="text" placeholder="Metric" class="col-span-5 px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-slate-400 font-bold focus:outline-none">
    <input type="text" placeholder="Value" class="col-span-6 px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-slate-200 font-semibold focus:outline-none">
    <button type="button" onclick="this.closest('.cv-spec-row').remove()" class="col-span-1 text-slate-600 hover:text-rose-400 transition"><i class="fas fa-trash-can text-xs"></i></button>
  `;
  container.appendChild(div);
}

function renderCanvasPhotos(photos) {
  const grid = document.getElementById('canvasPhotoGrid');
  if (!grid || !Array.isArray(photos)) return;

  grid.innerHTML = photos.map((p, idx) => `
    <div onclick="selectCanvasThumbnail('${escapeHtml(p.url)}', this)" 
         class="cv-photo-card cursor-pointer p-2 rounded-2xl border-2 ${idx === 0 ? 'border-indigo-500 bg-slate-950' : 'border-slate-800 bg-slate-950'} transition hover:border-indigo-400 relative">
      <div class="aspect-square rounded-xl ${p.type === 'ai_hero' ? 'bg-white' : 'bg-slate-900'} border border-slate-800 flex items-center justify-center p-2 mb-1.5 overflow-hidden">
        <img src="${escapeHtml(p.url)}" class="w-full h-full ${p.type === 'ai_hero' ? 'object-contain' : 'object-cover'}">
      </div>
      <div class="flex items-center justify-between text-[10px]">
        <span class="font-bold text-slate-300 truncate">${escapeHtml(p.badge || p.title)}</span>
        <i class="fas ${idx === 0 ? 'fa-check-circle text-indigo-400' : 'fa-circle text-slate-700'} cv-photo-check"></i>
      </div>
    </div>
  `).join('');
}

function selectCanvasThumbnail(url, cardEl) {
  document.getElementById('cv_thumbnail_url').value = url;
  onCanvasThumbnailChange(url);

  document.querySelectorAll('.cv-photo-card').forEach(c => {
    c.classList.remove('border-indigo-500');
    c.classList.add('border-slate-800');
    const ic = c.querySelector('.cv-photo-check');
    if (ic) ic.className = 'fas fa-circle text-slate-700 cv-photo-check';
  });

  if (cardEl) {
    cardEl.classList.add('border-indigo-500');
    cardEl.classList.remove('border-slate-800');
    const ic = cardEl.querySelector('.cv-photo-check');
    if (ic) ic.className = 'fas fa-check-circle text-indigo-400 cv-photo-check';
  }
}

function onCanvasThumbnailChange(url) {
  currentCanvasState.thumbnail = url;
  const sim = document.getElementById('storefrontSimImg');
  if (sim && url) sim.src = url;
}

function renderStorefrontSimulator() {
  const title = document.getElementById('cv_title').value || currentCanvasState.title;
  const price = parseFloat(document.getElementById('cv_price').value) || 0;
  const prevPrice = parseFloat(document.getElementById('cv_prev_price').value) || 0;
  const thumb = document.getElementById('cv_thumbnail_url').value || currentCanvasState.thumbnail;

  const simTitle = document.getElementById('storefrontSimTitle');
  const simPrice = document.getElementById('storefrontSimPrice');
  const simOldPrice = document.getElementById('storefrontSimOldPrice');
  const simBadge = document.getElementById('storefrontSimBadge');
  const simImg = document.getElementById('storefrontSimImg');
  const simBullets = document.getElementById('storefrontSimBullets');

  if (simTitle) simTitle.innerText = title;
  if (simPrice) simPrice.innerText = '₦' + price.toLocaleString();
  if (simImg) simImg.src = thumb;

  if (prevPrice > price && price > 0) {
    const pct = Math.round(((prevPrice - price) / prevPrice) * 100);
    simOldPrice.innerText = '₦' + prevPrice.toLocaleString();
    simOldPrice.classList.remove('hidden');
    simBadge.innerText = '-' + pct + '%';
    simBadge.classList.remove('hidden');
  } else {
    simOldPrice.classList.add('hidden');
    simBadge.classList.add('hidden');
  }

  // 5 Bullets
  if (simBullets) {
    const bullets = [];
    for (let i = 1; i <= 5; i++) {
      const b = document.getElementById('cv_bullet_' + i)?.value;
      if (b) bullets.push(b);
    }
    simBullets.innerHTML = bullets.map(b => `
      <div class="flex items-start gap-1.5">
        <i class="fas fa-check-circle text-emerald-600 mt-0.5 text-[10px]"></i>
        <span>${escapeHtml(b)}</span>
      </div>
    `).join('');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Canvas Tab Switcher
// ─────────────────────────────────────────────────────────────────────────────
function switchCanvasTab(tab) {
  document.getElementById('cTabDocument').classList.add('hidden');
  document.getElementById('cTabPhotos').classList.add('hidden');
  document.getElementById('cTabPreview').classList.add('hidden');

  document.getElementById('cTabBtnDoc').className = 'px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 flex items-center gap-1.5 transition';
  document.getElementById('cTabBtnPhotos').className = 'px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 flex items-center gap-1.5 transition';
  document.getElementById('cTabBtnPreview').className = 'px-3.5 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 flex items-center gap-1.5 transition';

  if (tab === 'document') {
    document.getElementById('cTabDocument').classList.remove('hidden');
    document.getElementById('cTabBtnDoc').className = 'px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white shadow-sm flex items-center gap-1.5 transition';
  } else if (tab === 'photos') {
    document.getElementById('cTabPhotos').classList.remove('hidden');
    document.getElementById('cTabBtnPhotos').className = 'px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white shadow-sm flex items-center gap-1.5 transition';
  } else if (tab === 'preview') {
    document.getElementById('cTabPreview').classList.remove('hidden');
    document.getElementById('cTabBtnPreview').className = 'px-3.5 py-1.5 rounded-lg bg-indigo-600 text-white shadow-sm flex items-center gap-1.5 transition';
    renderStorefrontSimulator();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1-Click Direct Catalog Publishing
// ─────────────────────────────────────────────────────────────────────────────
function publishLiveCanvasProduct() {
  const title = document.getElementById('cv_title').value.trim();
  const price = parseFloat(document.getElementById('cv_price').value);
  const prevPrice = parseFloat(document.getElementById('cv_prev_price').value) || null;
  const stock = parseInt(document.getElementById('cv_stock').value, 10) || 50;
  const thumbnail = document.getElementById('cv_thumbnail_url').value.trim();
  const narrative = document.getElementById('cv_narrative').value.trim();

  if (!title) {
    alert('Please enter a product title before publishing.');
    return;
  }
  if (!price || price <= 0) {
    alert('Please set a valid price in Naira.');
    return;
  }

  const bullets = [];
  for (let i = 1; i <= 5; i++) {
    const val = document.getElementById('cv_bullet_' + i)?.value.trim();
    if (val) bullets.push(val);
  }

  // Collect specs
  const specsMap = {};
  document.querySelectorAll('.cv-spec-row').forEach(row => {
    const inputs = row.querySelectorAll('input');
    const k = inputs[0]?.value.trim();
    const v = inputs[1]?.value.trim();
    if (k && v) specsMap[k] = v;
  });

  const btn = document.getElementById('btnBottomPublish');
  const btnTop = document.getElementById('btnTopPublish');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing to ZAVORA...';

  const fd = new FormData();
  fd.append('title', title);
  fd.append('price', price);
  if (prevPrice) fd.append('previous_price', prevPrice);
  fd.append('stock_quantity', stock);
  fd.append('thumbnail', thumbnail);
  fd.append('narrative_description', narrative);
  fd.append('bullet_points', JSON.stringify(bullets));
  fd.append('specifications_map', JSON.stringify(specsMap));

  fetch('<?php echo zavora_asset("api/ai_studio.php?action=publish_canvas_product"); ?>', {
    method: 'POST',
    body: fd
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      appendAssistantMessage(`🎉 **Congratulations!** Your product **${data.title}** is now published live on ZAVORA Storefront! [Click here to view live listing](${data.view_url})`, ['Live on Catalog', `SKU: ${data.sku}`]);
      alert(`Success! Product '${data.title}' is now live on ZAVORA.`);
      window.location.href = data.view_url;
    } else {
      alert(data.message || 'Publishing failed.');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network error publishing product.');
  })
  .finally(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> <span>Publish Product to Storefront</span>';
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Reset Session
// ─────────────────────────────────────────────────────────────────────────────
function resetChatSession() {
  if (confirm('Start a new AI Studio session? Your chat will be cleared.')) {
    window.location.reload();
  }
}

function updateStudioCredits(credits) {
  const c = parseInt(credits, 10);
  const el = document.getElementById('headerCreditCount');
  if (el) el.innerText = c + ' Credits';
}

function formatMarkdownText(md) {
  if (!md) return '';
  return md
    .replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-white">$1</strong>')
    .replace(/\*(.*?)\*/g, '<em class="text-slate-300">$1</em>')
    .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" class="text-indigo-400 underline font-bold hover:text-indigo-300">$1</a>')
    .replace(/\n\n/g, '<br><br>')
    .replace(/\n/g, '<br>');
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, function(m) {
    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
  });
}

// Initial Sync on load
document.addEventListener('DOMContentLoaded', function() {
  syncCanvasUpdate(currentCanvasState);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
