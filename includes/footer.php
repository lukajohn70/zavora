  </main>

  <!-- Global Footer -->
  <footer class="bg-[#0B132B] text-white pt-16 pb-12 border-t border-white/10">
    <div class="container mx-auto px-4 lg:px-8">
      
      <!-- Top Newsletter / Trust Strip -->
      <div class="p-8 rounded-2xl bg-gradient-to-r from-[#1B2299]/30 via-white/5 to-[#E4306D]/20 border border-white/10 mb-14 flex flex-col lg:flex-row items-center justify-between gap-8">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-[#F7DE7A] block mb-1">Stay Ahead of the Deals</span>
          <h3 class="text-2xl font-black font-display text-white">Subscribe to ZAVORA Insiders</h3>
          <p class="text-gray-300 text-sm mt-1">Get flash discounts, new digital release alerts, and course enrolment scholarships.</p>
        </div>
        <form class="flex w-full lg:w-auto max-w-md gap-2" onsubmit="event.preventDefault(); alert('Thank you for subscribing to ZAVORA!');">
          <input type="email" placeholder="Enter your email address" required 
                 class="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#E4306D]" />
          <button type="submit" class="px-6 py-3 bg-[#E4306D] hover:bg-[#c2285a] text-white font-bold text-sm rounded-xl transition-transform hover:scale-105 shadow-lg">
            Subscribe
          </button>
        </form>
      </div>

      <!-- Main Footer Columns -->
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8 mb-12 text-sm">
        
        <!-- Brand Info -->
        <div class="col-span-2">
          <img src="<?php echo zavora_asset('assets/images/logo-white.svg'); ?>" alt="ZAVORA" class="h-10 w-auto mb-4" />
          <p class="text-gray-400 text-sm leading-relaxed mb-4 max-w-sm">
            ZAVORA is a premier multi-category marketplace. Discover physical merchandise, production-ready digital downloads, and world-class online courses in one seamless checkout experience.
          </p>
          <div class="flex items-center gap-2 text-xs font-semibold text-gray-300">
            <span class="w-2 h-2 rounded-full bg-[#16865B]"></span>
            <span>A proud digital platform of <a href="<?php echo htmlspecialchars(JLM_CORPORATE_URL); ?>" target="_blank" class="text-[#F7DE7A] hover:underline font-bold">John Luka Media</a></span>
          </div>
        </div>

        <!-- Shop Categories -->
        <div>
          <h4 class="font-bold text-white mb-4 text-xs tracking-wider uppercase">Marketplace</h4>
          <ul class="space-y-2.5 text-gray-400">
            <li><a href="<?php echo zavora_asset('shop.php?category=electronics'); ?>" class="hover:text-white transition-colors">Electronics & Audio</a></li>
            <li><a href="<?php echo zavora_asset('shop.php?category=computing'); ?>" class="hover:text-white transition-colors">Computing & Laptops</a></li>
            <li><a href="<?php echo zavora_asset('shop.php?category=fashion'); ?>" class="hover:text-white transition-colors">Apparel & Merch</a></li>
            <li><a href="<?php echo zavora_asset('shop.php?type=digital'); ?>" class="hover:text-white transition-colors">Digital UI Kits & Code</a></li>
            <li><a href="<?php echo zavora_asset('shop.php?category=ebooks'); ?>" class="hover:text-white transition-colors">E-Books & Manuals</a></li>
          </ul>
        </div>

        <!-- Academy & LMS -->
        <div>
          <h4 class="font-bold text-white mb-4 text-xs tracking-wider uppercase">ZAVORA Academy</h4>
          <ul class="space-y-2.5 text-gray-400">
            <li><a href="<?php echo zavora_asset('academy.php'); ?>" class="hover:text-white transition-colors">Browse Online Courses</a></li>
            <li><a href="<?php echo htmlspecialchars(LEARNERIUM_URL); ?>" target="_blank" rel="noopener" class="hover:text-[#F7DE7A] transition-colors flex items-center gap-1.5">
              <span>Learnerium LMS</span>
              <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
            </a></li>
            <li><a href="<?php echo htmlspecialchars(LEARNERIUM_URL . '/login/student'); ?>" target="_blank" class="hover:text-white transition-colors">Student Sign In</a></li>
            <li><a href="<?php echo htmlspecialchars(LEARNERIUM_URL . '/login/instructor'); ?>" target="_blank" class="hover:text-white transition-colors">Teach with Us</a></li>
          </ul>
        </div>

        <!-- Ecosystem Platforms -->
        <div>
          <h4 class="font-bold text-white mb-4 text-xs tracking-wider uppercase">JLM Ecosystem</h4>
          <ul class="space-y-2.5 text-gray-400">
            <li><a href="<?php echo htmlspecialchars(JLM_CORPORATE_URL); ?>" target="_blank" class="hover:text-white transition-colors">JLM Corporate Hub</a></li>
            <li><a href="<?php echo htmlspecialchars(LEARNERIUM_URL); ?>" target="_blank" class="hover:text-white transition-colors">Learnerium Platform</a></li>
            <li><a href="https://results.jlm.com.ng" target="_blank" class="hover:text-white transition-colors">JLM Results (RMS)</a></li>
            <li><a href="<?php echo zavora_asset('admin/index.php'); ?>" class="hover:text-[#E4306D] transition-colors text-xs flex items-center gap-1">
              <i class="fas fa-lock text-[10px]"></i>
              <span>Admin Portal</span>
            </a></li>
          </ul>
        </div>

      </div>

      <!-- Bottom Bar -->
      <div class="pt-8 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-gray-400">
        <div>
          © <?php echo date('Y'); ?> ZAVORA Marketplace. All rights reserved. Technology powered by John Luka Media.
        </div>
        <div class="flex items-center gap-4">
          <span class="text-gray-500">Secured with:</span>
          <span class="px-2 py-1 bg-white/10 rounded font-semibold text-white">Paystack</span>
          <span class="px-2 py-1 bg-white/10 rounded font-semibold text-white">Flutterwave</span>
          <span class="px-2 py-1 bg-white/10 rounded font-semibold text-white">Mastercard</span>
          <span class="px-2 py-1 bg-white/10 rounded font-semibold text-white">Visa</span>
        </div>
      </div>

    </div>
  </footer>

  <!-- Mobile Bottom Floating Navigation Bar -->
  <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 px-4 py-2 flex justify-around items-center shadow-2xl">
    <a href="<?php echo zavora_asset('index.php'); ?>" class="flex flex-col items-center text-xs text-gray-600 hover:text-[#1B2299]">
      <i class="fas fa-home text-base"></i>
      <span class="text-[10px] mt-0.5">Home</span>
    </a>
    <a href="<?php echo zavora_asset('shop.php'); ?>" class="flex flex-col items-center text-xs text-gray-600 hover:text-[#1B2299]">
      <i class="fas fa-th-large text-base"></i>
      <span class="text-[10px] mt-0.5">Shop</span>
    </a>
    <a href="<?php echo zavora_asset('academy.php'); ?>" class="flex flex-col items-center text-xs text-[#1B2299] font-bold">
      <i class="fas fa-graduation-cap text-base"></i>
      <span class="text-[10px] mt-0.5">Academy</span>
    </a>
    <a href="<?php echo zavora_asset('cart.php'); ?>" class="flex flex-col items-center text-xs text-gray-600 hover:text-[#1B2299] relative">
      <i class="fas fa-bag-shopping text-base"></i>
      <span class="text-[10px] mt-0.5">Cart</span>
      <?php if ($cartCount > 0): ?>
        <span class="absolute -top-1 -right-2 bg-[#E4306D] text-white text-[9px] font-black px-1 rounded-full">
          <?php echo $cartCount; ?>
        </span>
      <?php endif; ?>
    </a>
    <a href="<?php echo zavora_asset('account/index.php'); ?>" class="flex flex-col items-center text-xs text-gray-600 hover:text-[#1B2299]">
      <i class="far fa-user text-base"></i>
      <span class="text-[10px] mt-0.5">Account</span>
    </a>
  </div>

</body>
</html>
