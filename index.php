<?php
$title = "Everything you need. One place.";
require_once __DIR__ . '/includes/header.php';

$pdo = zavora_get_db();

// 1. Fetch Flash Deals & Featured Products
$dealsStmt = $pdo->query("SELECT * FROM products WHERE is_flash_deal = 1 ORDER BY id DESC LIMIT 4");
$flashDeals = $dealsStmt->fetchAll();

// 2. Fetch Hero Showcase Product (100% Database-Driven)
$heroStmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_featured = 1 ORDER BY p.is_boosted DESC, p.rating DESC, p.id DESC LIMIT 1");
$heroProduct = $heroStmt->fetch();
if (!$heroProduct) {
    $heroProduct = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 1")->fetch();
}

// 3. Fetch Trending Products
$trendStmt = $pdo->query("SELECT * FROM products WHERE is_featured = 1 ORDER BY rating DESC LIMIT 6");
$trendingProducts = $trendStmt->fetchAll();

// 4. Fetch Digital Products
$digiStmt = $pdo->query("SELECT * FROM products WHERE type = 'digital' ORDER BY id DESC LIMIT 3");
$digitalProducts = $digiStmt->fetchAll();

// 5. Fetch Live Academy Courses (from Learnerium API)
$academyCourses = zavora_get_academy_courses(3);

// 6. Fetch Categories
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $catStmt->fetchAll();
?>

<!-- Section 1: Hero Banner -->
<section class="relative bg-gradient-to-br from-[#0B132B] via-[#1B2299] to-[#0B132B] text-white py-14 sm:py-20 lg:py-28 overflow-hidden">
  <!-- Glowing ambient orb accents -->
  <div class="pointer-events-none absolute -top-40 -right-20 w-96 h-96 bg-[#E4306D]/30 rounded-full blur-3xl"></div>
  <div class="pointer-events-none absolute -bottom-40 -left-20 w-96 h-96 bg-[#F7DE7A]/15 rounded-full blur-3xl"></div>

  <div class="container mx-auto px-4 lg:px-8 relative z-10">
    <div class="grid lg:grid-cols-12 gap-12 items-center">
      
      <!-- Left Hero Copy -->
      <div class="lg:col-span-7 text-center lg:text-left">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[#F7DE7A] text-xs font-bold mb-6">
          <span class="w-2 h-2 rounded-full bg-[#E4306D] animate-ping"></span>
          <span>Next-Generation Nigerian Commerce & Academy</span>
        </div>
        
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black font-display tracking-tight text-white mb-6 leading-tight">
          Everything you need.<br>
          <span class="bg-gradient-to-r from-white via-[#F7DE7A] to-[#E4306D] bg-clip-text text-transparent">One unified place.</span>
        </h1>
        
        <p class="text-base sm:text-lg text-gray-200 max-w-xl mx-auto lg:mx-0 leading-relaxed mb-8">
          Discover genuine physical products, professional digital assets, and high-impact technology courses — powered by John Luka Media with seamless Paystack checkout.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
          <a href="<?php echo zavora_asset('shop.php'); ?>" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-[#E4306D] hover:bg-[#c2285a] text-white font-bold text-sm shadow-xl hover:scale-105 transition-all text-center flex items-center justify-center gap-2">
            <span>Shop Marketplace</span>
            <i class="fas fa-arrow-right text-xs"></i>
          </a>
          <a href="<?php echo zavora_asset('academy.php'); ?>" class="w-full sm:w-auto px-7 py-3.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-sm backdrop-blur-sm transition-all text-center flex items-center justify-center gap-2">
            <i class="fas fa-graduation-cap text-[#F7DE7A]"></i>
            <span>Explore Academy</span>
          </a>
        </div>

        <!-- Micro Trust Signals -->
        <div class="mt-10 pt-8 border-t border-white/10 grid grid-cols-3 gap-4 text-center lg:text-left">
          <div>
            <div class="text-xl sm:text-2xl font-black text-white">100%</div>
            <div class="text-[11px] text-gray-300 font-medium">Authentic Goods</div>
          </div>
          <div>
            <div class="text-xl sm:text-2xl font-black text-[#F7DE7A]">Instant</div>
            <div class="text-[11px] text-gray-300 font-medium">Digital Delivery</div>
          </div>
          <div>
            <div class="text-xl sm:text-2xl font-black text-[#E4306D]">LMS</div>
            <div class="text-[11px] text-gray-300 font-medium">Synced Enrolment</div>
          </div>
        </div>

      </div>

      <!-- Right Hero Visual Grid (100% Database Driven) -->
      <div class="lg:col-span-5 relative">
        <div class="relative mx-auto max-w-md">
          <?php if (!empty($heroProduct)): ?>
            <!-- Main Hero Card Preview -->
            <a href="<?php echo zavora_asset('product.php?id=' . $heroProduct['id']); ?>" class="block rounded-3xl bg-white/10 backdrop-blur-xl border border-white/20 p-6 shadow-2xl hover:border-white/40 transition-all duration-300 group">
              <div class="relative rounded-2xl overflow-hidden aspect-[4/3] mb-4 bg-gray-900">
                <img src="<?php echo htmlspecialchars($heroProduct['thumbnail']); ?>" alt="<?php echo htmlspecialchars($heroProduct['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                <span class="absolute top-3 left-3 bg-[#E4306D] text-white text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                  <?php echo !empty($heroProduct['is_boosted']) ? 'Featured Spotlight' : 'Marketplace Pick'; ?>
                </span>
                <?php if (!empty($heroProduct['is_preorder'])): ?>
                  <span class="absolute top-3 right-3 bg-indigo-600 text-white text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                    Pre-Order
                  </span>
                <?php endif; ?>
              </div>
              <div class="flex items-center justify-between text-white">
                <div class="pr-3">
                  <h4 class="font-bold text-sm text-white line-clamp-1 group-hover:text-[#F7DE7A] transition-colors"><?php echo htmlspecialchars($heroProduct['title']); ?></h4>
                  <p class="text-xs text-gray-300 line-clamp-1"><?php echo htmlspecialchars($heroProduct['category_name'] ?? 'Flagship Hardware'); ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                  <span class="text-base font-black text-[#F7DE7A]"><?php echo zavora_format_price($heroProduct['price']); ?></span>
                  <?php if (!empty($heroProduct['previous_price'])): ?>
                    <span class="block text-[11px] text-gray-400 line-through"><?php echo zavora_format_price($heroProduct['previous_price']); ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endif; ?>

          <!-- Floating badge: Course Academy -->
          <div class="absolute -bottom-6 -left-6 bg-white text-gray-900 p-4 rounded-2xl shadow-2xl border border-gray-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#1B2299] text-white flex items-center justify-center font-bold">
              <i class="fas fa-graduation-cap"></i>
            </div>
            <div>
              <span class="text-[10px] font-bold uppercase text-[#1B2299] block">Academy Integrated</span>
              <span class="text-xs font-bold text-gray-900">Auto-Enrolment Active</span>
            </div>
          </div>

          <!-- Floating badge: Secure Payment -->
          <div class="absolute -top-6 -right-6 bg-white text-gray-900 px-4 py-3 rounded-2xl shadow-2xl border border-gray-100 flex items-center gap-2">
            <i class="fas fa-lock text-[#16865B]"></i>
            <span class="text-xs font-bold">Encrypted Checkout</span>
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- Section 2: Shop by Category -->
<section class="py-14 sm:py-16 bg-white border-b border-gray-200">
  <div class="container mx-auto px-4 lg:px-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h2 class="text-2xl sm:text-3xl font-black font-display text-gray-900">Shop by Category</h2>
        <p class="text-sm text-gray-500 mt-1">Explore carefully organized physical and digital product departments.</p>
      </div>
      <a href="<?php echo zavora_asset('shop.php'); ?>" class="text-xs font-bold text-[#1B2299] hover:underline flex items-center gap-1">
        <span>View All</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
      <?php foreach ($categories as $cat): ?>
        <a href="<?php echo zavora_asset('shop.php?category=' . urlencode($cat['slug'])); ?>" 
           class="group p-5 rounded-2xl bg-gray-50 hover:bg-white border border-gray-150 hover:border-[#1B2299]/30 shadow-sm hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 flex flex-col items-center text-center">
          <div class="w-14 h-14 rounded-2xl bg-white group-hover:bg-[#1B2299] text-[#1B2299] group-hover:text-white border border-gray-100 flex items-center justify-center text-xl mb-3 shadow-sm transition-colors duration-300">
            <i class="fas <?php echo htmlspecialchars($cat['icon']); ?>"></i>
          </div>
          <h3 class="text-sm font-bold text-gray-900 group-hover:text-[#1B2299] transition-colors">
            <?php echo htmlspecialchars($cat['name']); ?>
          </h3>
          <span class="text-[11px] text-gray-400 mt-1 capitalize font-medium">
            <?php echo htmlspecialchars($cat['type']); ?> Department
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Section 3: Flash Deals -->
<?php if (!empty($flashDeals)): ?>
<section class="py-14 sm:py-20 bg-gradient-to-b from-[#F8F9FC] to-white">
  <div class="container mx-auto px-4 lg:px-8">
    
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl bg-[#E4306D] text-white flex items-center justify-center text-lg">
          <i class="fas fa-bolt"></i>
        </span>
        <div>
          <h2 class="text-2xl sm:text-3xl font-black font-display text-gray-900">Flash Deals & Discounts</h2>
          <p class="text-xs sm:text-sm text-gray-500">Limited quantities at discounted prices.</p>
        </div>
      </div>
      
      <!-- Real Promotional Status Badge -->
      <div class="flex items-center gap-2 text-xs font-bold bg-white px-4 py-2 rounded-xl border border-gray-200 shadow-sm text-gray-700">
        <i class="fas fa-tag text-[#E4306D]"></i>
        <span>Verified Promotional Pricing • Real-Time Stock</span>
      </div>
    </div>

    <!-- Deal Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <?php foreach ($flashDeals as $item): ?>
        <div class="group bg-white rounded-2xl border border-gray-200/90 hover:border-[#1B2299]/30 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden">
          <div>
            <div class="relative bg-gray-100 aspect-square overflow-hidden">
              <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" 
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
              <span class="absolute top-3 left-3 bg-[#E4306D] text-white text-[10px] font-black px-2 py-0.5 rounded-full uppercase">
                Flash Sale
              </span>
            </div>
            <div class="p-5">
              <div class="flex items-center gap-1 text-xs text-amber-500 mb-1">
                <i class="fas fa-star text-[10px]"></i>
                <span class="font-bold text-gray-800"><?php echo number_format($item['rating'], 1); ?></span>
                <span class="text-gray-400 text-[11px]">(<?php echo $item['reviews_count']; ?>)</span>
              </div>
              <h3 class="font-bold text-sm text-gray-900 line-clamp-2 mb-2 group-hover:text-[#1B2299] transition-colors">
                <a href="<?php echo zavora_asset('product.php?id=' . $item['id']); ?>">
                  <?php echo htmlspecialchars($item['title']); ?>
                </a>
              </h3>
              <div class="flex items-baseline gap-2">
                <span class="text-base font-black text-[#1B2299]"><?php echo zavora_format_price($item['price']); ?></span>
                <?php if ($item['previous_price']): ?>
                  <span class="text-xs text-gray-400 line-through"><?php echo zavora_format_price($item['previous_price']); ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="p-5 pt-0">
            <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
              <input type="hidden" name="type" value="<?php echo $item['type']; ?>">
              <input type="hidden" name="title" value="<?php echo htmlspecialchars($item['title']); ?>">
              <input type="hidden" name="price" value="<?php echo $item['price']; ?>">
              <input type="hidden" name="image" value="<?php echo htmlspecialchars($item['thumbnail']); ?>">
              <button type="submit" class="w-full py-2.5 bg-gray-50 hover:bg-[#1B2299] text-gray-800 hover:text-white rounded-xl text-xs font-bold border border-gray-200 transition-colors flex items-center justify-center gap-2">
                <i class="fas fa-cart-plus"></i>
                <span>Add to Cart</span>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>

<!-- Section 4: ZAVORA Academy (E-Learning LMS Integration) -->
<section class="py-16 sm:py-20 bg-gradient-to-br from-[#0B132B] via-[#1B2299] to-[#0B132B] text-white relative overflow-hidden">
  <div class="container mx-auto px-4 lg:px-8 relative z-10">
    
    <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-4 mb-10">
      <div>
        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-white/10 border border-white/20 text-[#F7DE7A] text-xs font-bold mb-3">
          <i class="fas fa-graduation-cap"></i>
          <span>Learning Division • Powered by Learnerium</span>
        </div>
        <h2 class="text-3xl sm:text-4xl font-black font-display text-white">ZAVORA Academy</h2>
        <p class="text-sm text-gray-300 max-w-xl mt-1">
          Purchase once on ZAVORA — get automated single-click learning access on our connected Laravel LMS.
        </p>
      </div>
      <a href="<?php echo zavora_asset('academy.php'); ?>" class="px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-xs transition-all flex items-center gap-2">
        <span>Explore All Courses</span>
        <i class="fas fa-arrow-right text-xs"></i>
      </a>
    </div>

    <!-- Academy Cards -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
      <?php foreach ($academyCourses as $course): ?>
        <div class="bg-white/10 backdrop-blur-md rounded-2xl border border-white/15 overflow-hidden flex flex-col justify-between hover:border-[#F7DE7A]/50 transition-all duration-300 hover:scale-[1.02]">
          <div>
            <div class="relative aspect-video bg-gray-900 overflow-hidden">
              <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-full object-cover">
              <span class="absolute top-3 left-3 bg-[#1B2299] text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full">
                <?php echo htmlspecialchars($course['category'] ?? 'Academy'); ?>
              </span>
              <span class="absolute bottom-3 right-3 bg-black/80 backdrop-blur-sm text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                <i class="far fa-clock mr-1"></i><?php echo htmlspecialchars($course['duration_formatted'] ?? 'Self-paced'); ?>
              </span>
            </div>

            <div class="p-6">
              <div class="text-xs text-[#F7DE7A] font-semibold mb-1">
                Instructor: <?php echo htmlspecialchars($course['instructor']['name'] ?? 'John Luka Media'); ?>
              </div>
              <h3 class="font-bold text-base text-white line-clamp-2 mb-3">
                <?php echo htmlspecialchars($course['title']); ?>
              </h3>
              <p class="text-xs text-gray-300 line-clamp-2 mb-4">
                <?php echo htmlspecialchars($course['description']); ?>
              </p>

              <div class="flex items-center gap-3 text-xs text-gray-300 pb-4 border-b border-white/10">
                <span><i class="fas fa-layer-group text-[#F7DE7A] mr-1"></i><?php echo $course['modules_count'] ?? 6; ?> Modules</span>
                <span>•</span>
                <span><i class="fas fa-play-circle text-[#F7DE7A] mr-1"></i><?php echo $course['lessons_count'] ?? 24; ?> Lessons</span>
              </div>
            </div>
          </div>

          <div class="p-6 pt-0 flex items-center justify-between">
            <div>
              <span class="text-xs text-gray-400 block">Enrolment Fee</span>
              <span class="text-lg font-black text-[#F7DE7A]"><?php echo zavora_format_price($course['effective_price'] ?? $course['price']); ?></span>
            </div>
            <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
              <input type="hidden" name="type" value="course">
              <input type="hidden" name="title" value="<?php echo htmlspecialchars($course['title']); ?>">
              <input type="hidden" name="price" value="<?php echo $course['effective_price'] ?? $course['price']; ?>">
              <input type="hidden" name="image" value="<?php echo htmlspecialchars($course['thumbnail_url']); ?>">
              <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#E4306D] hover:bg-[#c2285a] text-white font-bold text-xs shadow-md transition-all flex items-center gap-1.5">
                <i class="fas fa-graduation-cap"></i>
                <span>Enrol Now</span>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- Section 5: Dedicated Digital Marketplace Assets -->
<section class="py-16 sm:py-20 bg-white border-b border-gray-200">
  <div class="container mx-auto px-4 lg:px-8">
    
    <div class="flex items-center justify-between mb-8">
      <div>
        <span class="text-xs font-bold text-[#E4306D] uppercase tracking-wider block mb-1">Instant Downloads</span>
        <h2 class="text-2xl sm:text-3xl font-black font-display text-gray-900">Digital Creative Assets & Tools</h2>
        <p class="text-xs sm:text-sm text-gray-500 mt-1">Figma UI kits, production templates, codebases, and executive manuals.</p>
      </div>
      <a href="<?php echo zavora_asset('shop.php?type=digital'); ?>" class="text-xs font-bold text-[#1B2299] hover:underline flex items-center gap-1">
        <span>Explore Digital</span>
        <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="grid md:grid-cols-3 gap-6">
      <?php foreach ($digitalProducts as $item): ?>
        <div class="bg-gray-50 rounded-2xl border border-gray-200/90 p-5 flex flex-col justify-between hover:bg-white hover:shadow-xl transition-all duration-300">
          <div>
            <div class="relative rounded-xl overflow-hidden aspect-video bg-gray-900 mb-4">
              <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover">
              <span class="absolute top-2.5 left-2.5 bg-[#0B132B] text-white text-[10px] font-bold px-2 py-0.5 rounded-md flex items-center gap-1">
                <i class="fas fa-download text-[9px] text-[#F7DE7A]"></i>
                <span>Digital Asset</span>
              </span>
            </div>
            <h3 class="font-bold text-sm text-gray-900 mb-2 line-clamp-2">
              <a href="<?php echo zavora_asset('product.php?id=' . $item['id']); ?>" class="hover:text-[#1B2299]">
                <?php echo htmlspecialchars($item['title']); ?>
              </a>
            </h3>
            <p class="text-xs text-gray-600 line-clamp-2 mb-3">
              <?php echo htmlspecialchars($item['description']); ?>
            </p>
            <div class="text-[11px] text-gray-500 font-medium mb-4">
              <?php echo htmlspecialchars($item['specifications']); ?>
            </div>
          </div>

          <div class="pt-4 border-t border-gray-200 flex items-center justify-between">
            <span class="text-base font-black text-[#1B2299]"><?php echo zavora_format_price($item['price']); ?></span>
            <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
              <input type="hidden" name="type" value="digital">
              <input type="hidden" name="title" value="<?php echo htmlspecialchars($item['title']); ?>">
              <input type="hidden" name="price" value="<?php echo $item['price']; ?>">
              <input type="hidden" name="image" value="<?php echo htmlspecialchars($item['thumbnail']); ?>">
              <button type="submit" class="px-4 py-2 bg-[#0B132B] hover:bg-[#1B2299] text-white text-xs font-bold rounded-xl transition-colors">
                Add to Cart
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- Section 6: Why Shop with ZAVORA? (Trust Indicators) -->
<section class="py-14 bg-gray-50">
  <div class="container mx-auto px-4 lg:px-8">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
      
      <div class="p-6 rounded-2xl bg-white border border-gray-150 flex flex-col items-center text-center">
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#1B2299] flex items-center justify-center text-xl mb-4">
          <i class="fas fa-shield-alt"></i>
        </div>
        <h4 class="font-bold text-sm text-gray-900 mb-1">Secure Payments</h4>
        <p class="text-xs text-gray-500">Pay safely with Paystack & Flutterwave. 256-bit SSL encrypted.</p>
      </div>

      <div class="p-6 rounded-2xl bg-white border border-gray-150 flex flex-col items-center text-center">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#16865B] flex items-center justify-center text-xl mb-4">
          <i class="fas fa-truck-fast"></i>
        </div>
        <h4 class="font-bold text-sm text-gray-900 mb-1">Reliable Delivery</h4>
        <p class="text-xs text-gray-500">Door-to-door nationwide delivery tracking across Nigeria.</p>
      </div>

      <div class="p-6 rounded-2xl bg-white border border-gray-150 flex flex-col items-center text-center">
        <div class="w-12 h-12 rounded-xl bg-purple-50 text-[#87207C] flex items-center justify-center text-xl mb-4">
          <i class="fas fa-graduation-cap"></i>
        </div>
        <h4 class="font-bold text-sm text-gray-900 mb-1">Instant LMS Access</h4>
        <p class="text-xs text-gray-500">Course purchases automatically sync with Learnerium Academy.</p>
      </div>

      <div class="p-6 rounded-2xl bg-white border border-gray-150 flex flex-col items-center text-center">
        <div class="w-12 h-12 rounded-xl bg-pink-50 text-[#E4306D] flex items-center justify-center text-xl mb-4">
          <i class="fas fa-headset"></i>
        </div>
        <h4 class="font-bold text-sm text-gray-900 mb-1">Customer Support</h4>
        <p class="text-xs text-gray-500">Direct assistance from the John Luka Media customer team.</p>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
