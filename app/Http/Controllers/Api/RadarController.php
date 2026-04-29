<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; // 🚀 Importante importar esto

class RadarController extends Controller
{
    public function getSteamDeals(Request $request)
    {
        // Intentamos obtener las ofertas de la caché por 30 minutos
        $ofertas = Cache::remember('radar_steam_deals', 60 * 30, function () {
            try {
                $response = Http::timeout(15)
                    ->withoutVerifying()
                    ->get('https://www.cheapshark.com/api/1.0/deals', [
                        'storeID' => 1,
                        'sortBy'  => 'Savings',
                        'pageSize' => 15,
                        'onSale'  => 1
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                // Si hay error (como el 429), devolvemos null para no cachear un error
                return null;

            } catch (\Exception $e) {
                \Log::error("Error en Radar: " . $e->getMessage());
                return null;
            }
        });

        // Si tenemos datos (de caché o de la API), los enviamos
        if ($ofertas) {
            return response()->json($ofertas);
        }

        // Si todo falla y no hay nada en caché, avisamos con un mensaje elegante
        return response()->json([
            'error' => 'El radar está recalibrándose. Por favor, vuelve a intentarlo en unos minutos.',
            'status' => 429
        ], 200); // Enviamos 200 para que Angular no lo trate como un crash total
    }
}