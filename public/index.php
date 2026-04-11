<?php
// ============================================================================
// index.php — Punto de entrada de la API (el archivo mas importante)
// Ubicacion: public/index.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   TODAS las peticiones HTTP que llegan a la API pasan por este archivo.
//   No importa si piden /api/producto, /api/cliente, o /api/lo-que-sea,
//   SIEMPRE se ejecuta index.php primero.
//
//   Este archivo hace 5 cosas, en este orden:
//     1. Carga el autoloader (para que PHP encuentre las clases)
//     2. Lee la configuracion (config.php: datos de la BD, CORS, etc.)
//     3. Crea los objetos necesarios (conexion, repositorio, servicio, controlador)
//     4. Configura CORS (para que el frontend pueda llamar a la API)
//     5. Lee la URL y decide que metodo del controlador ejecutar
//
// CUANDO SE EJECUTA (en que momento del flujo):
//   1. Un usuario o el frontend hace: GET http://localhost:8000/api/producto
//   2. El servidor PHP (el que prendimos con php -S localhost:8000 -t public)
//      recibe la peticion
//   3. Como usamos "-t public", PHP busca index.php dentro de public/
//      ^^^^ ACA se ejecuta este archivo
//   4. index.php hace todo el trabajo: lee config, crea objetos, parsea la URL,
//      llama al controlador, y devuelve la respuesta JSON
//
// POR QUE TODAS LAS PETICIONES PASAN POR ACA:
//   Porque al ejecutar "php -S localhost:8000 -t public", PHP usa index.php
//   como archivo por defecto. Cualquier URL que no sea un archivo fisico
//   (como docs.html) se redirige automaticamente a index.php.
//   Esto se llama "Front Controller Pattern".
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C# / .NET:   Program.cs (donde se configura DI, middleware, rutas)
//   - Java:        Application.java (@SpringBootApplication)
//   - Python:      main.py o app.py (FastAPI/Flask)
//   - Node.js:     index.js o app.js (Express)
// ============================================================================

// ============================================================================
// PASO 1: CARGAR EL AUTOLOADER
// ============================================================================
// require_once carga y ejecuta un archivo PHP.
// Aca cargamos el autoloader que creamos en vendor/autoload.php.
// A partir de esta linea, PHP puede encontrar automaticamente cualquier
// clase de nuestro proyecto sin necesidad de hacer require de cada archivo.
//
// __DIR__ = la carpeta donde esta ESTE archivo (public/)
// '/../vendor/autoload.php' = subir un nivel (..) y entrar a vendor/
// Ruta final: C:\xampp\htdocs\ApiGenericaPhp\vendor\autoload.php
require_once __DIR__ . '/../vendor/autoload.php';

// "use" crea un ALIAS CORTO para una clase con namespace largo.
// NO carga el archivo — eso lo hace el autoloader cuando se use la clase.
//
// Sin "use":   $p = new ApiGenericaPhp\Conexion\ProveedorConexion($config);
// Con "use":   $p = new ProveedorConexion($config);
//
// Es solo un atajo para no escribir el nombre completo cada vez.
// Equivalente a "using" en C#, "import" en Python/Java.
use ApiGenericaPhp\Conexion\ProveedorConexion;                    // Lee config y da datos de conexion
use ApiGenericaPhp\Repositorios\RepositorioLecturaMysqlMariaDB;   // Ejecuta SQL contra la BD
use ApiGenericaPhp\Politicas\PoliticaTablasProhibidasDesdeConfig;  // Decide si una tabla esta permitida
use ApiGenericaPhp\Servicios\ServicioCrud;                         // Logica de negocio (validar, normalizar)
use ApiGenericaPhp\Controllers\EntidadesController;                // Recibe peticiones HTTP, devuelve JSON

// ============================================================================
// PASO 2: CARGAR CONFIGURACION
// ============================================================================
// Aca se ejecuta config/config.php, que devuelve un array asociativo con toda
// la configuracion (datos de BD, CORS, tablas prohibidas, etc.)
// Despues de esta linea, $config tiene toda la configuracion disponible.
//
// Ejemplo de lo que queda en $config:
//   $config['DatabaseProvider']  -> 'MariaDB'
//   $config['ConnectionStrings']['MariaDB']['host'] -> 'localhost'
//   $config['TablasProhibidas']  -> []
//   $config['Cors']['AllowedOrigins'] -> '*'
$config = require __DIR__ . '/../config/config.php';

// ============================================================================
// PASO 3: CREAR LOS OBJETOS (Inyeccion de Dependencias manual)
// ============================================================================
// Aca se crean todos los objetos que la API necesita para funcionar.
// Se crean en orden porque cada uno DEPENDE del anterior:
//
//   $config (array)
//     └─> $proveedorConexion (lee config, sabe host/puerto/usuario/password)
//           └─> $repositorio (usa la conexion para ejecutar SQL contra la BD)
//                 └─> $servicioCrud (usa el repositorio, aplica reglas de negocio)
//                       └─> $controlador (usa el servicio, recibe HTTP, devuelve JSON)
//
// Es como una cadena: cada pieza necesita a la anterior para funcionar.
// Si cambias la BD (ej: de MariaDB a PostgreSQL), solo cambias el $repositorio.
// El $servicioCrud y el $controlador siguen funcionando igual.

// Crear el proveedor de conexion: lee la config y sabe como conectarse a la BD.
// Le pasamos $config para que sepa host, puerto, usuario, contrasena, etc.
$proveedorConexion = new ProveedorConexion($config);

// Seleccionar que repositorio usar segun el proveedor configurado en config.php
// strtolower() convierte a minusculas para que 'MariaDB', 'mariadb', 'MARIADB' funcionen igual
$proveedorBD = strtolower($proveedorConexion->getProveedorActual());

// switch es como un if/elseif pero mas limpio cuando hay muchas opciones.
// Segun el proveedor, se crea el repositorio correcto.
switch ($proveedorBD) {
    case 'mariadb':  // Si config dice 'MariaDB'
    case 'mysql':    // Si config dice 'MySQL' (usan el mismo repositorio, mismo SQL)
        $repositorio = new RepositorioLecturaMysqlMariaDB($proveedorConexion);
        break;       // "break" = salir del switch, no seguir evaluando
    // ESCALABILIDAD: Descomentar cuando se implemente
    // case 'postgresql':
    // case 'postgres':
    //     $repositorio = new RepositorioLecturaPostgreSQL($proveedorConexion);
    //     break;
    default:
        // Si el proveedor no es ninguno de los soportados, devolver error 500
        http_response_code(500);   // 500 = Internal Server Error
        echo json_encode([
            'estado'  => 500,
            'mensaje' => "Proveedor de base de datos '{$proveedorBD}' no soportado.",
            'sugerencia' => "Proveedores soportados: MariaDB, MySQL. Verificar 'DatabaseProvider' en config/config.php",
        ]);
        exit;  // Detener la ejecucion (no seguir procesando la peticion)
}

// Crear la politica de tablas prohibidas: lee la lista de config.php
$politicaTablas = new PoliticaTablasProhibidasDesdeConfig($config);

// Crear el servicio CRUD: recibe el repositorio (para acceder a la BD)
// y la politica (para saber que tablas estan permitidas)
$servicioCrud   = new ServicioCrud($repositorio, $politicaTablas);

// Crear el controlador: recibe el servicio para delegar la logica de negocio
// Este es el objeto que va a responder las peticiones HTTP
$controlador    = new EntidadesController($servicioCrud);

// ============================================================================
// PASO 4: CONFIGURAR CORS
// ============================================================================
// CORS = Cross-Origin Resource Sharing (compartir recursos entre origenes).
//
// EL PROBLEMA QUE RESUELVE:
//   El frontend esta en http://localhost:80 (Apache)
//   La API esta en http://localhost:8000 (PHP built-in server)
//   Como son puertos DIFERENTES, el navegador los considera "origenes diferentes"
//   y BLOQUEA las llamadas del frontend a la API (por seguridad).
//
//   Estos headers le dicen al navegador:
//   "Tranquilo, esta API acepta llamadas de otros origenes. Dejalo pasar."
//
// header() envia un header HTTP en la respuesta. Los headers son metadatos
// que van ANTES del cuerpo (JSON) de la respuesta.

$cors = $config['Cors'] ?? [];  // Leer config CORS, o array vacio si no existe ( ?? = "si es null, usar esto otro")

// Allow-Origin: QUIEN puede llamar a esta API. '*' = cualquiera (para desarrollo)
header('Access-Control-Allow-Origin: '  . ($cors['AllowedOrigins'] ?? '*'));

// Allow-Methods: QUE verbos HTTP acepta (GET para leer, POST para crear, etc.)
header('Access-Control-Allow-Methods: ' . ($cors['AllowedMethods'] ?? 'GET, POST, PUT, DELETE, OPTIONS'));

// Allow-Headers: QUE headers puede enviar el frontend (Content-Type es necesario para enviar JSON)
header('Access-Control-Allow-Headers: ' . ($cors['AllowedHeaders'] ?? 'Content-Type, Authorization'));

// Manejar preflight OPTIONS:
// Antes de hacer un POST/PUT/DELETE, el navegador envia automaticamente
// una peticion OPTIONS preguntando "¿puedo hacer esto?". Es como tocar la puerta
// antes de entrar. Si respondemos 204 (No Content), el navegador procede
// con la peticion real.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);  // 204 = "OK, pero no tengo contenido que devolver"
    exit;                     // Terminar aca, no seguir procesando
}

// ============================================================================
// PASO 5: ROUTER — Leer la URL y decidir que hacer
// ============================================================================
// El router es el que mira la URL que llego y decide que metodo del
// controlador ejecutar. Es como un recepcionista que lee tu pedido
// y te envia a la ventanilla correcta.
//
// Tabla de rutas (que URL llama a que metodo):
//   GET    /                                    -> inicio()               (bienvenida)
//   GET    /api/info                            -> obtenerInformacion()   (info de la API)
//   GET    /api/{tabla}                         -> listarAsync()          (listar registros)
//   GET    /api/{tabla}/{clave}/{valor}         -> obtenerPorClaveAsync() (buscar uno)
//   POST   /api/{tabla}                         -> crearAsync()           (crear registro)
//   POST   /api/{tabla}/verificar-contrasena    -> verificarContrasenaAsync()
//   PUT    /api/{tabla}/{clave}/{valor}         -> actualizarAsync()      (actualizar registro)
//   DELETE /api/{tabla}/{clave}/{valor}         -> eliminarAsync()        (eliminar registro)

// $_SERVER es un array SUPERGLOBAL de PHP que existe siempre.
// PHP lo llena automaticamente con informacion de la peticion HTTP.
// No lo creas vos, ya viene listo para usar.
$metodo = $_SERVER['REQUEST_METHOD']; // El verbo HTTP: 'GET', 'POST', 'PUT' o 'DELETE'
$uri    = $_SERVER['REQUEST_URI'];    // La URL completa que pidieron. Ej: '/api/producto?limite=10'

// parse_url() separa la URL en sus partes:
//   '/api/producto?limite=10'  ->  path = '/api/producto'
//                                  query = 'limite=10'
$urlParts = parse_url($uri);
$path     = $urlParts['path'] ?? '/';  // La parte antes del '?'

// rtrim() quita caracteres del final de un string.
// Aca quita la barra final: '/api/producto/' -> '/api/producto'
// Asi '/api/producto' y '/api/producto/' se tratan igual.
$path = rtrim($path, '/');
if (empty($path)) {
    $path = '/';  // Si quedo vacio, es la raiz
}

// Parsear los query params (lo que va despues del '?' en la URL).
// parse_str() convierte 'limite=10&esquema=public' en un array:
//   $queryParams = ['limite' => '10', 'esquema' => 'public']
$queryParams = [];
if (isset($urlParts['query'])) {
    parse_str($urlParts['query'], $queryParams);
}

// Separar el path en segmentos (las partes entre cada '/').
// explode('/', $path) corta el string por cada '/'.
// array_filter() quita los elementos vacios (por las barras al inicio/final).
// array_values() reindeza el array desde 0.
//
// Ejemplo:
//   '/api/producto/codigo/PR001'
//   explode     -> ['', 'api', 'producto', 'codigo', 'PR001']
//   filter      -> ['api', 'producto', 'codigo', 'PR001']  (sin el '' vacio)
//   values      -> [0 => 'api', 1 => 'producto', 2 => 'codigo', 3 => 'PR001']
$segmentos = array_values(array_filter(explode('/', $path)));

// urldecode() decodifica caracteres especiales en la URL.
// Ejemplo: %40 -> @, %2F -> /, %20 -> espacio
// array_map() aplica urldecode() a CADA elemento del array.
$segmentos = array_map('urldecode', $segmentos);

// Leer el body JSON (solo para POST y PUT, que envian datos).
// GET y DELETE no envian body.
$bodyJson = null;
if (in_array($metodo, ['POST', 'PUT'])) {        // in_array() = ¿el metodo esta en esta lista?
    $rawBody = file_get_contents('php://input');   // php://input = el body crudo que envio el cliente
    if (!empty($rawBody)) {                        // Si el body no esta vacio...
        $bodyJson = json_decode($rawBody, true);   // Convertir JSON string a array PHP
        // json_decode($string, true) -> true significa "convertir a array asociativo"
        //   '{"nombre":"Juan","edad":25}'  ->  ['nombre' => 'Juan', 'edad' => 25]

        // Si el JSON esta mal formado (ej: falta una coma, llave sin cerrar)
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);               // 400 = Bad Request (peticion mal hecha)
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([                     // json_encode() = convertir array PHP a JSON string
                'estado'  => 400,
                'mensaje' => 'El body de la peticion no es JSON valido.',
                'detalle' => json_last_error_msg(), // Mensaje del error de parseo JSON
            ], JSON_UNESCAPED_UNICODE);             // JSON_UNESCAPED_UNICODE = no escapar acentos (ñ, é)
            exit;
        }
    }
}

// Extraer parametros comunes del query string.
// Estos parametros son opcionales — si no vienen en la URL, quedan en null.
//   ?esquema=public        -> $esquema = 'public'
//   ?limite=50             -> $limite = 50
//   ?camposEncriptar=contrasena -> $camposEncriptar = 'contrasena'
$esquema         = $queryParams['esquema'] ?? null;                              // Esquema de BD (raramente usado)
$limite          = isset($queryParams['limite']) ? (int)$queryParams['limite'] : null; // (int) convierte string '50' a numero 50
$camposEncriptar = $queryParams['camposEncriptar'] ?? null;                       // Campos a hashear con BCrypt

// ============================================================================
// DESPACHO DE RUTAS — Decidir que hacer segun la URL
// ============================================================================
// Ahora que ya tenemos el $path, $metodo, $segmentos y $bodyJson,
// podemos decidir que metodo del controlador ejecutar.

// --- RUTA: GET / (la raiz) ---
// Si alguien abre http://localhost:8000/ sin nada mas, mostrar bienvenida.
if ($path === '/' && $metodo === 'GET') {
    $controlador->inicio();  // Muestra un mensaje de bienvenida con info de la API
    exit;                    // Terminar, no seguir evaluando rutas
}

// --- RUTA: GET /docs ---
// Swagger UI: documentacion interactiva para probar la API desde el navegador.
// readfile() lee un archivo y lo envia directamente al navegador.
if ($path === '/docs' && $metodo === 'GET') {
    readfile(__DIR__ . '/docs.html');  // Envia el HTML de Swagger UI
    exit;
}

// --- Verificar que la ruta empieza con /api ---
// Todas las rutas de datos empiezan con /api. Si no empieza con /api,
// es una ruta que no existe.
// count($segmentos) = cuantos segmentos tiene la URL
//   '/api/producto' tiene 2 segmentos: ['api', 'producto']
//   '/hola' tiene 1 segmento: ['hola'] -> NO empieza con /api
if (count($segmentos) < 2 || $segmentos[0] !== 'api') {
    http_response_code(404);  // 404 = Not Found (no existe)
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'estado'    => 404,
        'mensaje'   => 'Ruta no encontrada.',
        'sugerencia' => 'Use /api/{tabla} para consultar tablas. Visite / para mas informacion.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Extraer el nombre de la tabla del segmento 1.
//   URL: /api/producto       -> segmentos = ['api', 'producto']
//   segmentos[0] = 'api'     (siempre es 'api')
//   segmentos[1] = 'producto' (el nombre de la tabla)
$tabla = $segmentos[1];

// --- RUTA: GET /api/info ---
// Devuelve informacion del controlador (nombre, version, etc.)
if ($tabla === 'info' && $metodo === 'GET') {
    $controlador->obtenerInformacion();
    exit;
}

// Contar cuantos segmentos tiene la URL para saber que ruta es:
//   2 segmentos: /api/producto               -> listar todos
//   3 segmentos: /api/usuario/verificar-...  -> verificar contrasena
//   4 segmentos: /api/producto/codigo/PR001  -> buscar/actualizar/eliminar uno
$numSegmentos = count($segmentos);

// Despachar segun el METODO HTTP (GET, POST, PUT, DELETE)
switch ($metodo) {

    case 'GET':
        if ($numSegmentos === 2) {
            // GET /api/producto  ->  Listar todos los registros de esa tabla
            $controlador->listarAsync($tabla, $esquema, $limite);

        } elseif ($numSegmentos === 4) {
            // GET /api/producto/codigo/PR001  ->  Buscar por clave
            $nombreClave = $segmentos[2];  // 'codigo' (nombre de la columna)
            $valor       = $segmentos[3];  // 'PR001'  (valor a buscar)
            $controlador->obtenerPorClaveAsync($tabla, $nombreClave, $valor, $esquema);

        } else {
            // La URL no tiene ni 2 ni 4 segmentos -> formato invalido
            http_response_code(400);  // 400 = Bad Request
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estado' => 400, 'mensaje' => 'Formato de ruta GET invalido.'], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'POST':
        if ($numSegmentos === 3 && $segmentos[2] === 'verificar-contrasena') {
            // POST /api/usuario/verificar-contrasena  ->  Verificar login
            $controlador->verificarContrasenaAsync($tabla, $bodyJson ?? [], $esquema);
            // $bodyJson ?? [] = si $bodyJson es null, usar array vacio []

        } elseif ($numSegmentos === 2) {
            // POST /api/producto  ->  Crear un registro nuevo
            // $camposEncriptar se usa para hashear contrasenas con BCrypt
            $controlador->crearAsync($tabla, $bodyJson ?? [], $esquema, $camposEncriptar);

        } else {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estado' => 400, 'mensaje' => 'Formato de ruta POST invalido.'], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'PUT':
        if ($numSegmentos === 4) {
            // PUT /api/producto/codigo/PR001  ->  Actualizar un registro
            $nombreClave = $segmentos[2];  // 'codigo'
            $valorClave  = $segmentos[3];  // 'PR001'
            $controlador->actualizarAsync($tabla, $nombreClave, $valorClave, $bodyJson ?? [], $esquema, $camposEncriptar);

        } else {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estado' => 400, 'mensaje' => 'Formato de ruta PUT invalido. Use: PUT /api/{tabla}/{clave}/{valor}'], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'DELETE':
        if ($numSegmentos === 4) {
            // DELETE /api/producto/codigo/PR001  ->  Eliminar un registro
            $nombreClave = $segmentos[2];  // 'codigo'
            $valorClave  = $segmentos[3];  // 'PR001'
            $controlador->eliminarAsync($tabla, $nombreClave, $valorClave, $esquema);

        } else {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estado' => 400, 'mensaje' => 'Formato de ruta DELETE invalido. Use: DELETE /api/{tabla}/{clave}/{valor}'], JSON_UNESCAPED_UNICODE);
        }
        break;

    default:
        // Si el metodo no es GET, POST, PUT ni DELETE (ej: PATCH, HEAD)
        http_response_code(405);  // 405 = Method Not Allowed
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'estado'  => 405,
            'mensaje' => "Metodo HTTP '{$metodo}' no permitido.",
            'metodosPermitidos' => ['GET', 'POST', 'PUT', 'DELETE'],
        ], JSON_UNESCAPED_UNICODE);
        break;
}
