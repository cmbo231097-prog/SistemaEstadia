<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

// Lee un archivo .xlsx/.xls/.csv de rubros y lo agrupa por evidencia (asignatura + tipo_evidencia).
class RubricaImportService
{
    private const MAX_FILAS = 2000;

    private const COLUMNAS_REQUERIDAS = ['asignatura', 'tipo_evidencia', 'criterio', 'puntaje_max'];

    // Encabezados alternativos aceptados para cada columna (ej. Excel muestra "puntaje_maximo" cortado).
    private const ALIAS = [
        'puntaje_maximo' => 'puntaje_max',
        'puntaje' => 'puntaje_max',
        'puntos' => 'puntaje_max',
        'puntos_maximos' => 'puntaje_max',
        'evaluable' => 'evaluable_desde_el_producto',
        'evaluable_desde_producto' => 'evaluable_desde_el_producto',
        'tipo' => 'tipo_evidencia',
        'evidencia' => 'tipo_evidencia',
        'orden' => 'orden_criterio',
    ];

    /**
     * @return array<string, array{codigo: string, asignatura: string, tipo: string, criterios: array<int, array{nombre_criterio: string, descripcion_criterio: string, puntaje_maximo: float, evaluable_ia: bool}>}>
     *
     * @throws ValidationException
     */
    public function leer(UploadedFile $archivo): array
    {
        $filas = $this->cargarFilas($archivo);
        if (count($filas) < 2) {
            $this->fallar(['El archivo no contiene filas de criterios.']);
        }

        $columnas = $this->mapearColumnas(array_shift($filas));
        $faltantes = array_diff(self::COLUMNAS_REQUERIDAS, array_keys($columnas));
        if ($faltantes !== []) {
            $this->fallar(['Faltan las columnas obligatorias: '.implode(', ', $faltantes).'.']);
        }

        $grupos = [];
        $errores = [];
        foreach ($filas as $indice => $fila) {
            $numeroFila = $indice + 2;
            $celda = fn (string $columna): string => $this->texto($fila[$columnas[$columna] ?? -1] ?? '');

            if (implode('', array_map(fn ($valor) => $this->texto($valor), $fila)) === '') {
                continue;
            }

            $asignatura = Str::upper($celda('asignatura'));
            $tipo = Str::upper($celda('tipo_evidencia'));
            $criterio = $celda('criterio');
            $puntaje = $this->numero($celda('puntaje_max'));

            if ($asignatura === '' || $tipo === '' || $criterio === '') {
                $errores[] = "Fila {$numeroFila}: asignatura, tipo_evidencia y criterio son obligatorios.";

                continue;
            }
            if ($puntaje === null || $puntaje <= 0) {
                $errores[] = "Fila {$numeroFila}: puntaje_max debe ser un número mayor que cero.";

                continue;
            }

            $codigo = "{$asignatura}-{$tipo}";
            $grupos[$codigo] ??= ['codigo' => $codigo, 'asignatura' => $asignatura, 'tipo' => $tipo, 'criterios' => []];
            $grupos[$codigo]['criterios'][] = [
                'orden' => (int) ($this->numero($celda('orden_criterio')) ?? count($grupos[$codigo]['criterios']) + 1),
                'nombre_criterio' => Str::limit($criterio, 100, ''),
                'descripcion_criterio' => Str::limit($criterio, 1000, ''),
                'puntaje_maximo' => $puntaje,
                'evaluable_ia' => $this->esEvaluable($celda('evaluable_desde_el_producto')),
            ];
        }

        if ($errores !== []) {
            $this->fallar(array_slice($errores, 0, 8));
        }
        if ($grupos === []) {
            $this->fallar(['El archivo no contiene filas de criterios.']);
        }

        foreach ($grupos as &$grupo) {
            usort($grupo['criterios'], fn (array $a, array $b) => $a['orden'] <=> $b['orden']);
            $grupo['criterios'] = array_map(
                fn (array $criterio) => array_diff_key($criterio, ['orden' => true]),
                $grupo['criterios']
            );
        }
        unset($grupo);

        return $grupos;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function cargarFilas(UploadedFile $archivo): array
    {
        try {
            $hoja = $this->lector(strtolower($archivo->getClientOriginalExtension()))
                ->load($archivo->getRealPath())
                ->getSheet(0);

            if ($hoja->getHighestDataRow() > self::MAX_FILAS) {
                $this->fallar(['El archivo excede el máximo de '.self::MAX_FILAS.' filas.']);
            }

            return $hoja->toArray(null, true, false, false);
        } catch (ValidationException $e) {
            throw $e;
        } catch (ReaderException|Throwable) {
            $this->fallar(['No se pudo leer el archivo. Verifica que sea un .xlsx, .xls o .csv válido.']);
        }
    }

    private function lector(string $extension): IReader
    {
        if ($extension === 'xlsx') {
            return new Xlsx;
        }
        if ($extension === 'xls') {
            return new Xls;
        }

        // Excel en español exporta CSV con ";" y en codificaciones distintas a UTF-8.
        $csv = new Csv;
        $csv->setDelimiter(null);
        $csv->setInputEncoding(Csv::GUESS_ENCODING);

        return $csv;
    }

    /**
     * @param  array<int, mixed>  $encabezado
     * @return array<string, int>
     */
    private function mapearColumnas(array $encabezado): array
    {
        $columnas = [];
        foreach ($encabezado as $posicion => $valor) {
            $nombre = Str::of($this->texto($valor))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $nombre = self::ALIAS[$nombre] ?? $nombre;
            if ($nombre !== '' && ! isset($columnas[$nombre])) {
                $columnas[$nombre] = $posicion;
            }
        }

        return $columnas;
    }

    private function texto(mixed $valor): string
    {
        $texto = trim((string) $valor);

        // Excel de Mac abre en MacRoman los CSV en UTF-8 y guarda "ñ" como "√±"; se revierte esa conversión.
        if (str_contains($texto, '√') && function_exists('iconv')) {
            $reparado = @iconv('UTF-8', 'MACINTOSH//IGNORE', $texto);
            if (is_string($reparado) && $reparado !== '' && mb_check_encoding($reparado, 'UTF-8')) {
                $texto = $reparado;
            }
        }

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    private function numero(string $valor): ?float
    {
        $valor = str_replace(',', '.', $valor);

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function esEvaluable(string $valor): bool
    {
        // Una celda vacía se interpreta como evaluable; solo una negativa explícita excluye el criterio.
        return ! in_array(Str::of($valor)->ascii()->lower()->toString(), ['no', 'n', '0', 'false', 'falso'], true);
    }

    /**
     * @param  array<int, string>  $mensajes
     *
     * @throws ValidationException
     */
    private function fallar(array $mensajes): never
    {
        throw ValidationException::withMessages(['archivo' => $mensajes]);
    }
}
