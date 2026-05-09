<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;

class ShareController extends Controller
{
    public function handle(Request $request, $username)
    {
        $user = User::where('username', $username)->firstOrFail();
        
        $frontendBase = env('FRONTEND_URL', 'http://localhost:4200');
        $frontendUrl = rtrim($frontendBase, '/') . '/u/' . $username;

        if (!$user->is_public) {
            return redirect()->away($frontendUrl);
        }

        $userAgent = strtolower($request->header('User-Agent'));
        $isBot = preg_match('/(bot|facebook|twitter|discord|whatsapp|telegram|linkedin)/i', $userAgent);

        if ($isBot) {
            // 🚀 Obtenemos las estadísticas también para el texto
            $stats = $user->games()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $completados = $stats->get('completado', 0);
            $jugando = $stats->get('jugando', 0);
            $pendientes = $stats->get('pendiente', 0);

            return view('social_preview', [
                'user' => $user,
                'frontendUrl' => $frontendUrl,
                'imageUrl' => url('/api/share/' . $username . '/image'),
                // Pasamos los números a la vista HTML
                'completados' => $completados,
                'jugando' => $jugando,
                'pendientes' => $pendientes
            ]);
        }

        return redirect()->away($frontendUrl);
    }

    public function generateImage($username)
    {
        $user = User::where('username', $username)->firstOrFail();

        if (!$user->is_public) {
            abort(403, 'Perfil privado');
        }

        $stats = $user->games()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $completados = $stats->get('completado', 0);
        $jugando = $stats->get('jugando', 0);
        $pendientes = $stats->get('pendiente', 0);

        // Usamos Intervention Image V3 (que es la que te funciona)
        $manager = ImageManager::gd();
        $image = $manager->create(1200, 630)->fill('#171a21');

        // IMPORTANTE: Asegúrate de que esta fuente sube a tu servidor Render
        $fontPath = public_path('fonts/fuente.ttf');

        $image->text('Biblioteca de ' . strtoupper($user->username), 600, 200, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(65);
            $font->color('#ffffff');
            $font->align('center');
            $font->valign('middle');
        });

        $statsText = "🏆 Completados: {$completados}   |   🎮 Jugando: {$jugando}";
        $image->text($statsText, 600, 350, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(45);
            $font->color('#66c0f4');
            $font->align('center');
            $font->valign('middle');
        });

        $image->text("Pendientes: {$pendientes} juegos esperando su turno...", 600, 450, function ($font) use ($fontPath) {
            $font->filename($fontPath);
            $font->size(30);
            $font->color('#8f98a0');
            $font->align('center');
            $font->valign('middle');
        });

        $pngData = (string) $image->toPng();
        
        return response($pngData, 200)->header('Content-Type', 'image/png');
    }
}