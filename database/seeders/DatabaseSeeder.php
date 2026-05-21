<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Store;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /* // 1. Creamos un Usuario de prueba (Si no existe ya)
        $user = User::firstOrCreate(
            ['email' => 'admin@gamer.com'],
            ['name' => 'Jugador1', 'password' => bcrypt('password')]
        );

        // 2. Creamos Plataformas
        $pc = Platform::create(['name' => 'PC', 'family' => 'PC', 'igdb_id' => 6]);
        $ps5 = Platform::create(['name' => 'PlayStation 5', 'family' => 'PlayStation', 'igdb_id' => 167]);

        // 3. Creamos Tiendas
        $steam = Store::create(['name' => 'Steam', 'slug' => 'steam', 'icon_name' => 'icon-steam']);
        $psn = Store::create(['name' => 'PlayStation Store', 'slug' => 'psn', 'icon_name' => 'icon-psn']);

        // 4. Creamos Géneros
        $rpg = Genre::create(['name' => 'Role-playing (RPG)', 'slug' => 'role-playing-rpg']);
        $adventure = Genre::create(['name' => 'Adventure', 'slug' => 'adventure']);

        // 5. Creamos el Juego Base (Demostrando los JSON casts)
        $witcher = Game::create([
            'category' => 'main_game',
            'name' => 'The Witcher 3: Wild Hunt',
            'slug' => 'the-witcher-3-wild-hunt',
            'summary' => 'Geralt of Rivia, a monster hunter, journeys through the Northern Realms...',
            'release_date' => '2015-05-19',
            'rating' => 96.5,
            'localized_data' => [
                'es' => [
                    'name' => 'The Witcher 3: Wild Hunt',
                    'summary' => 'Geralt de Rivia viaja por los Reinos del Norte en busca de Ciri.'
                ]
            ],
            'supported_languages' => [
                'audio' => ['en', 'pl', 'es', 'fr'],
                'subtitles' => ['en', 'pl', 'es', 'fr', 'de', 'it', 'ja']
            ]
        ]);

        // 6. Añadimos el contenido Multimedia (Carátula y Tráiler)
        $witcher->media()->createMany([
            ['type' => 'cover', 'source' => 'igdb', 'path' => 'co1wyy', 'is_primary' => true],
            ['type' => 'trailer', 'source' => 'steam', 'path' => 'https://steamcdn-a.akamaihd.net/steam/apps/256658589/movie480.mp4', 'is_primary' => false],
        ]);

        // 7. Enlazamos el juego con el catálogo mundial (Pivotes)
        $witcher->platforms()->attach([$pc->id, $ps5->id]);
        $witcher->genres()->attach([$rpg->id, $adventure->id]);
        $witcher->stores()->attach($steam->id, [
            'external_id' => '292030', 
            'external_url' => 'https://store.steampowered.com/app/292030/'
        ]);

        // 8. Creamos un DLC y lo enlazamos al juego base
        Game::create([
            'parent_id' => $witcher->id,
            'category' => 'expansion',
            'name' => 'The Witcher 3: Blood and Wine',
            'slug' => 'the-witcher-3-blood-and-wine',
            'release_date' => '2016-05-31',
        ])->media()->create(['type' => 'cover', 'source' => 'igdb', 'path' => 'co1x4b', 'is_primary' => true]);

        // 9. ¡La Colección del Usuario! Añadimos el juego a la biblioteca del jugador
        $user->games()->attach($witcher->id, [
            'platform_id' => $pc->id,
            'store_id' => $steam->id,
            'status' => 'completed',
            'playtime_minutes' => 8450, // ¡Más de 140 horas!
            'is_favorite' => true
        ]); */
    }
}