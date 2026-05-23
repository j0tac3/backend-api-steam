<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Models\Game; 
use Illuminate\Support\Facades\DB;


class JournalEntryController extends Controller
{
    // 1. OBTENER TODAS LAS NOTAS DE UN JUEGO (GET)
    public function index(Request $request, $gameId)
    {
        // 🚀 ACTUALIZADO: Ahora buscamos en la relación correcta 'inventory()' en lugar del viejo 'games()'
        $hasGame = $request->user()->inventory()->where('game_id', $gameId)->exists();
        
        if (!$hasGame) {
            return response()->json(['message' => 'No tienes este juego en tu colección'], 403);
        }

        // Traemos SOLO las notas de ese usuario para ese juego
        $entries = JournalEntry::where('game_id', $gameId)
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($entries);
    }

    // 2. CREAR UNA NOTA (POST)
    public function store(Request $request, $gameId)
    {
        $request->validate(['content' => 'required|string']);
        
        // 🚀 Verificamos propiedad del juego usando 'inventory()'
        $hasGame = $request->user()->inventory()->where('game_id', $gameId)->exists();
        if (!$hasGame) {
            return response()->json(['message' => 'No puedes añadir notas a un juego que no tienes'], 403);
        }

        // Creamos la nota ligada al Juego GLOBAL y al Usuario ACTUAL
        $entry = JournalEntry::create([
            'user_id' => $request->user()->id,
            'game_id' => $gameId,
            'content' => $request->content,
            'is_featured' => \Illuminate\Support\Facades\DB::raw('false') // Satisface a PostgreSQL
        ]);

        // 🚀 EL FIX PARA ANGULAR:
        // Forzamos que la copia en memoria tenga el booleano false puro para el JSON
        $entry->is_featured = false;

        return response()->json($entry, 201);
    }

    // 3. ACTUALIZAR UNA NOTA (PUT/PATCH)
    public function update(Request $request, $id)
    {
        // Buscamos la nota asegurándonos de que le pertenece al usuario
        $entry = JournalEntry::where('user_id', $request->user()->id)->findOrFail($id);

        $updates = [];

        if ($request->has('content')) {
            $updates['content'] = $request->content;
        }
        
        // Fix para booleanos en PostgreSQL/MySQL
        if ($request->has('is_featured')) {
            $newStatus = $request->boolean('is_featured');
            $updates['is_featured'] = \Illuminate\Support\Facades\DB::raw($newStatus ? 'true' : 'false');
        }

        $entry->update($updates);

        return response()->json($entry->fresh());
    }

    // 4. ELIMINAR UNA NOTA (DELETE)
    public function destroy(Request $request, $id)
    {
        $entry = JournalEntry::where('user_id', $request->user()->id)->findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Nota eliminada correctamente']);
    }
}