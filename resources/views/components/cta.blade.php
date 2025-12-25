<section id="download" class="py-6 md:py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- CTA Card -->
        <div class="max-w-5xl mx-auto bg-linear-to-br from-gray-900 to-gray-800 rounded-2xl md:rounded-3xl lg:rounded-[3rem] p-8 md:p-12 text-center shadow-2xl">
            
            <!-- Heading -->
            <h2 class="text-xl sm:text-xl md:text-2xl font-bold text-white mb-4 md:mb-5 lg:mb-6 leading-tight px-4">
                Ready to upgrade your style?
            </h2>

            <!-- Subheading -->
            <p class="text-sm sm:text-sm md:text-base text-gray-300 mb-6 md:mb-8 lg:mb-10 max-w-2xl mx-auto leading-relaxed px-4">
                Join thousands of users dressing smarter with Stylo AI.
            </p>

            <?php
                $androidUrl = route('download.android');
            ?>

            <!-- CTA Button -->
            <div class="flex justify-center px-4">
                <a href="<?php echo e($androidUrl); ?>" target="_blank" class="inline-flex items-center justify-center w-full sm:w-auto bg-purple-600 hover:bg-purple-700 text-white text-sm sm:text-base md:text-lg font-semibold px-6 sm:px-8 md:px-10 lg:px-12 py-3 sm:py-3.5 md:py-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 hover:scale-105">
                    <span class="whitespace-nowrap">Download for Android</span>
                </a>
            </div>
        </div>
    </div>
</section>