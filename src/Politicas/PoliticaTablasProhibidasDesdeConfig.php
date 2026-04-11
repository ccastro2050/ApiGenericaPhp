<?php
// ============================================================================
// PoliticaTablasProhibidasDesdeConfig.php — Decide si una tabla esta permitida o prohibida
// Ubicacion: src/Politicas/PoliticaTablasProhibidasDesdeConfig.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Esta clase lee la lista de tablas prohibidas de config.php y responde
//   una sola pregunta: "¿esta tabla esta permitida?"
//
//   Es como un guardia de seguridad con una lista negra:
//     - Si la lista esta vacia: deja pasar a todos.
//     - Si la lista dice ['usuario', 'rol']: bloquea esas dos tablas.
//     - Cualquier otra tabla pasa sin problema.
//
// EJEMPLO:
//   En config.php:
//     'TablasProhibidas' => ['usuario', 'rol']
//
//   Resultado:
//     esTablaPermitida('producto')  -> true   (no esta en la lista, pasa)
//     esTablaPermitida('usuario')   -> false  (esta en la lista, bloqueada)
//     esTablaPermitida('Usuario')   -> false  (la comparacion ignora mayusculas)
//
// CUANDO SE USA EN EL FLUJO:
//   1. index.php crea: $politicaTablas = new PoliticaTablasProhibidasDesdeConfig($config)
//   2. Se la pasa a ServicioCrud
//   3. Cada vez que alguien pide una tabla (GET /api/producto), ServicioCrud
//      pregunta: $this->politicaTablas->esTablaPermitida('producto')
//   4. Si retorna false, ServicioCrud devuelve error 403 (Prohibido)
// ============================================================================

namespace ApiGenericaPhp\Politicas;

use ApiGenericaPhp\Servicios\Abstracciones\IPoliticaTablasProhibidas;

// "implements IPoliticaTablasProhibidas" = esta clase CUMPLE el contrato de esa interface.
// Debe tener el metodo esTablaPermitida() que la interface exige.
class PoliticaTablasProhibidasDesdeConfig implements IPoliticaTablasProhibidas
{
    // Lista de tablas prohibidas, guardada en minusculas.
    // Ejemplo: ['usuario', 'rol']
    // Si esta vacia ([]), todas las tablas estan permitidas.
    private array $tablasProhibidas;

    // Constructor: se ejecuta al hacer new PoliticaTablasProhibidasDesdeConfig($config)
    // Recibe la configuracion completa y extrae solo la lista de tablas prohibidas.
    public function __construct(array $configuracion)
    {
        // Leer la lista de config.php. Si no existe, usar array vacio [].
        $tablas = $configuracion['TablasProhibidas'] ?? [];

        // Limpiar y normalizar la lista en un solo paso:
        //
        // 1. array_filter() = quitar elementos vacios o solo espacios.
        //    Recibe una funcion anonima que dice "quedarse solo con los no vacios".
        //    function($t) { return !empty(trim($t)); }
        //      trim($t) = quitar espacios  ->  '  ' se convierte en ''
        //      empty('') = true  ->  !true = false  ->  se descarta
        //      empty('usuario') = false  ->  !false = true  ->  se queda
        //
        // 2. array_map('strtolower', ...) = aplicar strtolower() a cada elemento.
        //    strtolower() = convertir a minusculas.
        //    'Usuario' -> 'usuario'
        //    'ROL' -> 'rol'
        //    Asi la comparacion despues es case-insensitive (no importa mayusculas).
        $this->tablasProhibidas = array_map('strtolower', array_filter($tablas, function ($t) {
            return !empty(trim($t));
        }));
    }

    // El metodo principal: ¿esta tabla esta permitida?
    // Retorna true si SI esta permitida, false si esta PROHIBIDA.
    public function esTablaPermitida(string $nombreTabla): bool
    {
        // Si el nombre esta vacio, no permitir (no tiene sentido una tabla sin nombre)
        if (empty(trim($nombreTabla))) {
            return false;
        }

        // in_array() = ¿este valor esta dentro del array?
        //   in_array('producto', ['usuario', 'rol'])  -> false (no esta)
        //   in_array('usuario', ['usuario', 'rol'])   -> true  (si esta)
        //
        // strtolower($nombreTabla) = convertir a minusculas para comparar
        //   'Producto' -> 'producto'
        //
        // El "!" al inicio invierte el resultado:
        //   ! in_array(...)
        //   Si esta en la lista prohibida: !true = false (NO permitida)
        //   Si NO esta en la lista:        !false = true (SI permitida)
        //
        // El tercer parametro "true" = comparacion estricta (compara tipo Y valor)
        return !in_array(strtolower($nombreTabla), $this->tablasProhibidas, true);
    }

    // Metodo auxiliar: devuelve la lista de tablas prohibidas.
    // Util para debugging o para mostrar al administrador que tablas estan bloqueadas.
    public function obtenerTablasProhibidas(): array
    {
        return $this->tablasProhibidas;
    }

    // Metodo auxiliar: ¿hay alguna restriccion configurada?
    // count() = contar cuantos elementos tiene el array.
    // Si count > 0, hay restricciones. Si es 0, no hay ninguna.
    public function tieneRestricciones(): bool
    {
        return count($this->tablasProhibidas) > 0;
    }
}
