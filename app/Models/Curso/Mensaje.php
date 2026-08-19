<?php

namespace App\Models\Curso;

use App\Enums\DB\TipoMensajeCurso;
use App\Models\Base\Curso\BaseMensaje;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modelo Mensaje
 *
 * Extiende de BaseMensaje (auto-generado)
 *
 * Mensajería del curso, independiente de agenda.agenda (que sigue siendo el
 * feedback de entregas por actividad). Un mensaje se sostiene en un componente:
 *
 * - Difusión: tipo_mensaje = MENSAJE_PARA_TODO_EL_CURSO, receptor NULL. La ven
 *   todos los inscritos del componente y su equipo docente.
 * - Canal individual: tipo_mensaje = MENSAJE_INDIVIDUAL. El canal es
 *   (id_componente, alumno) — todos los docentes del componente comparten el
 *   mismo canal, así que un colegiado que responde no abre una conversación
 *   aparte. id_usuario_receptor sólo marca a quién iba dirigido ese mensaje.
 */
class Mensaje extends BaseMensaje
{
    protected $casts = [
        'tipo_mensaje' => TipoMensajeCurso::class,
    ];

    /** Sólo difusiones del componente. */
    public function scopeDifusion(Builder $query): Builder
    {
        return $query->where('tipo_mensaje', TipoMensajeCurso::MENSAJE_PARA_TODO_EL_CURSO->value);
    }

    /** Sólo mensajes de canales individuales. */
    public function scopeIndividual(Builder $query): Builder
    {
        return $query->where('tipo_mensaje', TipoMensajeCurso::MENSAJE_INDIVIDUAL->value);
    }

    /**
     * Canal de un alumno en un componente: todos los mensajes individuales donde
     * el alumno es emisor o receptor, sin importar qué docente intervino.
     */
    public function scopeDelCanal(Builder $query, int $idComponente, int $idUsuarioAlumno): Builder
    {
        return $query->individual()
            ->where('id_componente', $idComponente)
            ->where(function (Builder $q) use ($idUsuarioAlumno) {
                $q->where('id_usuario_emisor', $idUsuarioAlumno)
                    ->orWhere('id_usuario_receptor', $idUsuarioAlumno);
            });
    }
}
