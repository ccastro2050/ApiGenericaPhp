<?php
// ============================================================================
// config.php — Configuracion centralizada de la API
// Ubicacion: config/config.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Es el unico lugar donde se configuran los datos de conexion a la base
//   de datos, las politicas de seguridad y el comportamiento de la API.
//   Todos los demas archivos leen de aca. Si necesitas cambiar la base de
//   datos, el usuario, la contrasena o el puerto, se cambia SOLO ACA.
//
// COMO FUNCIONA:
//   Este archivo usa "return" para devolver un array asociativo.
//   Cuando otro archivo hace:  $config = require 'config.php';
//   la variable $config queda con todo lo que esta abajo.
//
// CUANDO SE EJECUTA (en que momento del flujo):
//   1. Un usuario hace una peticion: GET http://localhost:8000/api/producto
//   2. El servidor PHP recibe la peticion y ejecuta public/index.php
//   3. index.php hace:  $config = require '../config/config.php';
//      ^^^^ ACA se ejecuta este archivo (una sola vez por peticion)
//   4. index.php usa $config para crear la conexion a la BD, aplicar CORS, etc.
//
//   Es decir: cada vez que alguien llama a la API, lo primero que pasa
//   es que index.php lee este archivo para saber como conectarse a la BD.
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C# / .NET:   appsettings.json
//   - Java:        application.properties
//   - Python:      .env o settings.py
//   - Node.js:     .env o config.json
// ============================================================================

// ¿Que es un array asociativo?
//   Es un array donde cada elemento tiene un NOMBRE (clave) en vez de un numero.
//   Es como un diccionario: buscas por nombre, no por posicion.
//
//   Array normal (por posicion):
//     $frutas = ['manzana', 'pera', 'uva'];
//     echo $frutas[0];  // 'manzana' (se accede por numero)
//
//   Array asociativo (por nombre):
//     $persona = ['nombre' => 'Juan', 'edad' => 25, 'email' => 'juan@correo.com'];
//     echo $persona['nombre'];  // 'Juan' (se accede por nombre)
//     echo $persona['edad'];    // 25
//
//   El "=>" significa "esta clave TIENE este valor".
//   Equivalente en otros lenguajes:
//     - JavaScript: { nombre: 'Juan', edad: 25 }         (objeto)
//     - Python:     { 'nombre': 'Juan', 'edad': 25 }     (diccionario)
//     - C#:         Dictionary<string, object>

// "return" devuelve el array al archivo que hizo "require" de este archivo.
// Es como si este archivo fuera una funcion que retorna un diccionario.
return [

    // ------------------------------------------------------------------------
    // DatabaseProvider — Que motor de base de datos usar
    // ------------------------------------------------------------------------
    // Este valor le dice a la API cual base de datos usar.
    // La API busca este nombre en 'ConnectionStrings' (mas abajo) para
    // saber host, puerto, usuario, contrasena, etc.
    //
    // Valores soportados: "MariaDB", "MySQL"
    // ESCALABILIDAD: Agregar "PostgreSQL", "SqlServer" cuando se implementen
    'DatabaseProvider' => 'MariaDB',

    // ------------------------------------------------------------------------
    // ConnectionStrings — Datos de conexion para cada motor de BD
    // ------------------------------------------------------------------------
    // Cada entrada es un array con los datos necesarios para conectarse.
    // Solo se usa la entrada que coincida con 'DatabaseProvider' de arriba.
    //
    // Ejemplo: si DatabaseProvider = 'MariaDB', solo se lee la entrada 'MariaDB'.
    //          La entrada 'MySQL' existe pero se ignora.
    'ConnectionStrings' => [

        // Configuracion para MariaDB (la que viene con XAMPP)
        'MariaDB' => [
            'host'     => 'localhost',              // Direccion del servidor de BD (localhost = esta PC)
            'port'     => 3306,                     // Puerto donde escucha MariaDB (3306 es el default)
            'database' => 'bdfacturas_mariadb_local', // Nombre de la base de datos a usar
            'username' => 'root',                   // Usuario de la BD (root = administrador)
            'password' => '',                       // Contrasena (vacia en XAMPP por defecto)
            'charset'  => 'utf8mb4',                // Codificacion (utf8mb4 soporta acentos y emojis)
        ],

        // Configuracion para MySQL (misma estructura, diferente nombre de BD)
        'MySQL' => [
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'bdfacturas_mysql_local',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],

        // ESCALABILIDAD: Descomentar cuando se implemente RepositorioLecturaPostgreSQL.php
        // 'PostgreSQL' => [
        //     'host'     => 'localhost',
        //     'port'     => 5432,                  // PostgreSQL usa el puerto 5432, no 3306
        //     'database' => 'bdfacturas_postgres_local',
        //     'username' => 'postgres',            // Usuario default de PostgreSQL
        //     'password' => 'postgres',
        //     'charset'  => 'utf8',
        // ],
    ],

    // ------------------------------------------------------------------------
    // TablasProhibidas — Lista negra de tablas que la API NO puede acceder
    // ------------------------------------------------------------------------
    // Si esta vacio (como ahora): TODAS las tablas de la BD estan permitidas.
    // Si agregas nombres: esas tablas se bloquean y la API devuelve error 403.
    // Util para proteger tablas sensibles (auditoria, configuracion interna, etc.)
    // La comparacion es case-insensitive (no importa mayusculas/minusculas).
    'TablasProhibidas' => [
        // Ejemplo: 'usuarios_admin', 'sys_config', 'auditoria'
    ],

    // ------------------------------------------------------------------------
    // Cors — Configuracion de Cross-Origin Resource Sharing
    // ------------------------------------------------------------------------
    // CORS controla QUIEN puede llamar a esta API desde un navegador.
    //
    // Problema que resuelve:
    //   Si el frontend esta en localhost:80 y la API en localhost:8000,
    //   el navegador bloquea las llamadas porque son "origenes diferentes".
    //   Estos headers le dicen al navegador: "esta bien, dejalo pasar".
    //
    // AllowedOrigins: '*' = cualquiera puede llamar (desarrollo).
    //                 En produccion seria: 'https://midominio.com'
    // AllowedMethods: que verbos HTTP acepta (GET, POST, PUT, DELETE).
    // AllowedHeaders: que headers puede enviar el frontend.
    'Cors' => [
        'AllowedOrigins' => '*',
        'AllowedMethods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'AllowedHeaders' => 'Content-Type, Authorization, X-Requested-With',
    ],

    // Puerto del servidor de desarrollo PHP
    // Se usa como referencia. El puerto real se define al ejecutar:
    // php -S localhost:8000 -t public  (el 8000 de aca debe coincidir)
    'ServerPort' => 8000,
]; // Fin del array de configuracion
