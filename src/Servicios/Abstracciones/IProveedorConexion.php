<?php
// ============================================================================
// IProveedorConexion.php — Interface (contrato) para datos de conexion a la BD
// Ubicacion: src/Servicios/Abstracciones/IProveedorConexion.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Define QUE datos de conexion se pueden pedir, pero NO dice de donde salen.
//   Es el contrato que dice: "cualquier proveedor de conexion debe poder dar
//   el DSN, el usuario y la contrasena".
//
//   Actualmente la implementacion (ProveedorConexion.php) lee estos datos
//   de config.php. Pero podrian venir de variables de entorno, de un archivo
//   .env, de una API, etc. — sin cambiar nada en el repositorio.
//
// QUE ES UN DSN:
//   DSN = Data Source Name (nombre de la fuente de datos).
//   Es un string que le dice a PDO como conectarse a la BD.
//   Ejemplo: 'mysql:host=localhost;port=3306;dbname=bdfacturas_mariadb_local;charset=utf8mb4'
//
// CUANDO SE USA EN EL FLUJO:
//   index.php crea: $proveedorConexion = new ProveedorConexion($config)
//   ProveedorConexion implementa ESTA interface.
//   Luego el repositorio usa $proveedorConexion->obtenerDsn() para conectarse.
// ============================================================================

namespace ApiGenericaPhp\Servicios\Abstracciones;

interface IProveedorConexion
{
    // Devuelve el nombre del motor de BD activo: "MariaDB", "MySQL", etc.
    // Se usa en index.php para decidir que repositorio crear.
    public function getProveedorActual(): string;

    // Devuelve el DSN (string de conexion) para PDO.
    // Ejemplo: 'mysql:host=localhost;port=3306;dbname=bdfacturas_mariadb_local;charset=utf8mb4'
    public function obtenerDsn(): string;

    // Devuelve el usuario de la BD. Ejemplo: 'root'
    public function obtenerUsuario(): string;

    // Devuelve la contrasena de la BD. Ejemplo: '' (vacia en XAMPP)
    public function obtenerContrasena(): string;
}
