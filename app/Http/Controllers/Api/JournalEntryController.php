<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Models\Game; // Ajusta si tu modelo se llama de otra forma

class JournalEntryController extends Controller
{
    // 1. OBTENER TODAS LAS NOTAS DE UN JUEGO (GET)
    public function index(Request $request, $gameId)
    {
        // Validamos que el juego pertenezca al usuario autenticado
        $game = $request->user()->games()->findOrFail($gameId);
        
        // Gracias a la relación del modelo, ya vienen ordenadas por 'created_at' desc
        return response()->json($game->journalEntries);
    }

    // 2. CREAR UNA NUEVA NOTA (POST)
    public function store(Request $request, $gameId)
    {
        $request->validate([
            'content' => 'required|string',
            'is_featured' => 'boolean'
        ]);

        $game = $request->user()->games()->findOrFail($gameId);

        $entry = $game->journalEntries()->create([
            'content' => $request->content,
            'is_featured' => $request->input('is_featured', false)
        ]);

        return response()->json($entry, 201);
    }

    // 3. EDITAR UNA NOTA EXISTENTE O CAMBIAR LA ESTRELLA (PATCH)
    public function update(Request $request, $id)
    {
        // Buscamos la nota asegurándonos de que su juego pertenezca a este usuario
        $entry = JournalEntry::whereHas('game', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);

        // Actualizamos solo lo que nos mande Angular (texto o estrella)
        if ($request->has('content')) {
            $entry->content = $request->content;
        }
        if ($request->has('is_featured')) {
            $entry->is_featured = $request->input('is_featured');
        }

        $entry->save();

        return response()->json($entry);
    }

    // 4. ELIMINAR UNA NOTA (DELETE)
    public function destroy(Request $request, $id)
    {
        $entry = JournalEntry::whereHas('game', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);

        $entry->delete();

        return response()->json(['message' => 'Nota eliminada correctamente']);
    }
}