<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    
    <title>🎮 Colección de juegos de {{ $user->username }}</title>
    <meta name="description" content="🏆 Completados: {{ $completados }} | 🎮 Jugando: {{ $jugando }} | ⏳ Pendientes: {{ $pendientes }}">
    
    <meta name="theme-color" content="#66c0f4">

    <meta property="og:type" content="website">
    <meta property="og:title" content="🎮 Colección de juegos de {{ $user->username }}">
    <meta property="og:description" content="🏆 Completados: {{ $completados }} | 🎮 Jugando: {{ $jugando }} | ⏳ Pendientes: {{ $pendientes }}">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:url" content="{{ $frontendUrl }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="🎮 Colección de juegos de {{ $user->username }}">
    <meta name="twitter:description" content="🏆 Completados: {{ $completados }} | 🎮 Jugando: {{ $jugando }} | ⏳ Pendientes: {{ $pendientes }}">
    <meta name="twitter:image" content="{{ $imageUrl }}">
</head>
<body>
    <script>window.location.replace("{{ $frontendUrl }}");</script>
</body>
</html>