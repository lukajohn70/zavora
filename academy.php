<?php
$title = "ZAVORA Academy — Online Courses & Masterclasses";
require_once __DIR__ . '/includes/header.php';

$courses = zavora_get_academy_courses(24);
?>

<!-- Academy Hero -->
<section class="bg-gradient-to-br from-[#0B132B] via-[#1B2299] to-[#0B132B] text-white py-14 sm:py-20 relative overflow-hidden">
  <div class="container mx-auto px-4 lg:px-8 relative z-10 text-center max-w-3xl">
    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-[#F7DE7A] text-xs font-bold mb-4">
      <i class="fas fa-graduation-cap"></i>
      <span>Education Division of ZAVORA • Powered by Learnerium</span>
    </div>
    <h1 class="text-3xl sm:text-5xl font-black font-display tracking-tight text-white mb-4">
      Master In-Demand Skills.
    </h1>
    <p class="text-base sm:text-lg text-gray-200 leading-relaxed mb-6">
      Practical courses in programming, media broadcasting, creative design, and tech business. Purchase seamlessly on ZAVORA and learn on our dedicated LMS platform.
    </p>
    <div class="inline-flex items-center gap-4 text-xs text-gray-300 bg-white/5 px-4 py-2 rounded-xl border border-white/10">
      <span><i class="fas fa-check-circle text-[#16865B] mr-1"></i>Curriculum Drip Access</span>
      <span>•</span>
      <span><i class="fas fa-certificate text-[#F7DE7A] mr-1"></i>Verifiable Certificates</span>
      <span>•</span>
      <span><i class="fas fa-laptop-code text-blue-400 mr-1"></i>Practical Projects</span>
    </div>
  </div>
</section>

<!-- Course Listing Grid -->
<section class="py-12 sm:py-16">
  <div class="container mx-auto px-4 lg:px-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Featured Courses (<?php echo count($courses); ?>)</h2>
        <p class="text-xs sm:text-sm text-gray-500">All courses include instructor feedback and lifetime curriculum updates.</p>
      </div>
      <a href="<?php echo htmlspecialchars(LEARNERIUM_URL); ?>" target="_blank" rel="noopener" class="text-xs font-bold text-[#1B2299] hover:underline flex items-center gap-1.5">
        <span>Go to Learnerium LMS Portal</span>
        <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
      </a>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
      <?php foreach ($courses as $course): ?>
        <div class="bg-white rounded-2xl border border-gray-200/90 shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden group">
          <div>
            <div class="relative aspect-video bg-gray-900 overflow-hidden">
              <img src="<?php echo htmlspecialchars($course['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" 
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
              <span class="absolute top-3 left-3 bg-[#1B2299] text-white text-[10px] font-bold px-2.5 py-0.5 rounded-full">
                <?php echo htmlspecialchars($course['category'] ?? 'Technology'); ?>
              </span>
              <span class="absolute bottom-3 right-3 bg-black/80 backdrop-blur-sm text-white text-[10px] font-semibold px-2 py-0.5 rounded-md">
                <i class="far fa-clock mr-1"></i><?php echo htmlspecialchars($course['duration_formatted'] ?? 'Self-Paced'); ?>
              </span>
            </div>

            <div class="p-6">
              <div class="text-xs text-[#1B2299] font-bold mb-1">
                Instructor: <?php echo htmlspecialchars($course['instructor']['name'] ?? 'John Luka Media'); ?>
              </div>
              <h3 class="font-bold text-base text-gray-900 line-clamp-2 mb-2 group-hover:text-[#1B2299] transition-colors">
                <?php echo htmlspecialchars($course['title']); ?>
              </h3>
              <p class="text-xs text-gray-600 line-clamp-3 mb-4 leading-relaxed">
                <?php echo htmlspecialchars($course['description']); ?>
              </p>

              <div class="flex items-center gap-3 text-xs text-gray-500 py-3 border-t border-b border-gray-100">
                <span><i class="fas fa-layer-group text-gray-400 mr-1"></i><?php echo $course['modules_count'] ?? 6; ?> Modules</span>
                <span>•</span>
                <span><i class="fas fa-play-circle text-gray-400 mr-1"></i><?php echo $course['lessons_count'] ?? 24; ?> Lessons</span>
                <span>•</span>
                <span><i class="fas fa-award text-amber-500 mr-1"></i>Certificate</span>
              </div>
            </div>
          </div>

          <div class="p-6 pt-0 flex items-center justify-between">
            <div>
              <span class="text-[11px] text-gray-400 block">Tuition / Enrolment</span>
              <span class="text-lg font-black text-[#1B2299]">
                <?php echo zavora_format_price($course['effective_price'] ?? $course['price']); ?>
              </span>
            </div>
            <form action="<?php echo zavora_asset('cart.php'); ?>" method="POST">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
              <input type="hidden" name="type" value="course">
              <input type="hidden" name="title" value="<?php echo htmlspecialchars($course['title']); ?>">
              <input type="hidden" name="price" value="<?php echo $course['effective_price'] ?? $course['price']; ?>">
              <input type="hidden" name="image" value="<?php echo htmlspecialchars($course['thumbnail_url']); ?>">
              <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#E4306D] hover:bg-[#c2285a] text-white font-bold text-xs shadow-md hover:scale-105 transition-all flex items-center gap-1.5">
                <i class="fas fa-graduation-cap"></i>
                <span>Enrol via ZAVORA</span>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
