@extends('layouts.app')

@section('title', 'Stylo App | Landing Page')

@section('content')

{{-- Navbar --}}
<x-navbar />
{{-- End Navbar --}}

{{-- Hero Section --}}
<x-hero />
{{-- End Hero Section --}}

{{-- Features Section --}}
<x-features />
{{-- End Features Section --}}

{{-- How It Works Section --}}
<x-how-it-works />
{{-- End How It Works Section --}}

{{-- Call to Action Section --}}
<x-cta />
{{-- End Call to Action Section --}}

{{-- Footer --}}
<x-footer />
{{-- End Footer --}}

@endsection
