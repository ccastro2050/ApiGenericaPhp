<?php
// ============================================================================
// autoload.php — Cargador automatico de clases (Autoloader PSR-4)
// Ubicacion: vendor/autoload.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   En PHP, cada clase vive en su propio archivo .php.
//   Sin autoloader, habria que hacer "require" de cada archivo manualmente:
//
//     require 'src/Conexion/ProveedorConexion.php';
//     require 'src/Servicios/ServicioCrud.php';
//     require 'src/Controllers/EntidadesController.php';
//     // ... y asi con CADA clase que usemos
//
//   Con autoloader, solo hacemos UN require (este archivo) y PHP
//   automaticamente carga cada clase cuando la necesita:
//
//     require 'vendor/autoload.php';  // Una sola vez
//     $x = new ProveedorConexion();   // PHP carga el archivo automaticamente
//
// CUANDO SE EJECUTA (en que momento del flujo):
//   1. Llega una peticion: GET http://localhost:8000/api/producto
//   2. PHP ejecuta public/index.php
//   3. index.php hace:  require 'vendor/autoload.php';
//      ^^^^ ACA se ejecuta este archivo (registra el autoloader)
//   4. A partir de ahi, cada vez que index.php use "new AlgunaClase()",
//      PHP llama al autoloader para buscar y cargar el archivo .php
//
// COMO CONVIERTE NOMBRE DE CLASE A RUTA DE ARCHIVO:
//   Namespace de la clase:  ApiGenericaPhp \ Conexion \ ProveedorConexion
//   Se convierte a ruta:    src            / Conexion / ProveedorConexion.php
//
//   Otro ejemplo:
//   Namespace:  ApiGenericaPhp \ Controllers \ EntidadesController
//   Ruta:       src            / Controllers / EntidadesController.php
//
// NOTA: Si tienes Composer instalado, ejecuta: composer dump-autoload
//   y Composer generara su propio autoloader (mas optimizado).
//   Este autoloader manual funciona exactamente igual.
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C# / .NET:  No necesita, el compilador resuelve los namespaces automaticamente
//   - Java:       No necesita, el classpath resuelve las clases
//   - Python:     import (busca modulos en sys.path)
//   - Node.js:    require() o import (busca en node_modules o rutas relativas)
// ============================================================================

// spl_autoload_register() le dice a PHP:
// "Cada vez que alguien use una clase que no esta cargada todavia,
//  ejecuta esta funcion para buscar y cargar el archivo .php correspondiente."
//
// Recibe una funcion anonima (sin nombre) que PHP va a llamar automaticamente.
// El parametro $clase es el nombre completo de la clase que se esta intentando usar.
// Ejemplo: si haces "new ServicioCrud()", PHP llama a esta funcion con
//          $clase = "ApiGenericaPhp\Servicios\ServicioCrud"
spl_autoload_register(function (string $clase) {

    // El prefijo de nuestro namespace (todas nuestras clases empiezan con esto)
    $prefijo = 'ApiGenericaPhp\\';

    // La carpeta donde estan nuestros archivos .php
    // __DIR__ = la carpeta donde esta ESTE archivo (vendor/)
    // '/../src/' = subir un nivel (..) y entrar a src/
    $dirBase = __DIR__ . '/../src/';

    // Verificar si la clase pertenece a nuestro proyecto.
    // strlen() = contar caracteres del prefijo
    // strncmp() = comparar los primeros N caracteres de dos strings
    // Si la clase NO empieza con "ApiGenericaPhp\", no es nuestra — ignorar.
    $longitudPrefijo = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitudPrefijo) !== 0) {
        return; // No es nuestra clase, dejar que otro autoloader la maneje
    }

    // Quitar el prefijo "ApiGenericaPhp\" para obtener la parte relativa.
    // substr($clase, $longitudPrefijo) = tomar todo DESPUES del prefijo
    //
    // Ejemplo:
    //   $clase = "ApiGenericaPhp\Conexion\ProveedorConexion"
    //   $claseRelativa = "Conexion\ProveedorConexion"  (sin el prefijo)
    $claseRelativa = substr($clase, $longitudPrefijo);

    // Convertir las barras invertidas del namespace (\) a barras de directorio (/)
    // y agregar .php al final.
    //
    // Ejemplo:
    //   $claseRelativa = "Conexion\ProveedorConexion"
    //   str_replace convierte \ a /
    //   $archivo = "src/Conexion/ProveedorConexion.php"
    $archivo = $dirBase . str_replace('\\', '/', $claseRelativa) . '.php';

    // Si el archivo existe en esa ruta, cargarlo con require.
    // Despues de esto, la clase esta disponible para usar.
    if (file_exists($archivo)) {
        require $archivo;
    }
});
