<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Biblioteca de {{ $user->username }}</title>
    
    <meta property="og:title" content="🎮 Colección de juegos de {{ $user->username }}">
    <meta property="og:description" content="¡Echa un vistazo a mis estadísticas y mi biblioteca de juegos completados!">
    <meta property="og:image" content="{{ $imageUrl }}">
    <meta property="og:url" content="{{ $frontendUrl }}">
    <meta property="og:type" content="profile">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="🎮 Colección de juegos de {{ $user->username }}">
    <meta name="twitter:description" content="¡Echa un vistazo a mis estadísticas y mi biblioteca de juegos completados!">
    <meta name="twitter:image" content="{{ $imageUrl }}">
</head>
<body>
    <p>Redirigiendo a la biblioteca...</p>
    <script>window.location.href = "{{ $frontendUrl }}";</script>
</body>
</html>