<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Volcado y restauración de la base de datos con las utilerías nativas (mysqldump / mysql). */
class DatabaseBackup
{
    /** Tablas cuyo contenido no se respalda: el historial de respaldos debe sobrevivir a una restauración. */
    public const TABLAS_EXCLUIDAS = ['respaldos_bd'];

    /** @return list<string> Tablas incluidas en el respaldo. */
    public function tablas(): array
    {
        $nombres = array_column(Schema::getTables(), 'name');

        return array_values(array_diff($nombres, self::TABLAS_EXCLUIDAS));
    }

    public function tamanoMb(): float
    {
        if (DB::getDriverName() !== 'mysql') {
            return 0.0;
        }
        $bytes = DB::selectOne(
            'select coalesce(sum(data_length + index_length), 0) as b from information_schema.tables where table_schema = ?',
            [DB::getDatabaseName()]
        )->b;

        return round($bytes / 1048576, 2);
    }

    /** @return array{motor: string, version: string} */
    public function gestor(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            return ['motor' => ucfirst(DB::getDriverName()), 'version' => (string) (DB::selectOne('select sqlite_version() as v')->v ?? '')];
        }
        $version = (string) DB::selectOne('select version() as v')->v;

        return [
            'motor' => stripos($version, 'mariadb') !== false ? 'MariaDB' : 'MySQL',
            'version' => preg_replace('/-.*/', '', $version),
        ];
    }

    public function espacioLibreBytes(string $directorio): int
    {
        return (int) (@disk_free_space($directorio) ?: 0);
    }

    /** Genera el volcado SQL en $destino (ruta absoluta). Lanza RuntimeException si falla. */
    public function volcar(string $destino, bool $comprimir = false): void
    {
        $c = $this->conexion();
        $sql = $comprimir ? $destino.'.tmp' : $destino;

        $comando = [
            $this->binario('dump'), '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            '--single-transaction', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4',
            '--add-drop-table', '--result-file='.$sql,
        ];
        foreach (self::TABLAS_EXCLUIDAS as $tabla) {
            $comando[] = '--ignore-table='.$c['database'].'.'.$tabla;
        }
        array_push($comando, $c['database']);

        $proceso = new Process($comando, null, ['MYSQL_PWD' => (string) $c['password']], null, config('backup.timeout'));
        $proceso->run();

        if (! $proceso->isSuccessful() || ! is_file($sql) || filesize($sql) === 0) {
            @unlink($sql);
            throw new RuntimeException(trim($proceso->getErrorOutput()) ?: 'mysqldump no generó el archivo.');
        }

        if ($comprimir) {
            $in = fopen($sql, 'rb');
            $out = gzopen($destino, 'wb6');
            while (! feof($in)) {
                gzwrite($out, fread($in, 1048576));
            }
            fclose($in);
            gzclose($out);
            unlink($sql);
        }
    }

    /** Restaura el archivo (.sql o .sql.gz) sobre la base actual. Lanza RuntimeException si falla. */
    public function cargar(string $origen): void
    {
        $c = $this->conexion();
        $flujo = fopen(str_ends_with($origen, '.gz') ? 'compress.zlib://'.$origen : $origen, 'rb');
        if (! $flujo) {
            throw new RuntimeException('No se pudo abrir el archivo de respaldo.');
        }

        $proceso = new Process([
            $this->binario('client'), '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            '--default-character-set=utf8mb4', $c['database'],
        ], null, ['MYSQL_PWD' => (string) $c['password']], $flujo, config('backup.timeout'));
        $proceso->run();
        fclose($flujo);

        if (! $proceso->isSuccessful()) {
            throw new RuntimeException(trim($proceso->getErrorOutput()) ?: 'La restauración falló.');
        }
    }

    /** Valida que el archivo sea un volcado SQL íntegro. Devuelve el mensaje de error o null si es válido. */
    public function validarArchivo(string $ruta): ?string
    {
        $gz = str_ends_with($ruta, '.gz');
        $abrir = fn () => @fopen($gz ? 'compress.zlib://'.$ruta : $ruta, 'rb');

        $f = $abrir();
        if (! $f) {
            return 'No se pudo leer el archivo.';
        }
        $inicio = (string) fread($f, 4096);
        $cola = substr($inicio, -512);
        while (! feof($f)) {
            $cola = substr($cola.(string) fread($f, 65536), -512);
        }
        fclose($f);

        if (! preg_match('/^-- (MySQL|MariaDB) dump/m', $inicio)) {
            return 'El archivo no tiene la estructura de un volcado SQL de MariaDB/MySQL generado por mysqldump.';
        }
        if (! str_contains($cola, 'Dump completed')) {
            return 'El volcado está incompleto o dañado (no termina correctamente).';
        }

        return null;
    }

    private function conexion(): array
    {
        $c = config('database.connections.'.config('database.default'));
        if (($c['driver'] ?? null) !== 'mysql' && ($c['driver'] ?? null) !== 'mariadb') {
            throw new RuntimeException('El respaldo solo está disponible para MariaDB/MySQL.');
        }

        return $c;
    }

    private function binario(string $tipo): string
    {
        $configurado = config($tipo === 'dump' ? 'backup.dump_binary' : 'backup.client_binary');
        if ($configurado) {
            return $configurado;
        }

        $nombres = $tipo === 'dump' ? ['mysqldump', 'mariadb-dump'] : ['mysql', 'mariadb'];
        $finder = new ExecutableFinder();
        $extras = ['/Applications/XAMPP/bin', '/opt/homebrew/bin', '/usr/local/bin', '/usr/local/mysql/bin', '/opt/lampp/bin', 'C:\\xampp\\mysql\\bin'];
        foreach ($nombres as $n) {
            if ($ruta = $finder->find($n, null, $extras)) {
                return $ruta;
            }
        }

        throw new RuntimeException("No se encontró la utilería {$nombres[0]}. Define DB_".($tipo === 'dump' ? 'DUMP' : 'CLIENT').'_BINARY en el .env.');
    }
}
