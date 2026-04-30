<?php

namespace App\Services;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    public function translateToSpanish(?string $text): ?string
    {
        if (empty($text)) {
            return $text;
        }

        try {
            // Traduce desde cualquier idioma (auto) al Español (es)
            $tr = new GoogleTranslate('es', 'auto'); 
            return $tr->translate($text);

        } catch (\Exception $e) {
            Log::error('Error traduciendo con Stichoza: ' . $e->getMessage());
            return $text; // Si falla, devolvemos el texto original en inglés
        }
    }
}