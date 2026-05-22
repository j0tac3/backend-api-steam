<?php

namespace App\Services;

class ImageHashService
{
    /**
     * Descarga una imagen desde una URL y genera su huella dactilar (dHash de 64 bits)
     */
    /**
     * Descarga una imagen, calcula su peso real y genera su huella dactilar
     * Devuelve un array con ['hash' => string, 'size' => int]
     */
    public function getHashAndSize(string $url): ?array
    {
        try {
            // 1. Descargamos la imagen a la memoria
            $imageData = @file_get_contents($url);
            if (!$imageData) return null;

            // 🚀 EL TRUCO DE CALIDAD: Medimos cuánto pesa el archivo crudo en bytes
            $sizeInBytes = strlen($imageData);

            $image = @imagecreatefromstring($imageData);
            if (!$image) return null;

            // 2. Reducimos la imagen a 9x8 píxeles
            $thumb = imagecreatetruecolor(9, 8);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

            // 3. Pasamos a escala de grises
            imagefilter($thumb, IMG_FILTER_GRAYSCALE);

            $hash = '';
            
            // 4. Comparamos los píxeles adyacentes
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $leftPixel = imagecolorat($thumb, $x, $y) & 0xFF;
                    $rightPixel = imagecolorat($thumb, $x + 1, $y) & 0xFF;
                    $hash .= ($leftPixel > $rightPixel) ? '1' : '0';
                }
            }

            imagedestroy($image);
            imagedestroy($thumb);

            // Devolvemos tanto el hash como el peso en bytes
            return [
                'hash' => $hash,
                'size' => $sizeInBytes
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Compara dos Hashes y devuelve el porcentaje de similitud visual (0 a 100%)
     */
    public function calculateSimilarity(?string $hash1, ?string $hash2): float
    {
        if (!$hash1 || !$hash2 || strlen($hash1) !== 64 || strlen($hash2) !== 64) {
            return 0;
        }

        $diferencias = 0;
        
        // Comparamos bit a bit (Distancia de Hamming)
        for ($i = 0; $i < 64; $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $diferencias++;
            }
        }

        // Devolvemos el porcentaje exacto de similitud
        return ((64 - $diferencias) / 64) * 100;
    }
}