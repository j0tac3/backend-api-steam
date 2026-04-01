use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Borramos la regla que impide repetir el ID del juego
            $table->dropUnique('games_steam_appid_unique');
            
            // Opcional pero recomendado: Creamos una regla combinada
            // Así evitamos que un MISMO usuario guarde el MISMO juego dos veces
            $table->unique(['user_id', 'steam_appid']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'steam_appid']);
            $table->unique('steam_appid');
        });
    }
};