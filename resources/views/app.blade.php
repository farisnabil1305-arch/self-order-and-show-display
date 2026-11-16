<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#FAF7F2] antialiased">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Soto Betawi Parsoey') }}</title>

        <!-- Google Fonts: DM Serif Display (Editorial Culinary), Plus Jakarta Sans (Clean UI), JetBrains Mono (Numbers/POS) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="h-full font-sans text-[#231812] bg-[#FAF7F2] overflow-x-hidden selection:bg-[#EADECF] selection:text-[#231812]">
        @inertia
    </body>
</html>
