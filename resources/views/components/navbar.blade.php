<nav class="bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <div class="shrink-0 flex items-center">
                <a href="#home" class="flex items-center space-x-2">
                    <img src="{{ asset('logo/logo.png') }}" alt="Stylo AI Logo" class="h-12 w-12">
                    <span class="text-xl font-bold text-gray-900">Stylo AI</span>
                </a>
            </div>

            <div class="hidden md:flex md:items-center md:space-x-8">
                <a href="#features" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">
                    Features
                </a>
                <a href="#how-it-works" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">
                    How it works
                </a>
                <a href="#download" class="text-gray-700 hover:text-gray-900 px-3 py-2 text-sm font-medium transition-colors">
                    Download
                </a>
            </div>

            <div class="md:hidden flex items-center">
                <button type="button" 
                        id="mobile-menu-button"
                        class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-purple-500"
                        aria-controls="mobile-menu"
                        aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <!-- Hamburger icon -->
                    <svg id="hamburger-icon" class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                    <!-- Close icon (hidden by default) -->
                    <svg id="close-icon" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div class="hidden md:hidden" id="mobile-menu">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 border-t border-gray-200 bg-white">
            <a href="#features" class="text-gray-700 hover:text-gray-900 hover:bg-gray-50 block px-3 py-2 rounded-md text-base font-medium mobile-menu-link">
                Features
            </a>
            <a href="#how-it-works" class="text-gray-700 hover:text-gray-900 hover:bg-gray-50 block px-3 py-2 rounded-md text-base font-medium mobile-menu-link">
                How it works
            </a>
            <a href="#download" class="text-gray-700 hover:text-gray-900 hover:bg-gray-50 block px-3 py-2 rounded-md text-base font-medium mobile-menu-link">
                Download
            </a>
        </div>
    </div>
</nav>

@push('scripts')
<script>
    // Toggle mobile menu
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const hamburgerIcon = document.getElementById('hamburger-icon');
    const closeIcon = document.getElementById('close-icon');

    mobileMenuButton.addEventListener('click', function() {
        // Toggle menu visibility
        mobileMenu.classList.toggle('hidden');
        
        // Toggle icons
        hamburgerIcon.classList.toggle('hidden');
        hamburgerIcon.classList.toggle('block');
        closeIcon.classList.toggle('hidden');
        closeIcon.classList.toggle('block');
        
        // Update aria-expanded
        const expanded = this.getAttribute('aria-expanded') === 'true' || false;
        this.setAttribute('aria-expanded', !expanded);
    });

    // Close mobile menu when clicking on a link
    const mobileMenuLinks = document.querySelectorAll('.mobile-menu-link');
    mobileMenuLinks.forEach(link => {
        link.addEventListener('click', function() {
            mobileMenu.classList.add('hidden');
            hamburgerIcon.classList.remove('hidden');
            hamburgerIcon.classList.add('block');
            closeIcon.classList.add('hidden');
            closeIcon.classList.remove('block');
            mobileMenuButton.setAttribute('aria-expanded', 'false');
        });
    });

    // Smooth scroll untuk semua anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
</script>
@endpush