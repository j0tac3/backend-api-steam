<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Services\IgdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GameSearchController extends Controller
{
    protected $igdb;

    public function __construct(IgdbService $igdb)
    {
        $this->igdb = $igdb;
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        if (!$query) return response()->json([]);

        $results = $this->igdb->searchGames($query);
        return response()->json($results);
    }

    public function getIgdbDetails($id, IgdbService $igdbService)
    {
        $details = $igdbService->getGameDetails($id);
        
        if (!$details) {
            return response()->json(['message' => 'Juego no encontrado'], 404);
        }

        return response()->json($details);
    }

    public function buscarEnIGDB(Request $request) {
        $nombre = $request->query('nombre');

        if (!$nombre) {
            return response()->json(['error' => 'Falta el nombre del juego'], 400);
        }

        // Llamamos al método del servicio que ya tiene la lógica de autenticación
        // Nota: He creado un método nuevo en el servicio llamado 'buscarJuegosAvanzado'
        $results = $this->igdb->buscarJuegosAvanzado($nombre);

        if (isset($results['error'])) {
            return response()->json($results, 500);
        }

        return response()->json($results);
    }

}