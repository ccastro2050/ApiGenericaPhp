<?php
// ============================================================================
// RepositorioLecturaMysqlMariaDB.php — Ejecuta SQL contra MySQL/MariaDB
// Ubicacion: src/Repositorios/RepositorioLecturaMysqlMariaDB.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Esta clase es la que REALMENTE habla con la base de datos.
//   Es la unica clase de todo el proyecto que ejecuta SQL.
//   Todas las demas clases (servicio, controlador) NO saben SQL.
//
//   Tiene un metodo para cada operacion CRUD:
//     obtenerFilasAsync()         -> SELECT * FROM tabla LIMIT ...
//     obtenerPorClaveAsync()      -> SELECT * FROM tabla WHERE clave = valor
//     crearAsync()                -> INSERT INTO tabla (...) VALUES (...)
//     actualizarAsync()           -> UPDATE tabla SET ... WHERE clave = valor
//     eliminarAsync()             -> DELETE FROM tabla WHERE clave = valor
//     obtenerHashContrasenaAsync()-> SELECT contrasena FROM tabla WHERE email = ...
//     obtenerDiagnosticoConexionAsync() -> Info tecnica de la conexion
//
// QUE ES PDO:
//   PDO = PHP Data Objects. Es la libreria de PHP para conectarse a bases de datos.
//   Funciona con MySQL, PostgreSQL, SQLite, SQL Server, etc.
//   Es como un "traductor universal" entre PHP y cualquier BD.
//
// QUE SON PREPARED STATEMENTS:
//   Son consultas SQL donde los VALORES se pasan por separado, no dentro del SQL.
//   Esto PREVIENE SQL Injection (un ataque donde alguien mete SQL malicioso).
//
//   MAL (vulnerable a SQL injection):
//     $sql = "SELECT * FROM producto WHERE codigo = '$valor'";
//     Si $valor = "'; DROP TABLE producto; --"  -> BORRA LA TABLA
//
//   BIEN (con prepared statement, como lo hacemos aca):
//     $sql = "SELECT * FROM producto WHERE codigo = :valor";
//     $stmt->bindValue(':valor', $valor);
//     Ahora $valor SIEMPRE se trata como dato, nunca como SQL.
//
// CUANDO SE USA EN EL FLUJO:
//   1. index.php crea: $repositorio = new RepositorioLecturaMysqlMariaDB($proveedorConexion)
//   2. Se lo pasa a ServicioCrud
//   3. Cuando ServicioCrud necesita datos, llama a $this->repositorio->obtenerFilasAsync(...)
//   4. Este repositorio arma el SQL, lo ejecuta contra la BD, y devuelve el resultado
//
// EQUIVALENTE EN OTROS LENGUAJES:
//   - C#:     Entity Framework DbContext, o Dapper queries
//   - Java:   JPA Repository, o JDBC
//   - Python: SQLAlchemy, o cursor.execute()
//   - Node.js: Sequelize, o mysql2.query()
// ============================================================================

namespace ApiGenericaPhp\Repositorios;

// Importar la interface que esta clase implementa
use ApiGenericaPhp\Repositorios\Abstracciones\IRepositorioLecturaTabla;
// Importar la interface del proveedor de conexion (para obtener DSN, usuario, password)
use ApiGenericaPhp\Servicios\Abstracciones\IProveedorConexion;
// Importar las clases PDO que vamos a usar
use PDO;          // La clase principal para conectarse a la BD
use PDOException; // El tipo de error que lanza PDO cuando algo falla

class RepositorioLecturaMysqlMariaDB implements IRepositorioLecturaTabla
{
    // El proveedor de conexion (tiene el DSN, usuario, contrasena).
    // Se recibe en el constructor y se guarda para usarlo en cada metodo.
    private IProveedorConexion $proveedorConexion;

    // Constructor: recibe el proveedor de conexion y lo guarda.
    public function __construct(IProveedorConexion $proveedorConexion)
    {
        $this->proveedorConexion = $proveedorConexion;
    }

    // -----------------------------------------------------------------------
    // crearConexion — Crea una conexion PDO a la base de datos
    // -----------------------------------------------------------------------
    // Este metodo PRIVADO se llama internamente cada vez que necesitamos
    // ejecutar una consulta. Crea una conexion nueva a la BD.
    //
    // Retorna un objeto PDO listo para ejecutar SQL.
    private function crearConexion(): PDO
    {
        // new PDO() = crear una conexion a la BD.
        // Necesita 3 cosas: el DSN, el usuario, y la contrasena.
        // El 4to parametro es un array de opciones de configuracion.
        $pdo = new PDO(
            $this->proveedorConexion->obtenerDsn(),        // 'mysql:host=localhost;port=3306;dbname=...'
            $this->proveedorConexion->obtenerUsuario(),     // 'root'
            $this->proveedorConexion->obtenerContrasena(),  // ''
            [
                // ERRMODE_EXCEPTION = si hay un error SQL, lanzar una excepcion
                // (en vez de fallar silenciosamente)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                // FETCH_ASSOC = devolver las filas como arrays asociativos
                // ['codigo' => 'PR001', 'nombre' => 'Laptop'] en vez de [0 => 'PR001', 1 => 'Laptop']
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // false = usar prepared statements REALES del servidor MySQL
                // (mas seguro que los emulados por PHP)
                PDO::ATTR_EMULATE_PREPARES   => false,

                // Asegurar que la conexion use UTF-8 (para acentos y caracteres especiales)
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
        return $pdo;
    }

    // =======================================================================
    // obtenerFilasAsync — SELECT * FROM tabla LIMIT ...
    // =======================================================================
    // Ejemplo: obtenerFilasAsync('producto', null, 10)
    // SQL generado: SELECT * FROM `producto` LIMIT 10
    // Retorna: [['codigo'=>'PR001','nombre'=>'Laptop',...], ['codigo'=>'PR002',...], ...]
    public function obtenerFilasAsync(string $nombreTabla, ?string $esquema, ?int $limite): array
    {
        // Validar que el nombre de la tabla no este vacio
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }

        // Si no se paso un limite, usar 1000 como maximo por defecto
        $limiteFinal = $limite ?? 1000;

        // Si se paso un esquema, agregarlo al SQL con backticks. Si no, string vacio.
        // Backticks (`) son el caracter de escape de MySQL para nombres de tablas/columnas
        // Protegen nombres que sean palabras reservadas de SQL (ej: `order`, `select`)
        $esquemaFinal = (!empty($esquema)) ? "`{$esquema}`." : '';

        // Armar el SQL.
        // NOTA: Los nombres de tabla NO se pueden poner como :parametro en prepared statements.
        // Solo los VALORES se parametrizan. Los nombres de tabla van directo en el SQL.
        // Por eso usamos backticks para proteger el nombre.
        $sql = "SELECT * FROM {$esquemaFinal}`{$nombreTabla}` LIMIT :limite";

        // prepare() = preparar la consulta (la envia al servidor MySQL para que la analice)
        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);

        // bindValue() = asignar un valor al parametro :limite
        // PDO::PARAM_INT = decirle que es un numero entero
        $stmt->bindValue(':limite', $limiteFinal, PDO::PARAM_INT);

        // execute() = ejecutar la consulta preparada contra la BD
        $stmt->execute();

        // fetchAll() = traer TODAS las filas del resultado como un array de arrays
        // Cada fila es un array asociativo: ['codigo'=>'PR001', 'nombre'=>'Laptop', ...]
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =======================================================================
    // obtenerPorClaveAsync — SELECT * FROM tabla WHERE clave = valor
    // =======================================================================
    // Ejemplo: obtenerPorClaveAsync('producto', null, 'codigo', 'PR001')
    // SQL generado: SELECT * FROM `producto` WHERE `codigo` = 'PR001'
    // Retorna: [['codigo'=>'PR001','nombre'=>'Laptop','stock'=>15,...]]
    public function obtenerPorClaveAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valor): array
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($nombreClave))) {
            throw new \InvalidArgumentException("El nombre de la columna clave no puede estar vacio.");
        }

        $esquemaFinal = (!empty($esquema)) ? "`{$esquema}`." : '';

        // WHERE `codigo` = :valor  ->  filtra por la columna que nos dijeron
        $sql = "SELECT * FROM {$esquemaFinal}`{$nombreTabla}` WHERE `{$nombreClave}` = :valor";

        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':valor', $valor);  // Asignar el valor al parametro :valor
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =======================================================================
    // crearAsync — INSERT INTO tabla (columnas) VALUES (valores)
    // =======================================================================
    // Ejemplo: crearAsync('producto', null, ['codigo'=>'PR099','nombre'=>'Test','stock'=>5,'valorunitario'=>1000])
    // SQL generado: INSERT INTO `producto` (`codigo`, `nombre`, `stock`, `valorunitario`) VALUES (:codigo, :nombre, :stock, :valorunitario)
    // Retorna: true si se inserto, false si no
    public function crearAsync(string $nombreTabla, ?string $esquema, array $datos, ?string $camposEncriptar = null): bool
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty($datos)) {
            throw new \InvalidArgumentException("El diccionario de datos no puede estar vacio.");
        }

        $esquemaFinal = (!empty($esquema)) ? "`{$esquema}`." : '';

        // --- ENCRIPTACION DE CAMPOS ---
        // Si se pidio encriptar algun campo (ej: ?camposEncriptar=contrasena),
        // se hashea con BCrypt ANTES de guardarlo en la BD.
        //
        // password_hash() = funcion nativa de PHP para hashear contrasenas.
        //   password_hash('admin123', PASSWORD_BCRYPT)
        //   Resultado: '$2y$10$7Fl0IeW...' (un hash irreversible de 60 caracteres)
        //
        // PASSWORD_BCRYPT = el algoritmo de hasheo (el mas usado para contrasenas)
        // 'cost' => 10 = cuantas veces se repite el hasheo (mas alto = mas seguro pero mas lento)
        if (!empty($camposEncriptar)) {
            // explode(',', 'contrasena,pin') = ['contrasena', 'pin']
            $campos = array_map('trim', explode(',', $camposEncriptar));
            foreach ($campos as $campo) {
                // isset() = verificar si ese campo existe en los datos
                if (isset($datos[$campo])) {
                    $datos[$campo] = password_hash((string)$datos[$campo], PASSWORD_BCRYPT, ['cost' => 10]);
                }
            }
        }

        // Armar las listas de columnas y placeholders para el SQL
        $columnas = [];      // ['`codigo`', '`nombre`', '`stock`', '`valorunitario`']
        $placeholders = [];  // [':codigo', ':nombre', ':stock', ':valorunitario']
        $valores = [];       // [':codigo' => 'PR099', ':nombre' => 'Test', ...]

        // foreach recorre cada par columna => valor del array de datos
        foreach ($datos as $columna => $valor) {
            $columnas[] = "`{$columna}`";          // Agregar nombre de columna con backticks
            $placeholders[] = ":{$columna}";        // Agregar placeholder para prepared statement
            $valores[":{$columna}"] = $valor;       // Guardar el valor asociado al placeholder
        }

        // sprintf() arma el SQL final.
        // implode(', ', $array) = unir elementos de un array con ', ' entre ellos
        //   implode(', ', ['`codigo`', '`nombre`']) -> '`codigo`, `nombre`'
        $sql = sprintf(
            "INSERT INTO %s`%s` (%s) VALUES (%s)",
            $esquemaFinal,                     // '' o '`esquema`.'
            $nombreTabla,                      // 'producto'
            implode(', ', $columnas),          // '`codigo`, `nombre`, `stock`, `valorunitario`'
            implode(', ', $placeholders)        // ':codigo, :nombre, :stock, :valorunitario'
        );
        // SQL final: INSERT INTO `producto` (`codigo`, `nombre`, ...) VALUES (:codigo, :nombre, ...)

        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);

        // Asignar cada valor a su placeholder
        foreach ($valores as $param => $val) {
            $stmt->bindValue($param, $val);
        }

        $stmt->execute();

        // rowCount() = cuantas filas se afectaron.
        // Si se inserto 1 fila, rowCount() = 1, y 1 > 0 = true
        return $stmt->rowCount() > 0;
    }

    // =======================================================================
    // actualizarAsync — UPDATE tabla SET col=val WHERE clave = valor
    // =======================================================================
    // Ejemplo: actualizarAsync('producto', null, 'codigo', 'PR001', ['stock'=>99])
    // SQL generado: UPDATE `producto` SET `stock` = :p_stock WHERE `codigo` = :valorClave
    // Retorna: 1 (una fila actualizada) o 0 (no encontro el registro)
    public function actualizarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave, array $datos, ?string $camposEncriptar = null): int
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($nombreClave))) {
            throw new \InvalidArgumentException("El nombre de la columna clave no puede estar vacio.");
        }
        if (empty($datos)) {
            throw new \InvalidArgumentException("El diccionario de datos no puede estar vacio.");
        }

        $esquemaFinal = (!empty($esquema)) ? "`{$esquema}`." : '';

        // Encriptar campos sensibles si se solicita (misma logica que en crearAsync)
        if (!empty($camposEncriptar)) {
            $campos = array_map('trim', explode(',', $camposEncriptar));
            foreach ($campos as $campo) {
                if (isset($datos[$campo])) {
                    $datos[$campo] = password_hash((string)$datos[$campo], PASSWORD_BCRYPT, ['cost' => 10]);
                }
            }
        }

        // Armar las asignaciones SET para el UPDATE
        $asignaciones = [];  // ['`stock` = :p_stock', '`nombre` = :p_nombre']
        $valores = [];       // [':p_stock' => 99, ':p_nombre' => 'Nuevo nombre']

        foreach ($datos as $columna => $valor) {
            // Usamos "p_" como prefijo para que no choque con :valorClave
            $paramName = "p_{$columna}";
            $asignaciones[] = "`{$columna}` = :{$paramName}";  // `stock` = :p_stock
            $valores[":{$paramName}"] = $valor;                 // :p_stock => 99
        }

        // SQL final: UPDATE `producto` SET `stock` = :p_stock WHERE `codigo` = :valorClave
        $sql = sprintf(
            "UPDATE %s`%s` SET %s WHERE `%s` = :valorClave",
            $esquemaFinal,
            $nombreTabla,
            implode(', ', $asignaciones),  // '`stock` = :p_stock, `nombre` = :p_nombre'
            $nombreClave                    // 'codigo'
        );

        // Agregar el valor de la clave del WHERE
        $valores[':valorClave'] = $valorClave;  // :valorClave => 'PR001'

        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);

        foreach ($valores as $param => $val) {
            $stmt->bindValue($param, $val);
        }

        $stmt->execute();
        return $stmt->rowCount(); // Cuantas filas se actualizaron (0 o 1 normalmente)
    }

    // =======================================================================
    // eliminarAsync — DELETE FROM tabla WHERE clave = valor
    // =======================================================================
    // Ejemplo: eliminarAsync('producto', null, 'codigo', 'PR001')
    // SQL generado: DELETE FROM `producto` WHERE `codigo` = :valorClave
    // Retorna: 1 (una fila eliminada) o 0 (no encontro el registro)
    public function eliminarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave): int
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($nombreClave))) {
            throw new \InvalidArgumentException("El nombre de la columna clave no puede estar vacio.");
        }

        $esquemaFinal = (!empty($esquema)) ? "`{$esquema}`." : '';
        $sql = "DELETE FROM {$esquemaFinal}`{$nombreTabla}` WHERE `{$nombreClave}` = :valorClave";

        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':valorClave', $valorClave);
        $stmt->execute();

        return $stmt->rowCount(); // Cuantas filas se eliminaron
    }

    // =======================================================================
    // obtenerHashContrasenaAsync — Obtener el hash BCrypt de un usuario
    // =======================================================================
    // Ejemplo: obtenerHashContrasenaAsync('usuario', null, 'email', 'contrasena', 'admin@correo.com')
    // SQL generado: SELECT `contrasena` FROM `usuario` WHERE `email` = :usuario LIMIT 1
    // Retorna: '$2y$10$7Fl0IeW...' (el hash) o null (si no existe el usuario)
    //
    // Este metodo NO verifica la contrasena. Solo trae el hash de la BD.
    // La verificacion la hace ServicioCrud con password_verify().
    public function obtenerHashContrasenaAsync(string $nombreTabla, ?string $esquema, string $campoUsuario, string $campoContrasena, string $valorUsuario): ?string
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }

        $esquemaFinal = (!empty($esquema)) ? "`{$esquema}`." : '';

        // Solo selecciona la columna de contrasena, no toda la fila (por seguridad)
        $sql = "SELECT `{$campoContrasena}` FROM {$esquemaFinal}`{$nombreTabla}` WHERE `{$campoUsuario}` = :usuario LIMIT 1";

        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuario', $valorUsuario);
        $stmt->execute();

        // fetchColumn() = traer SOLO el valor de la primera columna de la primera fila
        // En vez de un array asociativo, devuelve directamente el valor.
        // Si no encontro el usuario, devuelve false.
        $resultado = $stmt->fetchColumn();

        // Si es false (no encontro), devolver null. Si encontro, devolver como string.
        return ($resultado === false) ? null : (string)$resultado;
    }

    // =======================================================================
    // obtenerDiagnosticoConexionAsync — Info tecnica de la conexion
    // =======================================================================
    // Se usa cuando el frontend llama a GET /api/info
    // Retorna: version del servidor, nombre de la BD, usuario, uptime, etc.
    // Es util para verificar que la conexion a la BD funciona correctamente.
    public function obtenerDiagnosticoConexionAsync(): array
    {
        // try/catch = intentar ejecutar codigo y atrapar errores si fallan.
        // Si algo dentro del "try" lanza una excepcion, el "catch" la atrapa
        // y podemos manejar el error en vez de que el programa se caiga.
        try {
            $pdo = $this->crearConexion();

            // query() = ejecutar SQL directamente (sin prepared statement, porque no tiene parametros)
            // Estas son funciones internas de MySQL que devuelven info del servidor.
            $stmt = $pdo->query("
                SELECT
                    DATABASE() AS nombre_base_datos,
                    SCHEMA() AS esquema_actual,
                    VERSION() AS version_servidor,
                    @@hostname AS nombre_servidor,
                    @@port AS puerto_servidor,
                    @@version_comment AS tipo_servidor,
                    USER() AS usuario_actual,
                    CONNECTION_ID() AS id_proceso_conexion
            ");
            // fetch() = traer UNA fila (a diferencia de fetchAll que trae todas)
            $info = $stmt->fetch(PDO::FETCH_ASSOC);

            // Obtener cuanto tiempo lleva prendido MySQL (uptime)
            $stmtUptime = $pdo->query("SHOW STATUS LIKE 'Uptime'");
            $uptimeRow = $stmtUptime->fetch(PDO::FETCH_ASSOC);
            $uptimeSegundos = (int)($uptimeRow['Value'] ?? 0); // (int) convierte string a numero

            // Calcular desde cuando esta prendido
            // time() = hora actual en segundos desde 1970 (Unix timestamp)
            // date() = convertir un timestamp a formato legible
            $horaInicio = date('Y-m-d\TH:i:s', time() - $uptimeSegundos);

            // intdiv() = division entera (sin decimales)
            // % = modulo (resto de la division)
            // 86400 = segundos en un dia (24 * 60 * 60)
            // 3600 = segundos en una hora (60 * 60)
            $dias = intdiv($uptimeSegundos, 86400);
            $horas = intdiv($uptimeSegundos % 86400, 3600);
            $minutos = intdiv($uptimeSegundos % 3600, 60);

            // Determinar si es MySQL o MariaDB leyendo el tipo de servidor
            // stripos() = buscar un texto dentro de otro (case-insensitive)
            // Si encuentra 'MariaDB' en el tipo, es MariaDB. Si no, es MySQL.
            $tipoServidor = $info['tipo_servidor'] ?? '';
            $proveedor = (stripos($tipoServidor, 'MariaDB') !== false) ? 'MariaDB' : 'MySQL';

            // Interpretar si es Docker o local segun cuanto tiempo lleva prendido
            if ($minutos + $horas * 60 < 60) {
                $interpretacion = "Probablemente Docker (iniciado hace {$minutos} minutos)";
            } elseif ($horas < 24) {
                $interpretacion = "Posiblemente Docker o servicio reiniciado (iniciado hace {$horas} horas)";
            } elseif ($dias < 7) {
                $interpretacion = "Probablemente {$proveedor} local (iniciado hace {$dias} dias)";
            } else {
                $interpretacion = "Definitivamente {$proveedor} local (iniciado hace {$dias} dias)";
            }

            // Retornar toda la info como array asociativo
            return [
                'proveedor'         => $proveedor,
                'baseDatos'         => $info['nombre_base_datos'],
                'esquema'           => $info['esquema_actual'] ?? $info['nombre_base_datos'],
                'version'           => $info['version_servidor'],
                'tipoServidor'      => $tipoServidor,
                'servidor'          => $info['nombre_servidor'],
                'puerto'            => (int)$info['puerto_servidor'],
                'horaInicio'        => $horaInicio,
                'usuarioConectado'  => $info['usuario_actual'],
                'idProcesoConexion' => (int)$info['id_proceso_conexion'],
                'tiempoEncendido'   => "{$dias} dias, {$horas} horas, {$minutos} minutos",
                'tipoConexion'      => $interpretacion,
                'explicacion'       => "Si 'horaInicio' es reciente, probablemente es Docker. Si es antigua, probablemente es base de datos local.",
            ];

        // catch = atrapar el error si algo fallo
        // PDOException $e = el tipo de error y la variable donde se guarda
        } catch (PDOException $e) {
            // Relanzar como RuntimeException con un mensaje mas descriptivo
            // $e->getMessage() = el mensaje de error original de PDO
            // $e->getCode() = el codigo de error de MySQL
            throw new \RuntimeException(
                "Error MySQL/MariaDB al obtener diagnostico de conexion: {$e->getMessage()}. Codigo: {$e->getCode()}",
                0,    // Codigo de error personalizado (0 = no especificado)
                $e    // La excepcion original (para poder ver la traza completa)
            );
        }
    }
}
