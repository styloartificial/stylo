<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Stylo App')</title>

    {{-- Tailwind / CSS --}}
    @vite('resources/css/app.css')
</head>
<body class="bg-gray-50 text-gray-800">

    {{-- Content --}}
    @yield('content')

</body>
</html>
