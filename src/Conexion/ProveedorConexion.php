<?php
// ============================================================================
// ProveedorConexion.php — Lee config.php y entrega datos de conexion a la BD
// Ubicacion: src/Conexion/ProveedorConexion.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Esta clase lee la configuracion de config.php y sabe responder:
//     - "¿Que motor de BD estamos usando?" -> getProveedorActual()  -> 'MariaDB'
//     - "¿Cual es el DSN de conexion?"     -> obtenerDsn()          -> 'mysql:host=localhost;...'
//     - "¿Cual es el usuario?"             -> obtenerUsuario()      -> 'root'
//     - "¿Cual es la contrasena?"          -> obtenerContrasena()   -> ''
//
//   El repositorio (RepositorioLecturaMysqlMariaDB) usa estos datos
//   para conectarse a la BD con PDO. El repositorio NO lee config.php
//   directamente — le pide los datos a esta clase.
//
// CUANDO SE USA EN EL FLUJO:
//   1. index.php crea: $proveedorConexion = new ProveedorConexion($config)
//      ^^^^ Se crea ACA, recibiendo la config
//   2. index.php pasa $proveedorConexion al repositorio
//   3. Cada vez que el repositorio necesita conectarse a la BD, llama a:
//      $this->proveedorConexion->obtenerDsn()
//      $this->proveedorConexion->obtenerUsuario()
//      $this->proveedorConexion->obtenerContrasena()
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C#:     Lee IConfiguration (appsettings.json) y da ConnectionString
//   - Java:   DataSource / application.properties
//   - Python: Lee .env o settings.py y da DATABASE_URL
// ============================================================================

// namespace = "apellido" de la clase, para organizarla.
// Esta clase esta en src/Conexion/, entonces su namespace es ApiGenericaPhp\Conexion
namespace ApiGenericaPhp\Conexion;

// "use" importa la interface que esta clase va a implementar.
// Es como decir: "voy a usar el contrato IProveedorConexion".
use ApiGenericaPhp\Servicios\Abstracciones\IProveedorConexion;

// "class ... implements ..." = esta clase CUMPLE el contrato de la interface.
// Eso significa que DEBE tener todos los metodos que la interface define:
// getProveedorActual(), obtenerDsn(), obtenerUsuario(), obtenerContrasena()
class ProveedorConexion implements IProveedorConexion
{
    // "private" = solo esta clase puede acceder a esta variable (nadie de afuera).
    // "array" = el tipo de dato (un array asociativo).
    // $configuracion = aca se guarda todo lo que viene de config.php.
    private array $configuracion;

    // __construct() = el CONSTRUCTOR. Se ejecuta automaticamente cuando haces:
    //   $proveedorConexion = new ProveedorConexion($config);
    //
    // Recibe $configuracion (el array de config.php) y lo guarda en $this->configuracion.
    // "$this" = "yo mismo" (la instancia actual de esta clase).
    // "$this->configuracion" = la propiedad 'configuracion' de ESTA instancia.
    public function __construct(array $configuracion)
    {
        $this->configuracion = $configuracion; // Guardar la config para usarla despues
    }

    // Devuelve el nombre del motor de BD configurado (ej: 'MariaDB').
    // Lee $config['DatabaseProvider'] de la configuracion.
    // Si no existe o esta vacio, devuelve 'MariaDB' por defecto.
    public function getProveedorActual(): string
    {
        // ?? '' = si no existe la clave 'DatabaseProvider', usar string vacio
        $valor = $this->configuracion['DatabaseProvider'] ?? '';

        // trim() = quitar espacios al inicio y final del string
        //   trim('  MariaDB  ') -> 'MariaDB'
        // empty() = verificar si esta vacio (null, '', 0, false)
        // Si esta vacio, devolver 'MariaDB' como default
        return empty(trim($valor)) ? 'MariaDB' : trim($valor);
    }

    // Construye y devuelve el DSN (Data Source Name) para conectarse con PDO.
    // El DSN es un string con formato especifico que PDO necesita para saber
    // a donde conectarse.
    //
    // Ejemplo de lo que retorna:
    //   'mysql:host=localhost;port=3306;dbname=bdfacturas_mariadb_local;charset=utf8mb4'
    public function obtenerDsn(): string
    {
        $proveedor = $this->getProveedorActual();             // 'MariaDB'
        $datos = $this->obtenerDatosConexion($proveedor);     // El array con host, port, etc.

        // sprintf() = como un template de string. Los %s y %d son huecos que se llenan:
        //   %s = string (texto)
        //   %d = digito (numero entero)
        //
        // Resultado: 'mysql:host=localhost;port=3306;dbname=bdfacturas_mariadb_local;charset=utf8mb4'
        //
        // NOTA: Tanto MySQL como MariaDB usan el driver 'mysql' de PDO.
        // Si fuera PostgreSQL, seria 'pgsql:host=...'
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $datos['host'],                // localhost
            $datos['port'],                // 3306
            $datos['database'],            // bdfacturas_mariadb_local
            $datos['charset'] ?? 'utf8mb4' // utf8mb4 (si no esta definido, usar utf8mb4)
        );
    }

    // Devuelve el usuario de la BD. Lee de config.php.
    // Si no esta definido, devuelve 'root' por defecto.
    public function obtenerUsuario(): string
    {
        $datos = $this->obtenerDatosConexion($this->getProveedorActual());
        return $datos['username'] ?? 'root'; // ?? 'root' = si no existe, usar 'root'
    }

    // Devuelve la contrasena de la BD. Lee de config.php.
    // Si no esta definida, devuelve '' (vacia) — que es el default de XAMPP.
    public function obtenerContrasena(): string
    {
        $datos = $this->obtenerDatosConexion($this->getProveedorActual());
        return $datos['password'] ?? ''; // ?? '' = si no existe, usar string vacio
    }

    // -----------------------------------------------------------------------
    // METODO PRIVADO (solo lo usa esta clase internamente)
    // -----------------------------------------------------------------------
    // Busca y devuelve el array de datos de conexion para un proveedor especifico.
    // Es un helper que usan obtenerDsn(), obtenerUsuario() y obtenerContrasena().
    //
    // "private" = solo esta clase puede llamar a este metodo.
    // Los metodos de arriba son "public" (los llama el repositorio desde afuera).
    //
    // Ejemplo: obtenerDatosConexion('MariaDB') retorna:
    //   ['host'=>'localhost', 'port'=>3306, 'database'=>'bdfacturas_mariadb_local', ...]
    private function obtenerDatosConexion(string $proveedor): array
    {
        // Leer la seccion 'ConnectionStrings' de la config
        $connectionStrings = $this->configuracion['ConnectionStrings'] ?? [];

        // isset() = verificar si existe esa clave en el array
        // Si no existe configuracion para ese proveedor, lanzar error
        if (!isset($connectionStrings[$proveedor])) {
            // "throw" = lanzar una excepcion (error controlado).
            // RuntimeException = tipo de error que indica un problema en tiempo de ejecucion.
            // El mensaje explica que paso y como solucionarlo.
            throw new \RuntimeException(
                "No se encontro la cadena de conexion para el proveedor '{$proveedor}'. " .
                "Verificar que existe 'ConnectionStrings.{$proveedor}' en config/config.php " .
                "y que 'DatabaseProvider' este configurado correctamente."
            );
            // El "." entre strings los CONCATENA (une). Es como + en otros lenguajes.
        }

        return $connectionStrings[$proveedor]; // Devolver el array de datos de conexion
    }
}
