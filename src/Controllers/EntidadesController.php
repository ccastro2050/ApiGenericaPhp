<?php
// ============================================================================
// EntidadesController.php — El controlador que recibe las peticiones HTTP
// Ubicacion: src/Controllers/EntidadesController.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Es la "cara publica" de la API. Es la clase que recibe las peticiones
//   HTTP del navegador/frontend y devuelve respuestas JSON.
//
//   Es GENERICO: un solo controlador maneja TODAS las tablas.
//   No necesitas crear un controlador por cada tabla (ProductoController,
//   ClienteController, etc.). Solo este controlador maneja todo via /api/{tabla}.
//
//   Su trabajo es:
//     1. Recibir la peticion HTTP (tabla, parametros, body JSON)
//     2. Delegar la logica al servicio (ServicioCrud)
//     3. Convertir el resultado a JSON
//     4. Enviar la respuesta HTTP con el codigo correcto (200, 400, 404, 500)
//     5. Atrapar errores y devolver mensajes descriptivos
//
//   El controlador NO sabe de SQL ni de reglas de negocio.
//   Solo sabe de HTTP: recibir peticiones y devolver JSON.
//
// CUANDO SE USA EN EL FLUJO:
//   1. Llega: GET /api/producto?limite=10
//   2. index.php parsea la URL y llama a: $controlador->listarAsync('producto', null, 10)
//      ^^^^ ACA entra esta clase
//   3. El controlador llama al servicio: $this->servicioCrud->listarAsync(...)
//   4. El servicio valida y llama al repositorio
//   5. El repositorio ejecuta SQL y devuelve datos
//   6. Los datos suben de vuelta: repositorio -> servicio -> controlador
//   7. El controlador arma el JSON y lo envia con http_response_code(200)
//
// CODIGOS HTTP QUE USA:
//   200 = OK (todo bien, aca van los datos)
//   204 = OK pero sin contenido (la tabla esta vacia)
//   400 = Bad Request (los parametros que mandaste estan mal)
//   401 = Unauthorized (contrasena incorrecta)
//   403 = Forbidden (la tabla esta prohibida)
//   404 = Not Found (no se encontro el registro o la tabla)
//   409 = Conflict (no se puede borrar porque tiene datos relacionados)
//   500 = Internal Server Error (algo fallo internamente)
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C#:     [ApiController] con [HttpGet], [HttpPost], etc.
//   - Java:   @RestController con @GetMapping, @PostMapping, etc.
//   - Python: FastAPI con @app.get(), @app.post(), etc.
//   - Node.js: Express con app.get(), app.post(), etc.
// ============================================================================

namespace ApiGenericaPhp\Controllers;

use ApiGenericaPhp\Servicios\Abstracciones\IServicioCrud;

class EntidadesController
{
    // El servicio de logica de negocio. El controlador le delega TODO el trabajo real.
    private IServicioCrud $servicioCrud;

    // Constructor: recibe el servicio y lo guarda.
    // El controlador depende de la INTERFACE IServicioCrud, no de la clase concreta.
    // Esto permite cambiar la implementacion del servicio sin tocar el controlador.
    public function __construct(IServicioCrud $servicioCrud)
    {
        $this->servicioCrud = $servicioCrud;
    }

    // =======================================================================
    // listarAsync — GET /api/{tabla} — Listar registros
    // =======================================================================
    // Ejemplo: GET /api/producto?limite=10
    //
    // Este metodo es llamado por index.php cuando llega un GET /api/{tabla}.
    // El parametro $tabla viene de la URL, $esquema y $limite del query string.
    //
    // ": void" al final = este metodo no retorna nada.
    // En vez de retornar, IMPRIME directamente el JSON con echo (via $this->responder).
    // Esto es porque en PHP la respuesta HTTP se envia con echo, no con return.
    public function listarAsync(string $tabla, ?string $esquema, ?int $limite): void
    {
        // try/catch: intentar ejecutar el codigo, y si falla, atrapar el error.
        // Esto evita que la API devuelva un error feo de PHP al usuario.
        // En vez de eso, devuelve un JSON descriptivo con el error.
        try {
            // error_log() = escribir un mensaje en el log del servidor.
            // Los logs aparecen en la terminal donde corre el servidor PHP.
            // Son utiles para debugging: saber que peticiones llegan y que pasa.
            error_log(sprintf(
                "INICIO consulta - Tabla: %s, Esquema: %s, Limite: %s",
                $tabla,
                $esquema ?? 'por defecto',  // Si es null, mostrar 'por defecto'
                $limite ?? 'por defecto'
            ));

            // Delegar al servicio. El servicio valida, verifica permisos, y llama al repositorio.
            // $filas = un array de arrays asociativos con los datos de la tabla.
            // Ejemplo: [['codigo'=>'PR001','nombre'=>'Laptop',...], ['codigo'=>'PR002',...]]
            $filas = $this->servicioCrud->listarAsync($tabla, $esquema, $limite);

            error_log(sprintf("RESULTADO exitoso - Registros obtenidos: %d de tabla %s", count($filas), $tabla));

            // Si la tabla esta vacia (no tiene filas), devolver 204 No Content
            if (empty($filas)) {
                error_log("SIN DATOS - Tabla {$tabla} consultada exitosamente pero no contiene registros");
                $this->responder(204);  // 204 = "OK pero no hay contenido"
                return;                  // Terminar, no seguir
            }

            // Todo bien: devolver 200 OK con los datos y metadatos
            // $this->responder() es un metodo privado de esta misma clase (ver abajo)
            // que convierte el array a JSON y lo envia con el codigo HTTP indicado.
            $this->responder(200, [
                'tabla'   => $tabla,                     // Que tabla se consulto
                'esquema' => $esquema ?? 'por defecto',  // Que esquema se uso
                'limite'  => $limite,                     // Que limite se aplico
                'total'   => count($filas),               // Cuantos registros hay
                'datos'   => $filas,                      // Los datos reales
            ]);

        // Si el servicio lanzo InvalidArgumentException (parametros invalidos)
        } catch (\InvalidArgumentException $e) {
            error_log("ERROR DE VALIDACION - Tabla: {$tabla}, Error: {$e->getMessage()}");
            $this->responder(400, [  // 400 = Bad Request
                'estado'  => 400,
                'mensaje' => 'Parametros de entrada invalidos.',
                'detalle' => $e->getMessage(),  // El mensaje del error (ej: "tabla no puede estar vacia")
                'tabla'   => $tabla,
            ]);

        // Si el servicio lanzo RuntimeException (tabla prohibida o no encontrada)
        } catch (\RuntimeException $e) {
            // stripos() busca "Acceso denegado" en el mensaje del error.
            // Si lo encuentra, es un 403 (prohibido). Si no, es un 404 (no encontrado).
            if (stripos($e->getMessage(), 'Acceso denegado') !== false) {
                error_log("ACCESO DENEGADO - Tabla restringida: {$tabla}");
                $this->responder(403, [  // 403 = Forbidden (prohibido)
                    'estado'  => 403,
                    'mensaje' => 'Acceso denegado.',
                    'detalle' => $e->getMessage(),
                    'tabla'   => $tabla,
                ]);
            } else {
                error_log("ERROR DE OPERACION - Tabla: {$tabla}, Error: {$e->getMessage()}");
                $this->responder(404, [  // 404 = Not Found (la tabla no existe en la BD)
                    'estado'    => 404,
                    'mensaje'   => 'El recurso solicitado no fue encontrado.',
                    'detalle'   => $e->getMessage(),
                    'tabla'     => $tabla,
                    'sugerencia' => 'Verifique que la tabla y el esquema existan en la base de datos',
                ]);
            }

        // \Throwable = atrapa CUALQUIER error (el catch mas amplio posible)
        // Es la ultima red de seguridad: si algo inesperado falla, no se cae la API.
        } catch (\Throwable $e) {
            error_log("ERROR CRITICO - Falla inesperada en consulta - Tabla: {$tabla} - {$e->getMessage()}");
            $this->responderError500($e, $tabla, 'Error interno del servidor al consultar tabla.');
        }
    }

    // =======================================================================
    // obtenerPorClaveAsync — GET /api/{tabla}/{clave}/{valor} — Buscar uno
    // =======================================================================
    // Ejemplo: GET /api/producto/codigo/PR001
    //   -> Busca en la tabla 'producto' donde 'codigo' = 'PR001'
    //   -> Devuelve el producto (o 404 si no existe)
    //
    // Misma estructura que listarAsync: try -> delegar -> responder -> catch errores
    public function obtenerPorClaveAsync(string $tabla, string $nombreClave, string $valor, ?string $esquema): void
    {
        try {
            error_log(sprintf(
                "INICIO filtrado - Tabla: %s, Esquema: %s, Clave: %s, Valor: %s",
                $tabla, $esquema ?? 'por defecto', $nombreClave, $valor
            ));

            $filas = $this->servicioCrud->obtenerPorClaveAsync($tabla, $esquema, $nombreClave, $valor);

            error_log(sprintf("RESULTADO filtrado - %d registros para %s=%s en %s", count($filas), $nombreClave, $valor, $tabla));

            if (empty($filas)) {
                $this->responder(404, [
                    'estado'  => 404,
                    'mensaje' => 'No se encontraron registros',
                    'detalle' => "No se encontro ningun registro con {$nombreClave} = {$valor} en la tabla {$tabla}",
                    'tabla'   => $tabla,
                    'esquema' => $esquema ?? 'por defecto',
                    'filtro'  => "{$nombreClave} = {$valor}",
                ]);
                return;
            }

            $this->responder(200, [
                'tabla'   => $tabla,
                'esquema' => $esquema ?? 'por defecto',
                'filtro'  => "{$nombreClave} = {$valor}",
                'total'   => count($filas),
                'datos'   => $filas,
            ]);

        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Acceso denegado') !== false) {
                $this->responder(403, [
                    'estado' => 403, 'mensaje' => 'Acceso denegado.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            } else {
                $this->responder(404, [
                    'estado' => 404, 'mensaje' => 'Recurso no encontrado.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
            ]);
        } catch (\Throwable $e) {
            error_log("ERROR CRITICO - Filtrado - Tabla: {$tabla}, Clave: {$nombreClave}={$valor} - {$e->getMessage()}");
            $this->responderError500($e, $tabla, 'Error interno del servidor al filtrar registros.', "{$nombreClave} = {$valor}");
        }
    }

    // =======================================================================
    // crearAsync — POST /api/{tabla} — Crear un registro nuevo
    // =======================================================================
    // Ejemplo: POST /api/producto con body JSON:
    //   {"codigo":"PR099","nombre":"Test","stock":5,"valorunitario":1000}
    //
    // Con encriptacion: POST /api/usuario?camposEncriptar=contrasena
    //   {"email":"nuevo@correo.com","contrasena":"123456"}
    //   -> La contrasena se hashea con BCrypt antes de guardarla
    public function crearAsync(string $tabla, array $datosEntidad, ?string $esquema, ?string $camposEncriptar): void
    {
        try {
            error_log(sprintf(
                "INICIO creacion - Tabla: %s, Esquema: %s, Campos a encriptar: %s",
                $tabla, $esquema ?? 'por defecto', $camposEncriptar ?? 'ninguno'
            ));

            // VALIDACION TEMPRANA EN CONTROLADOR
            if (empty($datosEntidad)) {
                $this->responder(400, [
                    'estado'  => 400,
                    'mensaje' => 'Los datos de la entidad no pueden estar vacios.',
                    'tabla'   => $tabla,
                ]);
                return;
            }

            // DELEGACION AL SERVICIO
            $creado = $this->servicioCrud->crearAsync($tabla, $esquema, $datosEntidad, $camposEncriptar);

            if ($creado) {
                error_log("EXITO creacion - Registro creado en tabla {$tabla}");
                $this->responder(200, [
                    'estado'  => 200,
                    'mensaje' => 'Registro creado exitosamente.',
                    'tabla'   => $tabla,
                    'esquema' => $esquema ?? 'por defecto',
                ]);
            } else {
                $this->responder(500, [
                    'estado'  => 500,
                    'mensaje' => 'No se pudo crear el registro.',
                    'tabla'   => $tabla,
                ]);
            }

        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Acceso denegado') !== false) {
                $this->responder(403, [
                    'estado' => 403, 'mensaje' => 'Acceso denegado.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            } else {
                $this->responder(500, [
                    'estado' => 500, 'mensaje' => 'Error en la operacion.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Datos invalidos.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
            ]);
        } catch (\Throwable $e) {
            error_log("ERROR CRITICO - Creacion - Tabla: {$tabla} - {$e->getMessage()}");
            $this->responderError500($e, $tabla, 'Error interno del servidor al crear registro.');
        }
    }

    // =======================================================================
    // actualizarAsync — PUT /api/{tabla}/{clave}/{valor} — Actualizar registro
    // =======================================================================
    // Ejemplo: PUT /api/producto/codigo/PR001 con body JSON:
    //   {"stock":99,"valorunitario":85000}
    //   -> Actualiza el producto PR001: stock=99, valorunitario=85000
    //
    // Si $filasAfectadas > 0: se encontro y actualizo -> 200 OK
    // Si $filasAfectadas = 0: no se encontro ese registro -> 404 Not Found
    public function actualizarAsync(string $tabla, string $nombreClave, string $valorClave, array $datosEntidad, ?string $esquema, ?string $camposEncriptar): void
    {
        try {
            error_log(sprintf(
                "INICIO actualizacion - Tabla: %s, Clave: %s=%s, Esquema: %s, Campos a encriptar: %s",
                $tabla, $nombreClave, $valorClave, $esquema ?? 'por defecto', $camposEncriptar ?? 'ninguno'
            ));

            // VALIDACION TEMPRANA
            if (empty($datosEntidad)) {
                $this->responder(400, [
                    'estado'  => 400,
                    'mensaje' => 'Los datos de actualizacion no pueden estar vacios.',
                    'tabla'   => $tabla,
                    'filtro'  => "{$nombreClave} = {$valorClave}",
                ]);
                return;
            }

            // DELEGACION AL SERVICIO
            $filasAfectadas = $this->servicioCrud->actualizarAsync(
                $tabla, $esquema, $nombreClave, $valorClave, $datosEntidad, $camposEncriptar
            );

            if ($filasAfectadas > 0) {
                error_log("EXITO actualizacion - {$filasAfectadas} filas en {$tabla} WHERE {$nombreClave}={$valorClave}");
                $this->responder(200, [
                    'estado'             => 200,
                    'mensaje'            => 'Registro actualizado exitosamente.',
                    'tabla'              => $tabla,
                    'esquema'            => $esquema ?? 'por defecto',
                    'filtro'             => "{$nombreClave} = {$valorClave}",
                    'filasAfectadas'     => $filasAfectadas,
                    'camposEncriptados'  => $camposEncriptar ?? 'ninguno',
                ]);
            } else {
                $this->responder(404, [
                    'estado'  => 404,
                    'mensaje' => 'No se encontro el registro a actualizar.',
                    'detalle' => "No existe un registro con {$nombreClave} = {$valorClave} en la tabla {$tabla}",
                    'tabla'   => $tabla,
                    'filtro'  => "{$nombreClave} = {$valorClave}",
                ]);
            }

        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Acceso denegado') !== false) {
                $this->responder(403, [
                    'estado' => 403, 'mensaje' => 'Acceso denegado.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            } else {
                $this->responder(500, [
                    'estado' => 500, 'mensaje' => 'Error en la operacion de actualizacion.',
                    'detalle' => $e->getMessage(), 'tabla' => $tabla, 'filtro' => "{$nombreClave} = {$valorClave}",
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage(),
                'tabla' => $tabla, 'filtro' => "{$nombreClave} = {$valorClave}",
            ]);
        } catch (\Throwable $e) {
            error_log("ERROR CRITICO - Actualizacion - Tabla: {$tabla}, Clave: {$nombreClave}={$valorClave} - {$e->getMessage()}");
            $this->responderError500($e, $tabla, 'Error interno del servidor al actualizar registro.', "{$nombreClave} = {$valorClave}");
        }
    }

    // =======================================================================
    // eliminarAsync — DELETE /api/{tabla}/{clave}/{valor} — Eliminar registro
    // =======================================================================
    // Ejemplo: DELETE /api/producto/codigo/PR099
    //   -> Elimina el producto con codigo PR099
    //
    // Caso especial: si el registro tiene datos relacionados (ej: una persona
    // que es cliente), MySQL devuelve error 1451 (restriccion de clave foranea).
    // En ese caso devolvemos 409 Conflict en vez de 500.
    public function eliminarAsync(string $tabla, string $nombreClave, string $valorClave, ?string $esquema): void
    {
        try {
            error_log(sprintf(
                "INICIO eliminacion - Tabla: %s, Clave: %s=%s, Esquema: %s",
                $tabla, $nombreClave, $valorClave, $esquema ?? 'por defecto'
            ));

            $filasEliminadas = $this->servicioCrud->eliminarAsync($tabla, $esquema, $nombreClave, $valorClave);

            if ($filasEliminadas > 0) {
                error_log("EXITO eliminacion - {$filasEliminadas} filas de {$tabla} WHERE {$nombreClave}={$valorClave}");
                $this->responder(200, [
                    'estado'          => 200,
                    'mensaje'         => 'Registro eliminado exitosamente.',
                    'tabla'           => $tabla,
                    'esquema'         => $esquema ?? 'por defecto',
                    'filtro'          => "{$nombreClave} = {$valorClave}",
                    'filasEliminadas' => $filasEliminadas,
                ]);
            } else {
                $this->responder(404, [
                    'estado'  => 404,
                    'mensaje' => 'No se encontro el registro a eliminar.',
                    'detalle' => "No existe un registro con {$nombreClave} = {$valorClave} en la tabla {$tabla}",
                    'tabla'   => $tabla,
                    'filtro'  => "{$nombreClave} = {$valorClave}",
                ]);
            }

        } catch (\RuntimeException $e) {
            // Detectar error de clave foranea (MySQL errno 1451)
            $prev = $e->getPrevious();
            if ($prev instanceof \PDOException && (int)$prev->errorInfo[1] === 1451) {
                $this->responder(409, [
                    'estado'  => 409,
                    'mensaje' => 'No se puede eliminar el registro.',
                    'detalle' => 'El registro esta siendo referenciado por otros datos (restriccion de clave foranea).',
                    'tabla'   => $tabla,
                    'filtro'  => "{$nombreClave} = {$valorClave}",
                ]);
                return;
            }

            if (stripos($e->getMessage(), 'Acceso denegado') !== false) {
                $this->responder(403, [
                    'estado' => 403, 'mensaje' => 'Acceso denegado.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            } else {
                $this->responder(500, [
                    'estado' => 500, 'mensaje' => 'Error en la operacion de eliminacion.',
                    'detalle' => $e->getMessage(), 'tabla' => $tabla, 'filtro' => "{$nombreClave} = {$valorClave}",
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage(),
                'tabla' => $tabla, 'filtro' => "{$nombreClave} = {$valorClave}",
            ]);
        } catch (\Throwable $e) {
            error_log("ERROR CRITICO - Eliminacion - Tabla: {$tabla}, Clave: {$nombreClave}={$valorClave} - {$e->getMessage()}");
            $this->responderError500($e, $tabla, 'Error interno del servidor al eliminar registro.', "{$nombreClave} = {$valorClave}");
        }
    }

    // =======================================================================
    // verificarContrasenaAsync — POST /api/{tabla}/verificar-contrasena
    // =======================================================================
    // Ejemplo: POST /api/usuario/verificar-contrasena con body JSON:
    //   {
    //     "campoUsuario": "email",              -> la columna donde buscar el usuario
    //     "campoContrasena": "contrasena",      -> la columna donde esta el hash
    //     "valorUsuario": "admin@correo.com",   -> el email a buscar
    //     "valorContrasena": "admin123"          -> la contrasena a verificar
    //   }
    //
    // Respuestas posibles:
    //   200 -> Credenciales correctas (el email existe Y la contrasena coincide)
    //   404 -> Usuario no encontrado (no existe ese email en la tabla)
    //   401 -> Contrasena incorrecta (el email existe pero la contrasena no coincide)
    public function verificarContrasenaAsync(string $tabla, array $datos, ?string $esquema): void
    {
        try {
            error_log(sprintf("INICIO verificacion credenciales - Tabla: %s, Esquema: %s", $tabla, $esquema ?? 'por defecto'));

            if (empty($datos)) {
                $this->responder(400, [
                    'estado' => 400, 'mensaje' => 'Los parametros de verificacion no pueden estar vacios.', 'tabla' => $tabla,
                ]);
                return;
            }

            // VALIDACION DE PARAMETROS REQUERIDOS
            $parametrosRequeridos = ['campoUsuario', 'campoContrasena', 'valorUsuario', 'valorContrasena'];
            foreach ($parametrosRequeridos as $parametro) {
                if (!isset($datos[$parametro]) || empty(trim((string)$datos[$parametro]))) {
                    $this->responder(400, [
                        'estado'              => 400,
                        'mensaje'             => "El parametro '{$parametro}' es requerido.",
                        'tabla'               => $tabla,
                        'parametrosRequeridos' => $parametrosRequeridos,
                    ]);
                    return;
                }
            }

            $campoUsuario    = (string)$datos['campoUsuario'];
            $campoContrasena = (string)$datos['campoContrasena'];
            $valorUsuario    = (string)$datos['valorUsuario'];
            $valorContrasena = (string)$datos['valorContrasena'];

            error_log("Verificando credenciales - Usuario: {$valorUsuario}, Tabla: {$tabla}");

            $resultado = $this->servicioCrud->verificarContrasenaAsync(
                $tabla, $esquema, $campoUsuario, $campoContrasena, $valorUsuario, $valorContrasena
            );

            switch ($resultado['codigo']) {
                case 200:
                    error_log("EXITO autenticacion - Usuario {$valorUsuario} en tabla {$tabla}");
                    $this->responder(200, [
                        'estado' => 200, 'mensaje' => 'Credenciales verificadas exitosamente.',
                        'tabla' => $tabla, 'usuario' => $valorUsuario, 'autenticado' => true,
                    ]);
                    break;

                case 404:
                    error_log("FALLO autenticacion - Usuario {$valorUsuario} no encontrado en {$tabla}");
                    $this->responder(404, [
                        'estado' => 404, 'mensaje' => 'Usuario no encontrado.',
                        'tabla' => $tabla, 'usuario' => $valorUsuario, 'autenticado' => false,
                    ]);
                    break;

                case 401:
                    error_log("FALLO autenticacion - Contrasena incorrecta para {$valorUsuario} en {$tabla}");
                    $this->responder(401, [
                        'estado' => 401, 'mensaje' => 'Contrasena incorrecta.',
                        'tabla' => $tabla, 'usuario' => $valorUsuario, 'autenticado' => false,
                    ]);
                    break;

                default:
                    $this->responder(500, [
                        'estado' => 500, 'mensaje' => 'Error durante la verificacion.',
                        'detalle' => $resultado['mensaje'], 'tabla' => $tabla,
                    ]);
            }

        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Acceso denegado') !== false) {
                $this->responder(403, [
                    'estado' => 403, 'mensaje' => 'Acceso denegado.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
                ]);
            } else {
                $this->responderError500($e, $tabla, 'Error interno al verificar credenciales.');
            }
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, [
                'estado' => 400, 'mensaje' => 'Parametros invalidos.', 'detalle' => $e->getMessage(), 'tabla' => $tabla,
            ]);
        } catch (\Throwable $e) {
            error_log("ERROR CRITICO - Verificacion credenciales - Tabla: {$tabla} - {$e->getMessage()}");
            $this->responderError500($e, $tabla, 'Error interno del servidor al verificar credenciales.');
        }
    }

    // =======================================================================
    // obtenerInformacion — GET /api/info — Info de la API
    // =======================================================================
    // Devuelve un JSON con la lista de endpoints disponibles y ejemplos.
    // Es como un "menu" de la API: le dice al usuario que puede hacer.
    public function obtenerInformacion(): void
    {
        $this->responder(200, [
            'controlador' => 'EntidadesController',
            'version'     => '1.0',
            'descripcion' => 'Controlador generico para consultar tablas de base de datos',
            'endpoints'   => [
                'GET    /api/{tabla}                         - Lista registros de una tabla',
                'GET    /api/{tabla}?esquema={e}&limite={n}  - Lista con esquema y limite',
                'GET    /api/{tabla}/{clave}/{valor}         - Obtener por clave',
                'POST   /api/{tabla}                         - Crear registro',
                'PUT    /api/{tabla}/{clave}/{valor}         - Actualizar registro',
                'DELETE /api/{tabla}/{clave}/{valor}         - Eliminar registro',
                'POST   /api/{tabla}/verificar-contrasena    - Verificar credenciales',
                'GET    /api/info                            - Muestra esta informacion',
            ],
            'ejemplos' => [
                'GET    /api/usuarios',
                'GET    /api/productos?esquema=ventas',
                'GET    /api/clientes?limite=50',
                'GET    /api/factura/numero/1',
                'POST   /api/usuario  (body JSON)',
                'PUT    /api/usuario/id/5  (body JSON)',
                'DELETE /api/producto/codigo/PRD001',
            ],
        ]);
    }

    // =======================================================================
    // inicio — GET / — Bienvenida
    // =======================================================================
    // Lo que se ve cuando abres http://localhost:8000/ en el navegador.
    // Es como la pagina de inicio de la API: muestra que es, como usarla,
    // y links a Swagger y a los endpoints.
    public function inicio(): void
    {
        $this->responder(200, [
            'Mensaje'       => 'Bienvenido a la API Generica en PHP',
            'Version'       => '1.0',
            'Descripcion'   => 'API generica para operaciones CRUD sobre cualquier tabla de base de datos',
            'Documentacion' => 'Visita /docs para Swagger UI interactivo',
            'FechaServidor' => gmdate('Y-m-d\TH:i:s\Z'),
            'Enlaces' => [
                'Swagger'      => '/docs',
                'Info'         => '/api/info',
                'EjemploTabla' => '/api/MiTabla',
            ],
            'Uso' => [
                'GET    /api/{tabla}              - Lista registros',
                'GET    /api/{tabla}?limite=50    - Lista con limite',
                'POST   /api/{tabla}              - Crear registro',
                'PUT    /api/{tabla}/{clave}/{val} - Actualizar',
                'DELETE /api/{tabla}/{clave}/{val} - Eliminar',
            ],
        ]);
    }

    // =======================================================================
    // METODOS PRIVADOS DE UTILIDAD (solo los usa esta clase internamente)
    // =======================================================================

    // responder() — Envia una respuesta JSON al navegador/frontend
    //
    // Todos los metodos de arriba usan este metodo para enviar sus respuestas.
    // Recibe el codigo HTTP y los datos, y se encarga de:
    //   1. Poner el codigo HTTP (200, 400, 404, etc.)
    //   2. Poner el header Content-Type para que el navegador sepa que es JSON
    //   3. Convertir el array PHP a JSON y enviarlo con echo
    //
    // Ejemplo: $this->responder(200, ['estado'=>200, 'datos'=>[...]])
    //   -> Envia HTTP 200 con el JSON correspondiente
    private function responder(int $codigo, ?array $datos = null): void
    {
        http_response_code($codigo);  // Poner el codigo HTTP de la respuesta
        header('Content-Type: application/json; charset=utf-8');  // Decirle al navegador: "esto es JSON en UTF-8"

        if ($datos !== null) {
            // json_encode() = convertir array PHP a string JSON
            // JSON_UNESCAPED_UNICODE = no escapar acentos (ñ, é, etc.)
            // JSON_PRETTY_PRINT = formato bonito con indentacion (facil de leer)
            // El "|" combina las dos opciones (operador bitwise OR)
            echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        // Si $datos es null (ej: respuesta 204), no envia body — solo el codigo HTTP.
    }

    // responderError500() — Respuesta detallada para errores internos (500)
    //
    // Cuando algo falla inesperadamente (error de BD, bug, etc.), este metodo
    // arma un JSON con toda la informacion del error para facilitar el debugging:
    //   - Tipo de excepcion
    //   - Mensaje de error
    //   - Error interno (si hay uno encadenado)
    //   - Stack trace (las primeras 3 lineas, para saber donde fallo)
    //   - Timestamp (fecha/hora del error)
    //
    // get_class($excepcion) = nombre de la clase del error (ej: "RuntimeException")
    // $excepcion->getMessage() = el mensaje del error
    // $excepcion->getPrevious() = la excepcion que causo esta (si hay una cadena de errores)
    // $excepcion->getTraceAsString() = el "camino" que siguio el error (stack trace)
    //
    // fn($l) => "  " . trim($l) es una "arrow function" (funcion flecha):
    //   fn($l) => ...  es lo mismo que  function($l) { return ...; }
    //   Es una forma mas corta de escribir funciones anonimas simples.
    private function responderError500(\Throwable $excepcion, string $tabla, string $mensaje, ?string $filtro = null): void
    {
        $detalle = "Tipo: " . get_class($excepcion) . "\n";
        $detalle .= "Mensaje: {$excepcion->getMessage()}\n";

        $prev = $excepcion->getPrevious();
        if ($prev !== null) {
            $detalle .= "Error interno: {$prev->getMessage()}\n";
        }

        // Tomar solo las primeras 3 lineas del stack trace (el completo es muy largo)
        // array_slice() = tomar una porcion de un array (del indice 0, tomar 3 elementos)
        $stackLines = array_slice(explode("\n", $excepcion->getTraceAsString()), 0, 3);
        $detalle .= "Stack trace:\n" . implode("\n", array_map(fn($l) => "  " . trim($l), $stackLines));

        $respuesta = [
            'estado'          => 500,
            'mensaje'         => $mensaje,
            'tabla'           => $tabla,
            'tipoError'       => get_class($excepcion),
            'detalle'         => $excepcion->getMessage(),
            'detalleCompleto' => $detalle,
            'errorInterno'    => ($prev !== null) ? $prev->getMessage() : null,
            'timestamp'       => gmdate('Y-m-d\TH:i:s\Z'),  // Fecha/hora en formato UTC
            'sugerencia'      => 'Revise los logs del servidor para mas detalles.',
        ];

        if ($filtro !== null) {
            $respuesta['filtro'] = $filtro;
        }

        $this->responder(500, $respuesta);
    }
}
