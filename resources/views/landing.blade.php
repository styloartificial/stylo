@extends('layouts.app')

@section('title', 'Stylo AI - Your Personal AI Stylist')

@section('content')
    <x-navbar />
    
    <main>
        <x-hero />
        <x-features />
        <x-how-it-works />
        <x-cta />
    </main>
    
    <x-footer />
@endsection