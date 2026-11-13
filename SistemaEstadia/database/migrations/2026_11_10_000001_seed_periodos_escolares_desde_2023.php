<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Tres periodos por año; el código es "año-n" (n = 1, 2 o 3), igual que el existente 2026-3.
    private const PERIODOS = [
        1 => ['Enero - Abril', '01-01', '04-30'],
        2 => ['Mayo - Agosto', '05-01', '08-31'],
        3 => ['Septiembre - Diciembre', '09-01', '12-31'],
    ];

    public function up(): void
    {
        // Desde Septiembre - Diciembre 2023 hasta el periodo vigente (Septiembre - Diciembre 2026).
        foreach (range(2023, 2026) as $anio) {
            foreach (self::PERIODOS as $numero => [$nombre, $inicio, $fin]) {
                if (($anio === 2023 && $numero < 3)) {
                    continue;
                }

                $codigo = "{$anio}-{$numero}";
                if (DB::table('periodos_escolares')->where('codigo_periodo', $codigo)->exists()) {
                    continue;
                }

                DB::table('periodos_escolares')->insert([
                    'codigo_periodo' => $codigo,
                    'nombre_periodo' => "{$nombre} {$anio}",
                    'fecha_inicio' => "{$anio}-{$inicio}",
                    'fecha_fin' => "{$anio}-{$fin}",
                    // Solo el periodo vigente queda activo; es el que se propone al registrar grupos.
                    'activo' => $codigo === '2026-3' ? 1 : 0,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Los periodos pueden estar vinculados a grupos existentes: no se eliminan.
    }
};
