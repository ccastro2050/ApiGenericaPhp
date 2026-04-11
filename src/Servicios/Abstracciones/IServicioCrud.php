<?php
// ============================================================================
// IServicioCrud.php — Interface (contrato) para la logica de negocio
// Ubicacion: src/Servicios/Abstracciones/IServicioCrud.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Define QUE operaciones de negocio existen, pero NO dice COMO se hacen.
//   Es el contrato entre el controlador y el servicio.
//
//   El CONTROLADOR (EntidadesController) usa esta interface para delegar
//   la logica. No le importa si el servicio valida, transforma o filtra —
//   solo sabe que tiene estos metodos disponibles.
//
// DIFERENCIA ENTRE REPOSITORIO Y SERVICIO:
//   Repositorio = acceso a datos puros ("dame todas las filas de esta tabla")
//   Servicio    = logica de negocio ("dame las filas, pero primero verifica
//                 que la tabla no este prohibida, valida los parametros, etc.")
//
//   Analogia del restaurante:
//     Repositorio = la bodega ("dame todos los tomates")
//     Servicio    = el chef ("dame tomates, pero solo los maduros, maximo 10")
//     Controlador = el mesero ("el cliente pidio ensalada, preparala")
//
// CUANDO SE USA EN EL FLUJO:
//   index.php crea: $servicioCrud = new ServicioCrud($repositorio, $politica)
//   ServicioCrud implementa ESTA interface.
//   Luego se lo pasa al controlador, que lo usa para todo.
// ============================================================================

namespace ApiGenericaPhp\Servicios\Abstracciones;

interface IServicioCrud
{
    // Listar registros de una tabla (con validaciones de negocio)
    // Ejemplo: listarAsync('producto', null, 10) -> lista 10 productos
    public function listarAsync(string $nombreTabla, ?string $esquema, ?int $limite): array;

    // Buscar registros por una columna especifica
    // Ejemplo: obtenerPorClaveAsync('producto', null, 'codigo', 'PR001')
    public function obtenerPorClaveAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valor): array;

    // Crear un registro nuevo
    // Ejemplo: crearAsync('producto', null, ['codigo'=>'PR099','nombre'=>'Test',...])
    public function crearAsync(string $nombreTabla, ?string $esquema, array $datos, ?string $camposEncriptar = null): bool;

    // Actualizar un registro existente
    // Ejemplo: actualizarAsync('producto', null, 'codigo', 'PR001', ['stock'=>99])
    public function actualizarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave, array $datos, ?string $camposEncriptar = null): int;

    // Eliminar un registro
    // Ejemplo: eliminarAsync('producto', null, 'codigo', 'PR001')
    public function eliminarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave): int;

    // Verificar credenciales de usuario (comparar contrasena con hash BCrypt)
    // Ejemplo: verificarContrasenaAsync('usuario', null, 'email', 'contrasena', 'admin@correo.com', 'admin123')
    //   -> Retorna: ['codigo' => 200, 'mensaje' => 'Credenciales correctas']
    //   -> O:       ['codigo' => 401, 'mensaje' => 'Contrasena incorrecta']
    //   -> O:       ['codigo' => 404, 'mensaje' => 'Usuario no encontrado']
    public function verificarContrasenaAsync(string $nombreTabla, ?string $esquema, string $campoUsuario, string $campoContrasena, string $valorUsuario, string $valorContrasena): array;
}
