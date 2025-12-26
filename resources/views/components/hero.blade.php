<?php
    $androidUrl = route('download.android');
?>

<section class="relative bg-linear-to-b from-purple-100 via-white to-white py-8 md:py-16 lg:py-24 ">
    <div  class="hero-reveal max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-4xl mx-auto">
            <div class="inline-flex items-center space-x-2 bg-white border border-purple-200 rounded-full px-4 py-2 mb-8 shadow-sm">
                <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span class="text-sm md:text-base font-medium text-purple-600">AI-Powered Fashion Assistant</span>
            </div>

            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-gray-900 mb-6 leading-tight opacity-0 animate-fadeInUp">
                <span class="inline-block bg-linear-to-r from-purple-600 via-pink-500 to-blue-400 bg-clip-text text-transparent animate-gradientShift bg-size-[200%_100%]">
                    Smarter outfits,
                </span>
                <br class="hidden sm:block">
                <span class="inline-block text-gray-900">
                    every single day.
                </span>
            </h1>

            <p class="text-lg sm:text-xl md:text-xl text-gray-600 mb-10 max-w-3xl mx-auto leading-relaxed">
                Scan your wardrobe, get personalized outfit ideas, and shop the look instantly.
            </p>

            <div class="flex justify-center">
                <a href="<?php echo e($androidUrl); ?>" target="_blank" class="inline-block bg-gray-900 hover:bg-gray-800 text-white text-base md:text-lg font-semibold px-8 md:px-12 py-4 md:py-5 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    Get Started for Free
                </a>
            </div>
        </div>
    </div>


</section>
