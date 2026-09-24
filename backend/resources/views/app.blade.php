<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    {{-- In production, these are injected by the frontend build (Vite manifest). --}}
    @if(app()->environment('local'))
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/src/main.tsx"></script>
    @else
        @php($manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true))
        <script type="module" src="{{ asset('build/'.$manifest['src/main.tsx']['file']) }}"></script>
        <link rel="stylesheet" href="{{ asset('build/'.$manifest['src/main.tsx']['css'][0]) }}">
    @endif
</head>
<body>
    <div id="root"></div>
</body>
</html>
