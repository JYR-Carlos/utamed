<?php

namespace App\Support\DataGenerators;

class ChileanNameGenerator
{
  private static array $nombres = [
    'hombre' => [
      'Juan', 'Carlos', 'Pedro', 'Diego', 'Miguel', 'Roberto', 'Fernando', 'Alejandro',
      'Jorge', 'Sergio', 'Andrés', 'Tomás', 'Vicente', 'Francisco', 'Antonio', 'Agustín',
      'Álvaro', 'Felipe', 'Gabriel', 'Gonzalo', 'Guillermo', 'Héctor', 'Hugo', 'Ignacio',
      'Iván', 'Jaime', 'Joaquín', 'Lucas', 'Luis', 'Manuel', 'Mario', 'Martín',
      'Matías', 'Mauricio', 'Nicolás', 'Pablo',
    ],
    'mujer' => [
      'María', 'Ana', 'Laura', 'Sofía', 'Isabel', 'Patricia', 'Claudia', 'Verónica',
      'Marcela', 'Valeria', 'Cristina', 'Gabriela', 'Francisca', 'Elena', 'Rosa', 'Adriana',
      'Alicia', 'Amalia', 'Bárbara', 'Beatriz', 'Camila', 'Carolina', 'Catalina', 'Cecilia',
      'Constanza', 'Daniela', 'Diana', 'Elisa', 'Evelyn', 'Fabiola', 'Fernanda', 'Florencia',
      'Gloria', 'Javiera', 'Josefina', 'Karina', 'Lucía', 'Luisa', 'Manuela', 'Natalia', 'Nicole',
    ],
  ];

  private static array $nombresSegundos = [
    'hombre' => ['José', 'Luis', 'Francisco', 'Manuel', 'Pablo', 'Raúl', 'Esteban', 'Javier', 'Ricardo', 'Enrique', '', ''],
    'mujer'  => ['Carmen', 'Rosa', 'Magdalena', 'Teresa', 'Gloria', 'Ester', 'Petra', 'Elena', '', ''],
  ];

  private static array $apellidos = [
    'García', 'Rodríguez', 'Martínez', 'López', 'González', 'Sánchez', 'Pérez', 'Fernández',
    'Torres', 'Morales', 'Díaz', 'Castro', 'Ramírez', 'Vargas', 'Flores', 'Gutiérrez',
    'Rojas', 'Núñez', 'Castillo', 'Medina', 'Herrera', 'Vega', 'Soto', 'Silva',
    'Romero', 'Escobar', 'Araya', 'Contreras', 'Espinoza', 'Fuentes',
  ];

  public static function generarNombre(): array
  {
    $genero   = array_rand(self::$nombres);
    $nombres  = self::$nombres[$genero];
    $segundos = self::$nombresSegundos[$genero];

    return [
      'nombre1'   => $nombres[array_rand($nombres)],
      'nombre2'   => $segundos[array_rand($segundos)] ?: null,
      'apellido1' => self::$apellidos[array_rand(self::$apellidos)],
      'apellido2' => self::$apellidos[array_rand(self::$apellidos)],
    ];
  }

  /**
   * RUT en el formato en que se guarda: sin puntos y con guion ({@see \App\Support\Rut}).
   *
   * Antes salía con puntos y era la fuente de la mayoría de los RUT "duplicados":
   * lo sembrado ("23.671.848-4") y lo que crea la aplicación ("23671848-4") son
   * la misma persona escrita de dos formas, y el UNIQUE de la columna no lo ve.
   */
  public static function generarRUT(): string
  {
    $numero = rand(9000000, 25999999);
    $digito = self::calcularDigitoVerificador($numero);

    return $numero . '-' . $digito;
  }

  private static function calcularDigitoVerificador(int $rut): string
  {
    $suma = 0;
    $mult = 2;

    foreach (array_reverse(str_split((string) $rut)) as $d) {
      $suma += $d * $mult;
      $mult  = $mult > 6 ? 2 : $mult + 1;
    }

    $dv = 11 - ($suma % 11);

    return match ($dv) {
      11      => '0',
      10      => 'K',
      default => (string) $dv,
    };
  }

  public static function generarAtributosDocentes(): array
  {
    $mapa = [
      'Licenciado'   => [['Profesor de Aula', 'Educador', 'Especialista'],                        ['Profesor Horario', 'Profesor Jornada Completa']],
      'Ingeniero'    => [['Académico Ingeniero', 'Especialista Técnico', 'Investigador'],         ['Profesor Jornada Completa', 'Profesor Medio Tiempo']],
      'Magíster'     => [['Investigador', 'Coordinador', 'Profesor Asistente'],                   ['Profesor Jornada Completa', 'Profesor Investigador']],
      'Doctor'       => [['Investigador Senior', 'Coordinador de Área', 'Director de Programa'], ['Profesor Jornada Completa', 'Director de Posgrado']],
      'Especialista' => [['Especialista Clínico', 'Asesor Técnico', 'Consultor'],                 ['Profesor Horario', 'Profesor Consultor']],
    ];

    $grado = array_rand($mapa);
    [$titulos, $cargos] = $mapa[$grado];

    return [
      'grado'  => $grado,
      'titulo' => $titulos[array_rand($titulos)],
      'cargo'  => $cargos[array_rand($cargos)],
    ];
  }
}
