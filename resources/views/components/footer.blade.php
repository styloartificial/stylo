<footer class="bg-white border-t border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">

        <div class="flex flex-col lg:flex-row lg:justify-between gap-8 lg:gap-12">

            <div class="lg:max-w-sm">
                <div class="flex items-center space-x-2 mb-4">
                    <img src="{{ asset('logo/logo.png') }}" alt="Stylo AI Logo" class="h-12 w-12">
                    <span class="text-xl font-semibold text-gray-900">Stylo AI</span>
                </div>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Your personal AI stylist in your pocket.<br>
                    Look good, feel good, every day.
                </p>
            </div>

            <!-- Links Section - Right -->
            <div class="flex gap-12 md:gap-16 lg:gap-20">
                <!-- Company Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Company</h3>
                    <ul class="space-y-3">
                        <li>
                            <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                                About
                            </a>
                        </li>
                        <li>
                            <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                                Contact
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Product Links -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-4">Product</h3>
                    <ul class="space-y-3">
                        <li>
                            <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                                Features
                            </a>
                        </li>
                        <li>
                            <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">
                                Download App
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Bottom Bar - Copyright Centered -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <div class="text-center">
                <p class="text-sm text-gray-400">
                    © {{ date('Y') }} Stylo AI Inc. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>