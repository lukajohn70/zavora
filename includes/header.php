<?php
require_once __DIR__ . '/../config/config.php';
$cartTotals = zavora_cart_totals();
$cartCount = $cartTotals['item_count'];
$pageTitle = isset($title) ? $title . ' — ZAVORA Marketplace' : 'ZAVORA — Everything you need. One place.';

// Check session states
$activeCustomerEmail = zavora_customer_auth();
$activeVendor = zavora_vendor_auth();
$activeAdmin = zavora_admin_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <meta name="description" content="ZAVORA is a premier Nigerian multi-category digital marketplace for physical products, digital creative assets, templates, and online courses. A John Luka Media company.">
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?php echo zavora_asset('assets/images/symbol.svg'); ?>">
  <link rel="shortcut icon" href="<?php echo zavora_asset('favicon.ico'); ?>">

  <!-- Google Fonts: Inter & Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800;900&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <!-- Tailwind CSS CDN with Figma Design Tokens -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            zavora: {
              navy: '#0B132B',
              blue: '#1B2299',
              magenta: '#E4306D',
              gold: '#F7DE7A',
              bg: '#F8F9FC',
              text: '#171B2D',
              muted: '#687083',
              border: '#E7E9F0',
              success: '#16865B',
              warning: '#D99024',
              error: '#D64545',
            }
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            display: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
          }
        }
      }
    }
  </script>

  <style>
    *, *::before, *::after {
      box-sizing: border-box;
    }
    html, body {
      max-width: 100%;
      overflow-x: hidden;
      -webkit-text-size-adjust: 100%;
      -webkit-tap-highlight-color: transparent;
    }
    body {
      background-color: #F8F9FC;
      color: #171B2D;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-width: 0;
      width: 100%;
    }
    .font-display {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }
    /* Prevent mobile scroll chaining & horizontal bounce */
    .overflow-x-auto {
      -webkit-overflow-scrolling: touch;
      overscroll-behavior-x: contain;
      max-width: 100%;
    }
    /* Ensure all responsive images & media stay strictly contained */
    img, video, canvas, svg {
      max-width: 100%;
      height: auto;
    }
    /* Hide scrollbar while preserving smooth touch scrolling */
    .scrollbar-none::-webkit-scrollbar {
      display: none;
    }
    .scrollbar-none {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col antialiased overflow-x-hidden">

  <!-- 1. Top Announcement Bar -->
  <div class="bg-[#0B132B] text-white text-xs py-2 px-3 sm:px-4 border-b border-white/10">
    <div class="container mx-auto flex flex-col sm:flex-row justify-between items-center gap-1.5 sm:gap-2">
      <div class="flex items-center gap-2 sm:gap-3 text-center sm:text-left">
        <span class="inline-flex items-center gap-1.5 font-semibold text-[#F7DE7A]">
          <i class="fas fa-bolt text-xs"></i> ZAVORA Global Marketplace
        </span>
        <span class="hidden md:inline text-gray-400">|</span>
        <span class="hidden md:inline text-gray-300">Fast Nationwide Delivery • Verified Digital Delivery • Academy LMS Integrated</span>
      </div>
      <div class="flex flex-wrap items-center justify-center sm:justify-end gap-x-3 gap-y-1 text-gray-300 text-[11px] sm:text-xs">
        <?php if ($activeAdmin): ?>
          <a href="<?php echo zavora_asset('admin/index.php'); ?>" class="text-[#E4306D] hover:underline transition-colors inline-flex items-center gap-1 font-bold whitespace-nowrap">
            <i class="fas fa-shield-halved text-[11px]"></i>
            <span>Admin Console</span>
          </a>
          <span class="text-white/20">|</span>
        <?php endif; ?>

        <?php if ($activeVendor): ?>
          <a href="<?php echo zavora_asset('seller/index.php'); ?>" class="text-[#F7DE7A] hover:underline transition-colors inline-flex items-center gap-1 font-bold whitespace-nowrap">
            <i class="fas fa-store text-[11px]"></i>
            <span><?php echo htmlspecialchars($activeVendor['store_name']); ?></span>
          </a>
        <?php else: ?>
          <a href="<?php echo zavora_asset('seller/index.php'); ?>" class="text-[#F7DE7A] hover:underline transition-colors inline-flex items-center gap-1 font-bold whitespace-nowrap">
            <i class="fas fa-shop text-[11px]"></i>
            <span>Sell on ZAVORA</span>
          </a>
        <?php endif; ?>
        <span class="text-white/20">|</span>
        <a href="<?php echo htmlspecialchars(JLM_CORPORATE_URL); ?>" target="_blank" rel="noopener" class="hover:text-[#F7DE7A] transition-colors inline-flex items-center gap-1 font-medium whitespace-nowrap">
          <span>A JLM Company</span>
          <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
        </a>
        <span class="hidden sm:inline">•</span>
        <span class="font-bold text-[#F7DE7A] whitespace-nowrap">NGN (₦)</span>
      </div>
    </div>
  </div>

  <!-- 2. Main Navigation Bar -->
  <header class="sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-[#E7E9F0] shadow-sm">
    <div class="container mx-auto px-4 lg:px-8 py-3 sm:py-3.5 flex items-center justify-between gap-3 sm:gap-8">
      
      <!-- Brand Logo -->
      <a href="<?php echo zavora_asset('index.php'); ?>" class="flex-shrink-0 flex items-center hover:opacity-95 transition-opacity">
        <img src="<?php echo zavora_asset('assets/images/logo.svg'); ?>" alt="ZAVORA" class="h-7 sm:h-9 w-auto block" />
      </a>

      <!-- Large Center Search Bar (Desktop / Tablet) -->
      <form action="<?php echo zavora_asset('shop.php'); ?>" method="GET" class="hidden md:flex flex-1 max-w-2xl relative">
        <div class="relative w-full flex items-center">
          <input type="text" name="q" placeholder="Search physical goods, digital assets, online courses..." 
                 value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                 class="w-full pl-11 pr-24 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299] focus:border-transparent transition-all shadow-inner" />
          <i class="fas fa-search absolute left-4 text-gray-400 text-sm"></i>
          <button type="submit" class="absolute right-1.5 px-4 py-1.5 bg-[#1B2299] hover:bg-[#0B132B] text-white rounded-lg text-xs font-bold transition-colors">
            Search
          </button>
        </div>
      </form>

      <!-- Header Utilities -->
      <div class="flex items-center gap-1.5 sm:gap-4 flex-shrink-0">
        <!-- Account -->
        <a href="<?php echo zavora_asset('account/index.php'); ?>" class="flex items-center gap-2 p-2 sm:px-3 sm:py-2 rounded-xl text-gray-700 hover:text-[#1B2299] hover:bg-gray-50 transition-colors" title="Account">
          <i class="far fa-user text-base sm:text-lg"></i>
          <span class="hidden lg:inline text-xs font-bold leading-tight text-left">
            Account<br>
            <span class="text-[11px] font-normal text-gray-500">
              <?php if ($activeCustomerEmail): ?>
                <span class="text-emerald-600 font-bold">Signed In</span>
              <?php else: ?>
                Sign In
              <?php endif; ?>
            </span>
          </span>
        </a>

        <!-- Academy Direct Shortcut -->
        <a href="<?php echo zavora_asset('academy.php'); ?>" class="hidden sm:flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold bg-[#1B2299]/5 text-[#1B2299] border border-[#1B2299]/15 hover:bg-[#1B2299] hover:text-white transition-all">
          <i class="fas fa-graduation-cap"></i>
          <span>Academy</span>
        </a>

        <!-- Wishlist Icon with Dynamic Badge -->
        <?php $wishlistCount = zavora_wishlist_count(); ?>
        <a href="<?php echo zavora_asset('wishlist.php'); ?>" 
           title="Saved Wishlist" 
           class="relative flex items-center gap-1.5 p-2 sm:px-3 sm:py-2 rounded-xl text-gray-700 hover:text-rose-600 hover:bg-rose-50/50 transition-colors">
          <i class="<?php echo $wishlistCount > 0 ? 'fas text-rose-500' : 'far text-gray-600'; ?> fa-heart text-base"></i>
          <span class="hidden md:inline text-xs font-bold">Wishlist</span>
          <span id="headerWishlistBadge" class="<?php echo $wishlistCount > 0 ? '' : 'hidden'; ?> bg-rose-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full ml-0.5 leading-none">
            <?php echo $wishlistCount; ?>
          </span>
        </a>

        <!-- Cart Icon with Badge -->
        <a href="<?php echo zavora_asset('cart.php'); ?>" class="relative flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 py-2 rounded-xl bg-gradient-to-r from-[#1B2299] to-[#0B132B] text-white font-bold text-xs shadow-md hover:shadow-lg transition-all">
          <i class="fas fa-bag-shopping text-sm"></i>
          <span class="hidden sm:inline">Cart</span>
          <span class="bg-[#E4306D] text-white text-[10px] font-black px-1.5 py-0.5 rounded-full">
            <?php echo $cartCount; ?>
          </span>
        </a>
      </div>
    </div>

    <!-- Dedicated Mobile Search Bar (Mobile Only) -->
    <div class="md:hidden px-4 pb-2.5 pt-0.5">
      <form action="<?php echo zavora_asset('shop.php'); ?>" method="GET" class="relative w-full">
        <input type="text" name="q" placeholder="Search products, digital assets, courses..." 
               value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
               class="w-full pl-9 pr-20 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#1B2299] focus:border-transparent transition-all shadow-inner" />
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <button type="submit" class="absolute right-1 top-1/2 -translate-y-1/2 px-3 py-1 bg-[#1B2299] text-white rounded-lg text-[11px] font-bold">
          Search
        </button>
      </form>
    </div>

    <!-- 3. Category Strip -->
    <div class="bg-white border-t border-gray-100 overflow-x-auto text-xs font-semibold scrollbar-none w-full">
      <div class="container mx-auto px-4 lg:px-8 flex items-center space-x-4 sm:space-x-6 py-2 sm:py-2.5 whitespace-nowrap min-w-0">
        <a href="<?php echo zavora_asset('shop.php'); ?>" class="text-gray-900 hover:text-[#1B2299] flex items-center gap-1.5 font-bold flex-shrink-0">
          <i class="fas fa-bars"></i>
          <span>All Categories</span>
        </a>
        <span class="text-gray-300">|</span>
        <a href="<?php echo zavora_asset('shop.php?type=physical'); ?>" class="text-gray-600 hover:text-[#1B2299] flex-shrink-0">Physical Store</a>
        <a href="<?php echo zavora_asset('shop.php?category=electronics'); ?>" class="text-gray-600 hover:text-[#1B2299] flex-shrink-0">Electronics</a>
        <a href="<?php echo zavora_asset('shop.php?category=computing'); ?>" class="text-gray-600 hover:text-[#1B2299] flex-shrink-0">Phones & Laptops</a>
        <a href="<?php echo zavora_asset('shop.php?category=fashion'); ?>" class="text-gray-600 hover:text-[#1B2299] flex-shrink-0">Fashion</a>
        <span class="text-gray-300">|</span>
        <a href="<?php echo zavora_asset('shop.php?type=digital'); ?>" class="text-gray-600 hover:text-[#E4306D] flex items-center gap-1 flex-shrink-0">
          <i class="fas fa-download text-xs text-[#E4306D]"></i>
          <span>Digital Products & Templates</span>
        </a>
        <a href="<?php echo zavora_asset('academy.php'); ?>" class="text-[#1B2299] font-bold hover:text-[#0B132B] flex items-center gap-1 flex-shrink-0">
          <i class="fas fa-graduation-cap text-[#1B2299]"></i>
          <span>ZAVORA Academy (Courses)</span>
        </a>
        <a href="<?php echo zavora_asset('shop.php?deals=1'); ?>" class="text-[#E4306D] font-bold flex items-center gap-1 flex-shrink-0">
          <i class="fas fa-fire"></i>
          <span>Flash Deals</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content Area -->
  <main class="flex-1 pb-24 md:pb-16 min-w-0">
