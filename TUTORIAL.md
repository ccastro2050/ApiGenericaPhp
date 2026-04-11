# Tutorial: Como se creo ApiGenericaPhp paso a paso

Tutorial para principiantes. Explica desde cero como se construyo esta API REST generica en PHP vanilla con MySQL/MariaDB.

---

## Tabla de Contenidos

1. [Que vamos a construir](#1-que-vamos-a-construir)
2. [Requisitos previos](#2-requisitos-previos)
3. [Paso 1: Crear la estructura de carpetas](#paso-1-crear-la-estructura-de-carpetas)
4. [Paso 2: El archivo de configuracion](#paso-2-el-archivo-de-configuracion-configconfigphp)
5. [Paso 3: El autoloader](#paso-3-el-autoloader-vendorautoloadphp)
6. [Paso 4: La interface de conexion](#paso-4-la-interface-de-conexion)
7. [Paso 5: La implementacion de conexion](#paso-5-la-implementacion-de-conexion)
8. [Paso 6: La interface del repositorio](#paso-6-la-interface-del-repositorio)
9. [Paso 7: El repositorio MySQL/MariaDB](#paso-7-el-repositorio-mysqlmariadb)
10. [Paso 8: La interface de politica de tablas](#paso-8-la-interface-de-politica-de-tablas)
11. [Paso 9: La implementacion de politica](#paso-9-la-implementacion-de-politica)
12. [Paso 10: La interface del servicio](#paso-10-la-interface-del-servicio)
13. [Paso 11: El servicio CRUD](#paso-11-el-servicio-crud)
14. [Paso 12: El controlador](#paso-12-el-controlador)
15. [Paso 13: El entry point y router](#paso-13-el-entry-point-y-router-indexphp)
16. [Paso 14: La base de datos](#paso-14-la-base-de-datos)
17. [Paso 15: Ejecutar y probar](#paso-15-ejecutar-y-probar)
18. [Resumen de la arquitectura](#resumen-de-la-arquitectura)

---

## 1. Que vamos a construir

Una API REST que permite hacer CRUD (Crear, Leer, Actualizar, Eliminar) sobre **cualquier tabla** de una base de datos MySQL/MariaDB, sin necesidad de crear un controlador por cada tabla.

```
GET    /api/producto          → Lista todos los productos
GET    /api/producto/codigo/PR001  → Obtiene producto PR001
POST   /api/producto          → Crea un producto (datos en body JSON)
PUT    /api/producto/codigo/PR001  → Actualiza producto PR001
DELETE /api/producto/codigo/PR001  → Elimina producto PR001

Y funciona igual con CUALQUIER tabla:
GET    /api/cliente
GET    /api/factura
GET    /api/lo_que_sea
```

### Por que es "generica"?

Porque un solo controlador maneja TODAS las tablas. No hay `ProductoController`, `ClienteController`, `FacturaController`... hay un solo `EntidadesController` que recibe el nombre de la tabla como parametro en la URL.

---

## 2. Requisitos previos

- **PHP 8.0+** instalado (viene con XAMPP)
- **MySQL o MariaDB** corriendo (viene con XAMPP)
- Un editor de texto (VS Code recomendado)
- Saber que es una base de datos, una tabla y SQL basico

No necesitas saber PHP avanzado. Este tutorial explica cada concepto.

---

## Paso 1: Crear la estructura de carpetas

Crear estas carpetas vacias:

```
ApiGenericaPhp/
├── public/              ← Aqui va el entry point (lo que ve el servidor web)
├── src/                 ← Aqui va TODO el codigo PHP organizado
│   ├── Controllers/     ← Capa HTTP (recibe peticiones, responde JSON)
│   ├── Servicios/       ← Capa de negocio (valida, normaliza)
│   │   └── Abstracciones/  ← Interfaces del servicio
│   ├── Repositorios/    ← Capa de datos (ejecuta SQL)
│   │   └── Abstracciones/  ← Interfaces del repositorio
│   ├── Conexion/        ← Proveedor de conexion a BD
│   └── Politicas/       ← Reglas de acceso a tablas
├── config/              ← Configuracion (conexion BD, CORS)
├── vendor/              ← Autoloader (carga clases automaticamente)
└── script_bd/           ← Scripts SQL para crear la base de datos
```

**Por que tantas carpetas?** Cada carpeta es una "capa" con una responsabilidad unica. Esto se llama **arquitectura en capas** y es un patron de diseno que permite:
- Cambiar la base de datos sin tocar el controlador
- Cambiar las reglas de negocio sin tocar la base de datos
- Testear cada capa por separado

---

## Paso 2: El archivo de configuracion (config/config.php)

Lo primero es definir **donde** esta la base de datos. En C# esto se hace en `appsettings.json`. En PHP usamos un archivo PHP que retorna un array:

```php
<?php
// config/config.php

return [
    'DatabaseProvider' => 'MariaDB',

    'ConnectionStrings' => [
        'MariaDB' => [
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'bdfacturas_mariadb_local',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],
    ],

    'TablasProhibidas' => [],

    'Cors' => [
        'AllowedOrigins' => '*',
        'AllowedMethods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'AllowedHeaders' => 'Content-Type, Authorization',
    ],
];
```

**Que es cada cosa:**
- `DatabaseProvider`: cual motor de BD usar (por ahora solo MariaDB)
- `ConnectionStrings`: datos para conectarse (host, puerto, nombre de BD, usuario, contrasena)
- `TablasProhibidas`: tablas que la API no debe tocar (seguridad)
- `Cors`: permisos para que frontends en otros puertos puedan llamar a la API

---

## Paso 3: El autoloader (vendor/autoload.php)

En PHP, cada clase vive en su propio archivo. PHP no sabe donde encontrarlas automaticamente (a diferencia de C# o Python). El autoloader le dice a PHP: "cuando alguien pida la clase `ApiGenericaPhp\Conexion\ProveedorConexion`, busca el archivo `src/Conexion/ProveedorConexion.php`".

```php
<?php
// vendor/autoload.php

spl_autoload_register(function (string $clase) {
    $prefijo = 'ApiGenericaPhp\\';           // Namespace base
    $dirBase = __DIR__ . '/../src/';          // Carpeta donde buscar

    // Si la clase no es de nuestro proyecto, ignorar
    $longitudPrefijo = strlen($prefijo);
    if (strncmp($prefijo, $clase, $longitudPrefijo) !== 0) {
        return;
    }

    // Convertir namespace a ruta de archivo:
    // ApiGenericaPhp\Conexion\ProveedorConexion → src/Conexion/ProveedorConexion.php
    $claseRelativa = substr($clase, $longitudPrefijo);
    $archivo = $dirBase . str_replace('\\', '/', $claseRelativa) . '.php';

    if (file_exists($archivo)) {
        require $archivo;
    }
});
```

**Que hace `spl_autoload_register`?** Registra una funcion que PHP llamara automaticamente cada vez que alguien use una clase que no ha sido cargada. Es como decirle a PHP: "si no encuentras una clase, usa esta funcion para buscarla".

---

## Paso 4: La interface de conexion

Antes de escribir codigo que se conecte a la base de datos, definimos un **contrato** (interface) que dice QUE se puede hacer, pero no COMO:

```php
<?php
// src/Servicios/Abstracciones/IProveedorConexion.php

namespace ApiGenericaPhp\Servicios\Abstracciones;

interface IProveedorConexion
{
    public function getProveedorActual(): string;   // "MariaDB", "MySQL", etc.
    public function obtenerDsn(): string;           // DSN para PDO
    public function obtenerUsuario(): string;       // usuario de BD
    public function obtenerContrasena(): string;    // contrasena de BD
}
```

**Por que una interface?** Porque manana podemos cambiar de MySQL a PostgreSQL creando otra clase que implemente la misma interface, sin tocar nada mas. Esto es el principio **DIP** (Dependency Inversion) de SOLID.

**Que es `namespace`?** Es como un "apellido" para las clases. Evita conflictos de nombres. Es igual que `namespace` en C#.

---

## Paso 5: La implementacion de conexion

Ahora la clase concreta que IMPLEMENTA la interface:

```php
<?php
// src/Conexion/ProveedorConexion.php

namespace ApiGenericaPhp\Conexion;

use ApiGenericaPhp\Servicios\Abstracciones\IProveedorConexion;

class ProveedorConexion implements IProveedorConexion
{
    private array $configuracion;   // Guarda la config de config.php

    public function __construct(array $configuracion)
    {
        $this->configuracion = $configuracion;   // Recibe config por constructor
    }

    public function getProveedorActual(): string
    {
        return $this->configuracion['DatabaseProvider'] ?? 'MariaDB';
    }

    public function obtenerDsn(): string
    {
        $datos = $this->configuracion['ConnectionStrings'][$this->getProveedorActual()];
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $datos['host'], $datos['port'], $datos['database'], $datos['charset']);
    }

    public function obtenerUsuario(): string { /* lee de config */ }
    public function obtenerContrasena(): string { /* lee de config */ }
}
```

**Que es `implements`?** Dice "esta clase cumple el contrato de IProveedorConexion". Si no implementa todos los metodos de la interface, PHP da error. Es igual que `: IProveedorConexion` en C#.

---

## Paso 6: La interface del repositorio

El repositorio es quien ejecuta SQL. Primero el contrato:

```php
<?php
// src/Repositorios/Abstracciones/IRepositorioLecturaTabla.php

namespace ApiGenericaPhp\Repositorios\Abstracciones;

interface IRepositorioLecturaTabla
{
    // CRUD completo - cada metodo es una operacion SQL
    public function obtenerFilasAsync(string $tabla, ?string $esquema, ?int $limite): array;
    public function obtenerPorClaveAsync(string $tabla, ?string $esquema, string $clave, string $valor): array;
    public function crearAsync(string $tabla, ?string $esquema, array $datos, ?string $camposEncriptar): bool;
    public function actualizarAsync(string $tabla, ?string $esquema, string $clave, string $valor, array $datos, ?string $camposEncriptar): int;
    public function eliminarAsync(string $tabla, ?string $esquema, string $clave, string $valor): int;
}
```

**Que es `?string`?** El `?` significa que el parametro puede ser `string` o `null`. Equivalente a `string?` en C#.

**Que es `array`?** En PHP, `array` es el tipo para listas y diccionarios. No hay `List<>` ni `Dictionary<>` como en C# — `array` hace todo.

---

## Paso 7: El repositorio MySQL/MariaDB

La implementacion concreta que ejecuta SQL real contra MySQL/MariaDB usando **PDO** (PHP Data Objects):

```php
<?php
// src/Repositorios/RepositorioLecturaMysqlMariaDB.php

namespace ApiGenericaPhp\Repositorios;

use ApiGenericaPhp\Repositorios\Abstracciones\IRepositorioLecturaTabla;
use ApiGenericaPhp\Servicios\Abstracciones\IProveedorConexion;
use PDO;

class RepositorioLecturaMysqlMariaDB implements IRepositorioLecturaTabla
{
    private IProveedorConexion $proveedorConexion;

    public function __construct(IProveedorConexion $proveedorConexion)
    {
        $this->proveedorConexion = $proveedorConexion;
    }

    private function crearConexion(): PDO
    {
        return new PDO(
            $this->proveedorConexion->obtenerDsn(),
            $this->proveedorConexion->obtenerUsuario(),
            $this->proveedorConexion->obtenerContrasena(),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Lanzar excepciones en error
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Resultado como array asociativo
                PDO::ATTR_EMULATE_PREPARES => false,  // Prepared statements reales (seguridad)
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',  // Soporte de acentos
            ]
        );
    }

    public function obtenerFilasAsync(string $tabla, ?string $esquema, ?int $limite): array
    {
        $limiteFinal = $limite ?? 1000;  // Si no se indica limite, maximo 1000
        $sql = "SELECT * FROM `{$tabla}` LIMIT :limite";
        //                     ^^^^^^^^        ^^^^^^^
        //                     backticks       prepared statement (previene SQL injection)

        $pdo = $this->crearConexion();
        $stmt = $pdo->prepare($sql);          // Preparar la consulta
        $stmt->bindValue(':limite', $limiteFinal, PDO::PARAM_INT);  // Asignar valor seguro
        $stmt->execute();                     // Ejecutar

        return $stmt->fetchAll();  // Retorna array de arrays asociativos
        // Ejemplo: [['codigo' => 'PR001', 'nombre' => 'Laptop', 'stock' => 15], ...]
    }

    // Los demas metodos (crear, actualizar, eliminar) siguen el mismo patron:
    // 1. Construir SQL con placeholders (:param)
    // 2. Preparar con $pdo->prepare()
    // 3. Asignar valores con $stmt->bindValue()
    // 4. Ejecutar con $stmt->execute()
}
```

**Que es PDO?** Es la forma estandar de PHP para conectarse a bases de datos. Funciona con MySQL, PostgreSQL, SQLite, etc. Equivalente a `SqlConnection` / `MySqlConnection` en C#.

**Que son prepared statements?** En vez de poner valores directamente en el SQL (peligroso: SQL injection), se usan placeholders `:nombre` y se asignan los valores por separado. PDO los escapa automaticamente.

```php
// PELIGROSO (SQL injection):
$sql = "SELECT * FROM producto WHERE codigo = '$valor'";
// Si $valor = "'; DROP TABLE producto; --" → destruye la tabla

// SEGURO (prepared statement):
$sql = "SELECT * FROM producto WHERE codigo = :valor";
$stmt->bindValue(':valor', $valor);  // PDO escapa automaticamente
```

---

## Paso 8: La interface de politica de tablas

Para seguridad: poder prohibir acceso a ciertas tablas:

```php
<?php
// src/Servicios/Abstracciones/IPoliticaTablasProhibidas.php

namespace ApiGenericaPhp\Servicios\Abstracciones;

interface IPoliticaTablasProhibidas
{
    public function esTablaPermitida(string $nombreTabla): bool;
}
```

---

## Paso 9: La implementacion de politica

```php
<?php
// src/Politicas/PoliticaTablasProhibidasDesdeConfig.php

namespace ApiGenericaPhp\Politicas;

use ApiGenericaPhp\Servicios\Abstracciones\IPoliticaTablasProhibidas;

class PoliticaTablasProhibidasDesdeConfig implements IPoliticaTablasProhibidas
{
    private array $tablasProhibidas;

    public function __construct(array $configuracion)
    {
        // Leer lista de config.php y convertir a minusculas
        $tablas = $configuracion['TablasProhibidas'] ?? [];
        $this->tablasProhibidas = array_map('strtolower', $tablas);
    }

    public function esTablaPermitida(string $nombreTabla): bool
    {
        // Permitida si NO esta en la lista prohibida
        return !in_array(strtolower($nombreTabla), $this->tablasProhibidas);
    }
}
```

---

## Paso 10: La interface del servicio

El servicio es la capa de **logica de negocio**. Valida, normaliza y coordina:

```php
<?php
// src/Servicios/Abstracciones/IServicioCrud.php

namespace ApiGenericaPhp\Servicios\Abstracciones;

interface IServicioCrud
{
    public function listarAsync(string $tabla, ?string $esquema, ?int $limite): array;
    public function obtenerPorClaveAsync(string $tabla, ?string $esquema, string $clave, string $valor): array;
    public function crearAsync(string $tabla, ?string $esquema, array $datos, ?string $camposEncriptar): bool;
    public function actualizarAsync(string $tabla, ?string $esquema, string $clave, string $valor, array $datos, ?string $camposEncriptar): int;
    public function eliminarAsync(string $tabla, ?string $esquema, string $clave, string $valor): int;
}
```

---

## Paso 11: El servicio CRUD

```php
<?php
// src/Servicios/ServicioCrud.php

namespace ApiGenericaPhp\Servicios;

use ApiGenericaPhp\Servicios\Abstracciones\IServicioCrud;
use ApiGenericaPhp\Repositorios\Abstracciones\IRepositorioLecturaTabla;
use ApiGenericaPhp\Servicios\Abstracciones\IPoliticaTablasProhibidas;

class ServicioCrud implements IServicioCrud
{
    private IRepositorioLecturaTabla $repositorio;
    private IPoliticaTablasProhibidas $politica;

    // Recibe dependencias por constructor (inyeccion de dependencias)
    public function __construct(
        IRepositorioLecturaTabla $repositorio,
        IPoliticaTablasProhibidas $politica
    ) {
        $this->repositorio = $repositorio;
        $this->politica = $politica;
    }

    public function listarAsync(string $tabla, ?string $esquema, ?int $limite): array
    {
        // FASE 1: Validar
        if (empty(trim($tabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }

        // FASE 2: Verificar permisos
        if (!$this->politica->esTablaPermitida($tabla)) {
            throw new \RuntimeException("Acceso denegado: La tabla '{$tabla}' esta restringida.");
        }

        // FASE 3: Normalizar parametros
        $esquema = (empty($esquema)) ? null : trim($esquema);
        $limite = ($limite === null || $limite <= 0) ? null : $limite;

        // FASE 4: Delegar al repositorio (no sabe si es MySQL, PostgreSQL, etc.)
        return $this->repositorio->obtenerFilasAsync($tabla, $esquema, $limite);
    }

    // Los demas metodos siguen el mismo patron:
    // Validar → Verificar permisos → Normalizar → Delegar al repositorio
}
```

**Por que no hacer todo en el controlador?** Porque si manana necesitas las mismas validaciones desde otro lugar (un comando de consola, un test, otra API), no puedes reutilizar codigo que esta dentro de un controlador HTTP. La capa de servicio es reutilizable.

---

## Paso 12: El controlador

El controlador solo se encarga de HTTP: recibir la peticion y devolver JSON:

```php
<?php
// src/Controllers/EntidadesController.php

namespace ApiGenericaPhp\Controllers;

use ApiGenericaPhp\Servicios\Abstracciones\IServicioCrud;

class EntidadesController
{
    private IServicioCrud $servicioCrud;

    public function __construct(IServicioCrud $servicioCrud)
    {
        $this->servicioCrud = $servicioCrud;
    }

    // GET /api/{tabla}
    public function listarAsync(string $tabla, ?string $esquema, ?int $limite): void
    {
        try {
            $filas = $this->servicioCrud->listarAsync($tabla, $esquema, $limite);

            if (empty($filas)) {
                $this->responder(204);  // Sin contenido
                return;
            }

            $this->responder(200, [
                'tabla'  => $tabla,
                'total'  => count($filas),
                'datos'  => $filas,
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->responder(400, ['estado' => 400, 'mensaje' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            $this->responder(403, ['estado' => 403, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->responder(500, ['estado' => 500, 'mensaje' => $e->getMessage()]);
        }
    }

    // Metodo privado para enviar respuestas JSON
    private function responder(int $codigo, ?array $datos = null): void
    {
        http_response_code($codigo);                            // Codigo HTTP (200, 404, 500...)
        header('Content-Type: application/json; charset=utf-8'); // Decirle al navegador que es JSON
        if ($datos !== null) {
            echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
}
```

**Que es `void`?** Significa que el metodo no retorna nada. En vez de retornar, escribe directamente al output con `echo`.

**Que es `\Throwable`?** Es la clase base de todas las excepciones en PHP. Equivalente a `Exception` en C#. El `\` al inicio significa "namespace global" (raiz).

---

## Paso 13: El entry point y router (index.php)

Este es el archivo mas importante. Es donde todo se conecta. Hace 3 cosas:
1. Crear las dependencias (inyeccion de dependencias manual)
2. Configurar CORS
3. Parsear la URL y llamar al metodo correcto del controlador

```php
<?php
// public/index.php

// 1. CARGAR AUTOLOADER (para que PHP encuentre las clases)
require_once __DIR__ . '/../vendor/autoload.php';

use ApiGenericaPhp\Conexion\ProveedorConexion;
use ApiGenericaPhp\Repositorios\RepositorioLecturaMysqlMariaDB;
use ApiGenericaPhp\Politicas\PoliticaTablasProhibidasDesdeConfig;
use ApiGenericaPhp\Servicios\ServicioCrud;
use ApiGenericaPhp\Controllers\EntidadesController;

// 2. CARGAR CONFIGURACION
$config = require __DIR__ . '/../config/config.php';

// 3. INYECCION DE DEPENDENCIAS MANUAL
// En C# esto lo hace el contenedor DI automaticamente.
// En PHP lo hacemos a mano, creando cada objeto en orden:
$proveedorConexion = new ProveedorConexion($config);
$repositorio       = new RepositorioLecturaMysqlMariaDB($proveedorConexion);
$politicaTablas    = new PoliticaTablasProhibidasDesdeConfig($config);
$servicioCrud      = new ServicioCrud($repositorio, $politicaTablas);
$controlador       = new EntidadesController($servicioCrud);

// 4. CORS (para que frontends en otros puertos puedan llamar)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 5. ROUTER (parsear la URL y despachar al controlador)
$metodo = $_SERVER['REQUEST_METHOD'];     // GET, POST, PUT, DELETE
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);  // /api/producto
$segmentos = array_values(array_filter(explode('/', $path)));
// Ejemplo: /api/producto/codigo/PR001 → ['api', 'producto', 'codigo', 'PR001']

$tabla = $segmentos[1] ?? '';  // "producto"

// Despachar segun metodo HTTP y cantidad de segmentos
switch ($metodo) {
    case 'GET':
        if (count($segmentos) === 2) {
            $controlador->listarAsync($tabla, $esquema, $limite);
        } elseif (count($segmentos) === 4) {
            $controlador->obtenerPorClaveAsync($tabla, $segmentos[2], $segmentos[3], $esquema);
        }
        break;
    case 'POST':
        $body = json_decode(file_get_contents('php://input'), true);
        $controlador->crearAsync($tabla, $body, $esquema, $camposEncriptar);
        break;
    // PUT, DELETE... mismo patron
}
```

**Que es `file_get_contents('php://input')`?** Lee el body crudo de la peticion HTTP. Es como `[FromBody]` en C#. `php://input` es un stream especial de PHP que contiene el body.

**Que es `explode('/', $path)`?** Divide un string por un separador. Es como `string.Split('/')` en C#.

---

## Paso 14: La base de datos

Crear el script SQL con las tablas, triggers y datos de ejemplo:

```sql
-- script_bd/bdfacturas_mariadb.sql

CREATE DATABASE IF NOT EXISTS bdfacturas_mariadb_local
    CHARACTER SET utf8mb4;

USE bdfacturas_mariadb_local;

CREATE TABLE producto (
    codigo VARCHAR(10) PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    stock INT NOT NULL,
    valorunitario DECIMAL(18,2) NOT NULL
);

INSERT INTO producto VALUES
('PR001', 'Laptop Lenovo', 15, 2500000),
('PR002', 'Monitor Samsung', 26, 800000);

-- Y asi todas las demas tablas...
```

Ejecutar con:

```bash
mysql -u root --default-character-set=utf8mb4 -e "source script_bd/bdfacturas_mariadb.sql"
```

---

## Paso 15: Ejecutar y probar

```bash
# Iniciar el servidor PHP
cd ApiGenericaPhp
php -S localhost:8000 -t public

# Probar en el navegador o con curl:
# http://localhost:8000/api/producto
```

Resultado:

```json
{
    "tabla": "producto",
    "total": 8,
    "datos": [
        {"codigo": "PR001", "nombre": "Laptop Lenovo", "stock": 15, "valorunitario": "2500000.00"},
        {"codigo": "PR002", "nombre": "Monitor Samsung", "stock": 26, "valorunitario": "800000.00"}
    ]
}
```

---

## Resumen de la arquitectura

### Las 4 capas

```
Peticion HTTP: GET /api/producto?limite=5
    |
    v
[index.php]              ROUTER: parsea URL, extrae tabla="producto", limite=5
    |
    v
[EntidadesController]    CAPA HTTP: recibe params, llama al servicio, responde JSON
    |
    v
[ServicioCrud]           CAPA NEGOCIO: valida tabla no vacia, verifica no prohibida, normaliza
    |
    v
[RepositorioMySQL]       CAPA DATOS: ejecuta SELECT * FROM `producto` LIMIT 5
    |
    v
[MariaDB]                BASE DE DATOS: devuelve filas
```

### Por que tantas capas? (analogia del restaurante)

| Capa | Rol en restaurante | Que hace | Que NO hace |
|---|---|---|---|
| **Router** (index.php) | Puerta | Recibe al cliente, lo dirige | No cocina, no sirve |
| **Controller** | Mesero | Toma pedido, lleva la comida | No cocina, no maneja ingredientes |
| **Servicio** | Chef | Decide receta, valida ingredientes | No sirve mesas, no compra |
| **Repositorio** | Bodega | Busca y entrega ingredientes | No cocina, no sabe de recetas |
| **BD** | Granja | Tiene los ingredientes | No sabe que se va a cocinar |

Si el chef (servicio) necesita cambiar de bodega (repositorio), el mesero (controller) no se entera. Cada uno hace lo suyo.

### Orden de creacion (de abajo hacia arriba)

```
1. config.php                    ← Primero: definir DONDE esta la BD
2. IProveedorConexion            ← Contrato: QUE necesito de conexion
3. ProveedorConexion             ← Implementacion: COMO leo la config
4. IRepositorioLecturaTabla      ← Contrato: QUE operaciones SQL existen
5. RepositorioLecturaMysqlMariaDB ← Implementacion: COMO ejecuto SQL en MySQL
6. IPoliticaTablasProhibidas     ← Contrato: QUE restricciones hay
7. PoliticaTablasProhibidasDesdeConfig ← Implementacion: COMO leo restricciones
8. IServicioCrud                 ← Contrato: QUE logica de negocio hay
9. ServicioCrud                  ← Implementacion: COMO valido y coordino
10. EntidadesController          ← COMO manejo HTTP y respondo JSON
11. index.php                    ← COMO conecto todo y parseo URLs
12. Base de datos                ← Los datos reales
```

### Principios SOLID aplicados

| Principio | Donde se ve |
|---|---|
| **S** (Responsabilidad unica) | Controller solo HTTP, Servicio solo negocio, Repositorio solo SQL |
| **O** (Abierto/cerrado) | Agregar PostgreSQL = nueva clase, no modificar las existentes |
| **L** (Sustitucion) | Cualquier repositorio funciona donde se espera IRepositorioLecturaTabla |
| **I** (Segregacion) | Interfaces pequenas: IServicioCrud, IRepositorioLecturaTabla, IProveedorConexion |
| **D** (Inversion) | ServicioCrud depende de IRepositorioLecturaTabla, no de RepositorioLecturaMysqlMariaDB |
