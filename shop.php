<?php
$title = "Shop Marketplace";
require_once __DIR__ . '/includes/header.php';

$pdo = zavora_get_db();

$type = $_GET['type'] ?? '';
$category = $_GET['category'] ?? '';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'featured';

$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if (!empty($type)) {
    $sql .= " AND p.type = ?";
    $params[] = $type;
}

if (!empty($category)) {
    $sql .= " AND c.slug = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($sort === 'price_low') {
    $sql .= " ORDER BY p.price ASC";
} elseif ($sort === 'price_high') {
    $sql .= " ORDER BY p.price DESC";
} elseif ($sort === 'rating') {
    $sql .= " ORDER BY p.rating DESC";
} else {
    $sql .= " ORDER BY p.is_boosted DESC, p.is_featured DESC, p.id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories for sidebar
$catList = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<div class="container mx-auto px-4 lg:px-8 py-8 sm:py-12">
  
  <!-- Breadcrumb & Header -->
  <div class="mb-8">
    <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
      <a href="<?php echo zavora_asset('index.php'); ?>" class="hover:text-gray-600">Home</a>
      <span>/</span>
      <span class="text-gray-800 font-bold">Shop</span>
      <?php if ($category): ?>
        <span>/</span>
        <span class="text-[#1B2299] font-bold capitalize"><?php echo htmlspecialchars($category); ?></span>
      <?php endif; ?>
    </div>
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-black font-display text-gray-900">
          <?php echo $search ? 'Results for "' . htmlspecialchars($search) . '"' : 'Explore All Products'; ?>
        </h1>
        <p class="text-sm text-gray-500 mt-1"><?php echo count($products); ?> items available</p>
      </div>
      
      <!-- Sort Dropdown -->
      <form method="GET" class="flex items-center gap-2 text-xs">
        <?php if ($type): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>"><?php endif; ?>
        <?php if ($category): ?><input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
        <?php if ($search): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
        <label for="sort" class="text-gray-500 font-semibold">Sort By:</label>
        <select name="sort" id="sort" onchange="this.form.submit()" class="px-3 py-2 bg-white border border-gray-200 rounded-xl text-gray-800 font-medium focus:outline-none focus:ring-2 focus:ring-[#1B2299]">
          <option value="featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Featured</option>
          <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
          <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
          <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
        </select>
      </form>
    </div>
  </div>

  <div class="grid lg:grid-cols-12 gap-8 items-start">
    
    <!-- Sidebar Filters (Left 3 Cols) -->
    <aside class="lg:col-span-3 space-y-6">
      
      <!-- Department Filter -->
      <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm">
        <h3 class="font-bold text-sm text-gray-900 mb-3 pb-2 border-b border-gray-100">Product Type</h3>
        <ul class="space-y-2 text-xs">
          <li>
            <a href="<?php echo zavora_asset('shop.php'); ?>" class="flex items-center justify-between py-1 text-gray-700 hover:text-[#1B2299] <?php echo empty($type) ? 'font-bold text-[#1B2299]' : ''; ?>">
              <span>All Products</span>
            </a>
          </li>
          <li>
            <a href="<?php echo zavora_asset('shop.php?type=physical'); ?>" class="flex items-center justify-between py-1 text-gray-700 hover:text-[#1B2299] <?php echo $type === 'physical' ? 'font-bold text-[#1B2299]' : ''; ?>">
              <span>Physical Goods</span>
              <span class="px-1.5 py-0.5 rounded bg-blue-50 text-[#1B2299] text-[10px]">Delivery</span>
            </a>
          </li>
          <li>
            <a href="<?php echo zavora_asset('shop.php?type=digital'); ?>" class="flex items-center justify-between py-1 text-gray-700 hover:text-[#1B2299] <?php echo $type === 'digital' ? 'font-bold text-[#1B2299]' : ''; ?>">
              <span>Digital Assets & Downloads</span>
              <span class="px-1.5 py-0.5 rounded bg-pink-50 text-[#E4306D] text-[10px]">Instant</span>
            </a>
          </li>
          <li>
            <a href="<?php echo zavora_asset('academy.php'); ?>" class="flex items-center justify-between py-1 text-[#1B2299] font-bold">
              <span>ZAVORA Academy</span>
              <span class="px-1.5 py-0.5 rounded bg-purple-50 text-[#87207C] text-[10px]">LMS</span>
            </a>
          </li>
        </ul>
      </div>

      <!-- Categories Filter -->
      <div class="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-sm">
        <h3 class="font-bold text-sm text-gray-900 mb-3 pb-2 border-b border-gray-100">Categories</h3>
        <ul class="space-y-2 text-xs">
          <?php foreach ($catList as $c): ?>
            <li>
              <a href="<?php echo zavora_asset('shop.php?category=' . urlencode($c['slug'])); ?>" 
                 class="flex items-center justify-between py-1 text-gray-600 hover:text-[#1B2299] <?php echo $category === $c['slug'] ? 'font-bold text-[#1B2299]' : ''; ?>">
                <span><?php echo htmlspecialchars($c['name']); ?></span>
                <i class="fas fa-chevron-right text-[9px] text-gray-300"></i>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

    </aside>

    <!-- Products Grid (Right 9 Cols) -->
    <div class="lg:col-span-9">
      <?php if (empty($products)): ?>
        <div class="bg-white rounded-2xl border border-gray-200/90 p-12 text-center shadow-sm">
          <i class="fas fa-search text-gray-300 text-3xl mb-3"></i>
          <h3 class="text-base font-bold text-gray-900 mb-1">No products found</h3>
          <p class="text-xs text-gray-500 mb-4">Try adjusting your filters or search keywords.</p>
          <a href="<?php echo zavora_asset('shop.php'); ?>" class="px-4 py-2 bg-[#1B2299] text-white text-xs font-bold rounded-xl">Reset Filters</a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($products as $item): ?>
            <div class="group bg-white rounded-2xl border border-gray-200/90 hover:border-[#1B2299]/30 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden relative">
              <div>
                <div class="relative bg-gray-100 aspect-square overflow-hidden">
                  <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" 
                       class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                  <span class="absolute top-3 left-3 bg-[#0B132B] text-white text-[10px] font-bold px-2 py-0.5 rounded-md uppercase">
                    <?php echo htmlspecialchars($item['type']); ?>
                  </span>

                  <?php if (!empty($item['is_boosted'])): ?>
                    <span class="absolute top-3 right-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider shadow flex items-center gap-1">
                      <i class="fas fa-bolt text-[9px]"></i> Sponsored
                    </span>
                  <?php endif; ?>
                </div>
                <div class="p-5">
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <div class="flex items-center gap-1 text-xs text-amber-500">
                      <i class="fas fa-star text-[10px]"></i>
                      <span class="font-bold text-gray-800"><?php echo number_format($item['rating'], 1); ?></span>
                      <span class="text-gray-400 text-[11px]">(<?php echo $item['reviews_count']; ?>)</span>
                    </div>

                    <?php if (!empty($item['is_preorder'])): ?>
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Pre-Order
                      </span>
                    <?php elseif (!empty($item['has_variants'])): ?>
                      <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600">
                        Variety
                      </span>
                    <?php endif; ?>
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
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
