<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Stylo App')</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('logo/logo.png') }}">

    {{-- Tailwind / CSS --}}
    @vite('resources/css/app.css')
</head>

<body class="bg-gray-50 text-gray-800">

    {{-- Content --}}
    @yield('content')

    {{-- ScrollReveal JS --}}
    <script src="https://unpkg.com/scrollreveal@4.0.9/dist/scrollreveal.min.js"></script>
    
    {{-- Fix Auto Scroll Issue --}}
    <script>
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo(0, 0);
    </script>
    
    {{-- ScrollReveal Init --}}
    <script>

        ScrollReveal({
            duration: 1000,
            distance: '50px',
            easing: 'ease-in-out',
            reset: true, 
            mobile: true,
            opacity: 0,
        });

        ScrollReveal().reveal('.hero-reveal', {
            duration: 1500,
            origin: 'bottom',
            distance: '80px',
            scale: 0.85,
            delay: 200,
        });

        ScrollReveal().reveal('.fade-up', {
            origin: 'bottom',
            distance: '50px',
        });

        ScrollReveal().reveal('.fade-left', {
            origin: 'left',
            distance: '80px',
        });

        ScrollReveal().reveal('.fade-right', {
            origin: 'right',
            distance: '80px',
        });

        ScrollReveal().reveal('.zoom-in', {
            scale: 0.85,
            distance: '0px',
        });
      
        ScrollReveal().reveal('.stagger-item', {
            interval: 200, 
        });
    </script>

    {{-- Scripts dari Child Views --}}
    @stack('scripts')

</body>
</html>