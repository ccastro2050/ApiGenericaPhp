<?php
// ============================================================================
// IRepositorioLecturaTabla.php — Interface (contrato) para acceso a datos
// Ubicacion: src/Repositorios/Abstracciones/IRepositorioLecturaTabla.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Define QUE operaciones puede hacer un repositorio, pero NO dice COMO.
//   Es un contrato: "cualquier repositorio que implemente esta interface
//   DEBE tener estos metodos".
//
//   Gracias a esto, el servicio (ServicioCrud) no sabe ni le importa
//   si los datos vienen de MySQL, PostgreSQL, un archivo JSON, o una API.
//   Solo sabe que tiene un objeto con estos metodos disponibles.
//
// QUE ES UNA INTERFACE:
//   Es como un contrato o una lista de requisitos.
//   No tiene codigo adentro — solo dice "debes tener estos metodos".
//   La clase que la implemente es la que escribe el codigo real.
//
//   Ejemplo de la vida real:
//     Interface "Vehiculo": debe tener acelerar(), frenar(), girar()
//     Clase "Auto" implements Vehiculo: acelerar() { piso el pedal del acelerador }
//     Clase "Bicicleta" implements Vehiculo: acelerar() { pedaleo mas rapido }
//     Ambos cumplen el contrato, pero lo hacen de forma diferente.
//
// CUANDO SE USA EN EL FLUJO:
//   index.php crea: $repositorio = new RepositorioLecturaMysqlMariaDB(...)
//   Ese repositorio implementa ESTA interface.
//   Luego se lo pasa a ServicioCrud, que lo usa sin saber que es MySQL.
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C#:        interface IRepositorioLecturaTabla { ... }
//   - Java:      interface IRepositorioLecturaTabla { ... }
//   - Python:    class IRepositorioLecturaTabla(ABC): @abstractmethod ...
//   - TypeScript: interface IRepositorioLecturaTabla { ... }
// ============================================================================

// namespace = el "apellido" de esta clase, para organizarla y evitar conflictos de nombres.
// Es como la estructura de carpetas pero en el codigo.
// ApiGenericaPhp\Repositorios\Abstracciones = esta en src/Repositorios/Abstracciones/
namespace ApiGenericaPhp\Repositorios\Abstracciones;

// "interface" = definir un contrato (no una clase).
// No se puede hacer "new IRepositorioLecturaTabla()" — solo se puede implementar.
interface IRepositorioLecturaTabla
{
    // -----------------------------------------------------------------------
    // obtenerFilasAsync — Listar registros de una tabla
    // -----------------------------------------------------------------------
    // Ejemplo: obtenerFilasAsync('producto', null, 10)
    //   -> Devuelve las primeras 10 filas de la tabla 'producto'
    //   -> Retorna: [['codigo'=>'PR001','nombre'=>'Laptop',...], ['codigo'=>'PR002',...], ...]
    //
    // "public function" = metodo publico (cualquiera puede llamarlo)
    // ": array" al final = el tipo de dato que RETORNA (un array)
    // "?string" = puede ser string O null (el ? significa "puede ser null")
    // "?int" = puede ser int O null
    public function obtenerFilasAsync(string $nombreTabla, ?string $esquema, ?int $limite): array;

    // -----------------------------------------------------------------------
    // obtenerPorClaveAsync — Buscar registros por una columna especifica
    // -----------------------------------------------------------------------
    // Ejemplo: obtenerPorClaveAsync('producto', null, 'codigo', 'PR001')
    //   -> Busca en la tabla 'producto' donde la columna 'codigo' sea 'PR001'
    //   -> Retorna: [['codigo'=>'PR001','nombre'=>'Laptop','stock'=>15,...]]
    public function obtenerPorClaveAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valor): array;

    // -----------------------------------------------------------------------
    // crearAsync — Insertar un registro nuevo
    // -----------------------------------------------------------------------
    // Ejemplo: crearAsync('producto', null, ['codigo'=>'PR099','nombre'=>'Test','stock'=>5,'valorunitario'=>1000])
    //   -> Inserta una fila nueva en la tabla 'producto'
    //   -> Retorna: true si se inserto, false si no
    //
    // $camposEncriptar: si se pasa 'contrasena', ese campo se hashea con BCrypt antes de guardar
    // "= null" al final = valor por defecto (si no se pasa, es null)
    public function crearAsync(string $nombreTabla, ?string $esquema, array $datos, ?string $camposEncriptar = null): bool;

    // -----------------------------------------------------------------------
    // actualizarAsync — Modificar un registro existente
    // -----------------------------------------------------------------------
    // Ejemplo: actualizarAsync('producto', null, 'codigo', 'PR001', ['stock'=>99])
    //   -> Actualiza el producto con codigo PR001, le pone stock=99
    //   -> Retorna: 1 (una fila afectada) o 0 (no encontro el registro)
    //
    // ": int" = retorna un entero (el numero de filas que se modificaron)
    public function actualizarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave, array $datos, ?string $camposEncriptar = null): int;

    // -----------------------------------------------------------------------
    // eliminarAsync — Borrar un registro
    // -----------------------------------------------------------------------
    // Ejemplo: eliminarAsync('producto', null, 'codigo', 'PR001')
    //   -> Elimina el producto con codigo PR001
    //   -> Retorna: 1 (una fila eliminada) o 0 (no encontro el registro)
    public function eliminarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave): int;

    // -----------------------------------------------------------------------
    // obtenerHashContrasenaAsync — Obtener el hash de contrasena de un usuario
    // -----------------------------------------------------------------------
    // Ejemplo: obtenerHashContrasenaAsync('usuario', null, 'email', 'contrasena', 'admin@correo.com')
    //   -> Busca en la tabla 'usuario' donde email='admin@correo.com'
    //   -> Retorna el valor de la columna 'contrasena' (el hash BCrypt)
    //   -> O null si no encontro el usuario
    //
    // ": ?string" = retorna string o null (null si no existe el usuario)
    public function obtenerHashContrasenaAsync(string $nombreTabla, ?string $esquema, string $campoUsuario, string $campoContrasena, string $valorUsuario): ?string;

    // -----------------------------------------------------------------------
    // obtenerDiagnosticoConexionAsync — Info tecnica de la conexion a la BD
    // -----------------------------------------------------------------------
    // Retorna datos como: version del servidor, nombre de la BD, usuario conectado, etc.
    // Se usa para el endpoint GET /api/info
    public function obtenerDiagnosticoConexionAsync(): array;
}
