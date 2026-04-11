<?php
// ============================================================================
// IPoliticaTablasProhibidas.php — Interface (contrato) para politica de acceso a tablas
// Ubicacion: src/Servicios/Abstracciones/IPoliticaTablasProhibidas.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Define UN solo metodo: "¿esta tabla esta permitida, si o no?"
//   El servicio (ServicioCrud) pregunta esto ANTES de hacer cualquier
//   operacion CRUD. Si la tabla esta prohibida, devuelve error 403.
//
//   El servicio no sabe de donde viene la lista de tablas prohibidas.
//   Podria venir de config.php, de la BD, de un archivo JSON, de una API...
//   Solo pregunta y recibe true o false.
//
// EJEMPLO:
//   Si en config.php pones: 'TablasProhibidas' => ['usuario', 'rol']
//   Entonces:
//     esTablaPermitida('producto')  -> true  (permitida, se puede usar)
//     esTablaPermitida('usuario')   -> false (prohibida, el servicio devuelve error)
//
// CUANDO SE USA EN EL FLUJO:
//   1. Llega: GET /api/producto
//   2. El controlador llama a: $servicio->listarAsync('producto', ...)
//   3. ServicioCrud pregunta: $politica->esTablaPermitida('producto')
//      ^^^^ ACA se usa
//   4. Si es true, sigue con la consulta. Si es false, devuelve error 403.
// ============================================================================

namespace ApiGenericaPhp\Servicios\Abstracciones;

interface IPoliticaTablasProhibidas
{
    // Devuelve true si la tabla esta PERMITIDA, false si esta PROHIBIDA.
    // Ejemplo: esTablaPermitida('producto') -> true
    //          esTablaPermitida('usuario')  -> false (si esta en la lista negra)
    public function esTablaPermitida(string $nombreTabla): bool;
}
