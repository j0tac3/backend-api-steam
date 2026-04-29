<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RadarController extends Controller
{
    /* public function getSteamDeals(Request $request)
    {
        try {
            // Hacemos la petición a la API de CheapShark
            // storeID=1 (Steam), sortBy=Deal Rating (Los mejores chollos primero)
            $response = Http::get('https://www.cheapshark.com/api/1.0/deals', [
                'storeID' => 1,
                'sortBy' => 'Deal Rating', 
                'pageSize' => 12, // Traemos 12 para que cuadren bien en tu grid/carrusel
                'onSale' => 1
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json(['error' => 'No se pudo conectar con el radar de ofertas'], 502);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    } */

    public function getSteamDeals(Request $request)
    {
        try {
            $response = Http::get('https://www.cheapshark.com/api/1.0/deals', [
                'storeID' => 1,
                'sortBy'  => 'Savings', // 🚀 Cambiamos a 'Savings' para que los de 100% salgan primero
                'pageSize' => 15,       // Aumentamos a 15 para tener más margen
                'onSale'  => 1
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }
            return response()->json(['error' => 'Error de conexión'], 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}