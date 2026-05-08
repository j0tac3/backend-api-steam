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

    public function store(Request $request, $gameId)
    {
        $request->validate(['content' => 'required|string']);
        $game = $request->user()->games()->findOrFail($gameId);

        // No enviamos 'is_featured'. Postgres le pondrá 'false' automáticamente.
        $entry = $game->journalEntries()->create([
            'content' => $request->content
        ]);

        return response()->json($entry, 201);
    }

    // 3. EDITAR UNA NOTA EXISTENTE O CAMBIAR LA ESTRELLA (PATCH)
    public function update(Request $request, $id)
    {
        // 1. Buscamos la nota asegurándonos de que pertenezca al usuario
        $entry = JournalEntry::whereHas('game', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);

        // 2. Si viene contenido, lo actualizamos
        if ($request->has('content')) {
            $entry->content = $request->content;
        }
        
        // 3. Si viene la estrella, forzamos el tipo booleano real de PHP
        if ($request->has('is_featured')) {
            // $request->boolean() convierte "true", 1, "1" en true real de PHP
            $entry->is_featured = $request->boolean('is_featured');
        }

        // 4. Guardamos
        $entry->save();

        // 5. Devolvemos el objeto fresco recién sacado de la base de datos
        return response()->json($entry->fresh());
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