<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Representa una materia del catálogo académico y sus datos curriculares.
 */
class Asignatura extends Model
{
    /** Identificadores usados para separar los catálogos por modelo educativo. */
    public const MODELO_ANTERIOR = 'anterior';
    public const MODELO_ACTUAL = 'actual';

    /** Etiquetas que se muestran en formularios y filtros. */
    private const MODELOS = [
        self::MODELO_ANTERIOR => 'Modelo educativo anterior',
        self::MODELO_ACTUAL => 'Modelo educativo actual',
    ];

    /** Abreviaturas y nombres oficiales de los programas de cada modelo. */
    private const PROGRAMAS = [
        self::MODELO_ANTERIOR => [
            'IIN' => 'Ingeniería Industrial',
            'IIF' => 'Ingeniería Financiera',
            'LAG' => 'Licenciatura en Administración y Gestión / Licenciatura en Administración de Empresas',
            'ITI' => 'Ingeniería en Tecnologías de la Información',
            'IET' => 'Ingeniería en Electrónica y Telecomunicaciones',
            'IBT' => 'Ingeniería en Biotecnología',
            'ITA' => 'Ingeniería en Tecnología Ambiental',
        ],
        self::MODELO_ACTUAL => [
            'IIN' => 'Ingeniería Industrial',
            'IFI' => 'Ingeniería Financiera',
            'LAD' => 'Licenciatura en Administración',
            'IID' => 'Ingeniería en Tecnologías de la Información e Innovación Digital',
            'ISE' => 'Ingeniería en Sistemas Electrónicos',
            'IBT' => 'Ingeniería en Biotecnología',
            'IAS' => 'Ingeniería Ambiental y Sustentabilidad',
        ],
    ];

    protected $table = 'asignaturas';
    protected $primaryKey = 'id_asignatura';
    public $timestamps = false;

    /** Una asignatura puede estar asociada con varios grupos. */
    public function grupos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Grupo::class, 'id_asignatura', 'id_asignatura');
    }

    /** Campos que pueden asignarse al crear o actualizar una asignatura. */
    protected $fillable = [
        'clave_asignatura',
        'modelo_educativo',
        'nombre_asignatura',
        'programa_educativo',
        'cuatrimestre',
    ];

    protected function casts(): array
    {
        return [
            'cuatrimestre' => 'integer',
        ];
    }

    /** Devuelve los nombres visibles de los modelos educativos disponibles. */
    public static function modelosEducativos(): array
    {
        return self::MODELOS;
    }

    /**
     * Devuelve los programas de un modelo o todos los catálogos si no se indica uno.
     */
    public static function programasEducativos(?string $modelo = null): array
    {
        return $modelo === null
            ? self::PROGRAMAS
            : (self::PROGRAMAS[$modelo] ?? []);
    }

    /** Busca la abreviatura del nombre del programa dentro del modelo indicado. */
    public static function abreviaturaDePrograma(string $modelo, string $programa): ?string
    {
        $abreviatura = array_search($programa, self::programasEducativos($modelo), true);

        // array_search devuelve false cuando el nombre no pertenece al catálogo.
        return $abreviatura === false ? null : $abreviatura;
    }

    /** Formato legible del cuatrimestre para mostrarlo en la interfaz. */
    public function getCuatrimestreEtiquetaAttribute(): string
    {
        return $this->cuatrimestre.'° Cuatrimestre';
    }
}
