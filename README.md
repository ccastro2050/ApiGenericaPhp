# ApiGenericaPhp - API REST Generica MySQL/MariaDB

![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue?logo=php)
![Database](https://img.shields.io/badge/DB-MySQL_%7C_MariaDB-brightgreen?logo=mariadb)
![Auth](https://img.shields.io/badge/Auth-BCrypt-gold?logo=letsencrypt)
![Architecture](https://img.shields.io/badge/Architecture-Clean_%26_SOLID-orange)
![License](https://img.shields.io/badge/License-Educativo-lightgrey)

API REST generica para operaciones CRUD sobre **cualquier tabla** de MySQL/MariaDB. Un solo controlador maneja TODAS las tablas via `/api/{tabla}`.

---

## Tabla de Contenidos

- [Como funciona el sistema completo](#como-funciona-el-sistema-completo)
- [Caracteristicas](#caracteristicas)
- [Arquitectura](#arquitectura)
- [Patrones de Diseno](#patrones-de-diseno)
- [Principios SOLID Aplicados](#principios-solid-aplicados)
- [Swagger UI](#swagger-ui-documentacion-interactiva)
- [Requisitos](#requisitos)
- [Instalacion](#instalacion)
- [Configuracion](#configuracion)
- [Endpoints](#endpoints)
- [Ejemplos de Uso](#ejemplos-de-uso)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Base de Datos](#base-de-datos-triggers-y-stored-procedures)
- [Como Escalar](#como-escalar-este-proyecto)
- [Solucion de Problemas](#solucion-de-problemas-comunes)

---

## Como funciona el sistema completo

Este proyecto es el **backend** (API REST). Existe un proyecto aparte llamado **FrontPhp_AppiGenericaPhp** que es el frontend. Juntos forman un sistema completo de facturacion.

### Diagrama de conexion

```
+-----------------------------+          +-----------------------------+          +-------------------+
|      NAVEGADOR              |          |       ApiGenericaPhp        |          |    MariaDB/MySQL   |
|  (Usuario en el browser)    |          |     (Backend - API REST)    |          |   (Base de datos)  |
+-----------------------------+          +-----------------------------+          +-------------------+
|                             |          |                             |          |                   |
|  http://localhost/          |  cURL    |  http://localhost:8000      |   PDO    | bdfacturas_       |
|  FrontPhp_AppiGenericaPhp/  | -------> |  /api/producto              | -------> | mariadb_local     |
|  pages/producto.php         |          |  /api/factura               |          |                   |
|                             | <------- |  /api/cliente               | <------- | 12 tablas         |
|  Renderiza HTML con los     |   JSON   |  /api/vendedor              |   Filas  | 5 triggers        |
|  datos recibidos            |          |  /api/{cualquier_tabla}     |          | 5 stored procs    |
+-----------------------------+          +-----------------------------+          +-------------------+

      Apache (puerto 80)               PHP built-in server (puerto 8000)       MariaDB (puerto 3306)
```

### Flujo de una peticion real

Cuando un usuario abre la pagina de facturas en el navegador:

```
1. El navegador pide: http://localhost/FrontPhp_AppiGenericaPhp/pages/factura.php
2. Apache ejecuta factura.php (frontend PHP)
3. factura.php usa ApiService.php para hacer llamadas cURL a la API:
   - GET http://localhost:8000/api/factura         (listar facturas)
   - GET http://localhost:8000/api/cliente          (listar clientes)
   - GET http://localhost:8000/api/vendedor         (listar vendedores)
   - GET http://localhost:8000/api/persona          (listar personas para nombres)
4. La API recibe cada peticion en public/index.php
5. index.php la enruta al metodo correcto de EntidadesController
6. EntidadesController delega a ServicioCrud (valida, normaliza)
7. ServicioCrud delega al Repositorio (ejecuta SQL con PDO)
8. MariaDB devuelve las filas
9. La API responde JSON a cada llamada cURL
10. factura.php combina los datos (join manual en PHP) y genera HTML
11. Apache envia el HTML al navegador
12. El usuario ve la tabla de facturas con nombres de clientes y vendedores
```

### Como se conectan (archivo clave)

El frontend sabe donde esta la API gracias a `FrontPhp_AppiGenericaPhp/config.php`:

```php
define('API_BASE_URL', 'http://localhost:8000');
```

Y el servicio `FrontPhp_AppiGenericaPhp/services/ApiService.php` usa esa URL para todas las llamadas cURL.

### Para levantar el sistema completo

Se necesitan **tres cosas corriendo al mismo tiempo**:

| Componente | Como se inicia | Puerto |
|---|---|---|
| MariaDB | XAMPP Control Panel (boton Start en MySQL) | 3306 |
| Apache | XAMPP Control Panel (boton Start en Apache) | 80 |
| ApiGenericaPhp | `C:\xampp\php\php.exe -S localhost:8000 -t public` desde la carpeta del proyecto | 8000 |

Luego abrir en el navegador: `http://localhost/FrontPhp_AppiGenericaPhp/pages/home.php`

---

## Caracteristicas

- **CRUD Generico**: Operaciones Create, Read, Update, Delete sobre cualquier tabla
- **MySQL/MariaDB**: Soporte nativo con PDO y prepared statements
- **Swagger UI**: Documentacion interactiva en `/docs` (OpenAPI 3.0)
- **Encriptacion BCrypt**: Hash seguro de contrasenas con `password_hash()` nativo
- **Triggers**: Calculo automatico de subtotales, stock y totales de factura
- **Stored Procedures**: 5 SPs para operaciones maestro-detalle (factura + productos)
- **CORS Configurado**: Listo para consumir desde cualquier frontend
- **Arquitectura Limpia**: Separacion de responsabilidades (Controllers, Services, Repositories)
- **Principios SOLID**: Aplicados en cada capa con interfaces PHP
- **Escalable**: Preparado para agregar PostgreSQL, JWT, y mas controladores

---

## Arquitectura

```
+-------------------------------------------------------------+
|                        CONTROLLERS                          |
|                   EntidadesController.php                    |
|  (ESCALABLE: agregar ProcedimientosController, Consultas,   |
|   Autenticacion, Diagnostico, Estructuras)                  |
+-------------------------------------------------------------+
                              |
                              v
+-------------------------------------------------------------+
|                         SERVICIOS                           |
|         IServicioCrud          |   IPoliticaTablasProhibidas |
|              |                 |              |              |
|         ServicioCrud           |  PoliticaTablas...Config    |
+-------------------------------------------------------------+
                              |
                              v
+-------------------------------------------------------------+
|                       REPOSITORIOS                          |
|  +---------------------+                                    |
|  |  MySQL / MariaDB    |  (ESCALABLE: agregar PostgreSQL,  |
|  |  (PDO + prepared)   |   SQLite, etc.)                   |
|  +---------------------+                                    |
+-------------------------------------------------------------+
                              |
                              v
+-------------------------------------------------------------+
|                      BASE DE DATOS                          |
|  12 tablas + 5 triggers + 5 stored procedures               |
+-------------------------------------------------------------+
```

### Flujo de una peticion dentro de la API

```
GET /api/producto?limite=10

1. index.php           -> Parsea URL, extrae tabla="producto", limite=10
2. EntidadesController -> Recibe params, hace logging, delega al servicio
3. ServicioCrud        -> Valida params, verifica tabla no prohibida, normaliza
4. RepositorioMySQL    -> Ejecuta: SELECT * FROM `producto` LIMIT 10 (prepared statement)
5. MariaDB             -> Devuelve filas
6. RepositorioMySQL    -> Convierte a array asociativo PHP
7. ServicioCrud        -> Retorna datos (futuro: transformaciones de negocio)
8. EntidadesController -> Construye respuesta JSON con metadatos
9. index.php           -> Envia HTTP 200 con Content-Type: application/json

Respuesta:
{
  "tabla": "producto",
  "esquema": "por defecto",
  "limite": 10,
  "total": 8,
  "datos": [...]
}
```

---

## Patrones de Diseno

### 1. Repository Pattern (Patron Repositorio)

Encapsula la logica de acceso a datos detras de una interface. El servicio no sabe si los datos vienen de MySQL, PostgreSQL o un archivo JSON.

```php
// Interface (contrato) - src/Repositorios/Abstracciones/IRepositorioLecturaTabla.php
interface IRepositorioLecturaTabla
{
    public function obtenerFilasAsync(string $tabla, ?string $esquema, ?int $limite): array;
    public function crearAsync(string $tabla, ?string $esquema, array $datos): bool;
    // ... mas metodos CRUD
}

// Implementacion concreta para MySQL - src/Repositorios/RepositorioLecturaMysqlMariaDB.php
class RepositorioLecturaMysqlMariaDB implements IRepositorioLecturaTabla
{
    public function obtenerFilasAsync(string $tabla, ?string $esquema, ?int $limite): array
    {
        // SQL especifico de MySQL: backticks, LIMIT, PDO
        $sql = "SELECT * FROM `{$tabla}` LIMIT :limite";
        // ...
    }
}

// ESCALABILIDAD: Crear otra implementacion sin tocar la existente
class RepositorioLecturaPostgreSQL implements IRepositorioLecturaTabla
{
    public function obtenerFilasAsync(string $tabla, ?string $esquema, ?int $limite): array
    {
        // SQL especifico de PostgreSQL: comillas dobles, etc.
    }
}
```

### 2. Dependency Injection (Inyeccion de Dependencias)

Cada clase recibe sus dependencias por constructor, nunca las crea internamente. En PHP se hace manualmente en `index.php`.

```php
// index.php — Punto de entrada de la API
// Cadena de dependencias (de abajo hacia arriba):
$proveedorConexion = new ProveedorConexion($config);                          // Lee config.php
$repositorio       = new RepositorioLecturaMysqlMariaDB($proveedorConexion);  // Acceso a datos
$politicaTablas    = new PoliticaTablasProhibidasDesdeConfig($config);         // Seguridad
$servicioCrud      = new ServicioCrud($repositorio, $politicaTablas);          // Logica negocio
$controlador       = new EntidadesController($servicioCrud);                   // Capa HTTP
```

### 3. Strategy Pattern (Patron Estrategia)

El proveedor de BD se selecciona en tiempo de ejecucion segun la configuracion. Cambiar de MySQL a PostgreSQL es cambiar una linea en `config.php`.

```php
// index.php — Seleccion de estrategia segun configuracion
switch ($proveedorBD) {
    case 'mariadb':
    case 'mysql':
        $repositorio = new RepositorioLecturaMysqlMariaDB($proveedorConexion);
        break;
    // ESCALABILIDAD: Agregar nuevos cases
    // case 'postgresql':
    //     $repositorio = new RepositorioLecturaPostgreSQL($proveedorConexion);
    //     break;
}
// El resto del codigo NO cambia — ServicioCrud y EntidadesController
// funcionan igual sin importar que repositorio se inyecto
```

### 4. Front Controller Pattern

Todas las peticiones HTTP pasan por un unico punto de entrada (`index.php`) que parsea la URL y despacha al metodo correcto del controlador.

```
Cualquier URL -> index.php -> Router -> EntidadesController.metodo()
```

### 5. Layered Architecture (Arquitectura en Capas)

Cada capa tiene una responsabilidad unica y solo se comunica con la capa adyacente:

| Capa | Responsabilidad | No debe saber sobre |
|---|---|---|
| **Controller** | HTTP: recibir peticion, devolver JSON con status code | SQL, conexiones, reglas de negocio |
| **Servicio** | Negocio: validar, normalizar, aplicar politicas | HTTP, SQL, tipo de BD |
| **Repositorio** | Datos: ejecutar SQL, manejar conexiones PDO | HTTP, reglas de negocio |
| **Conexion** | Infraestructura: leer config, construir DSN | HTTP, SQL, negocio |

---

## Principios SOLID Aplicados

### S - Single Responsibility Principle (Responsabilidad Unica)

Cada clase tiene UNA sola razon para cambiar:

| Clase | Responsabilidad unica | Si cambia... |
|---|---|---|
| `EntidadesController` | Coordinar HTTP (recibir params, devolver JSON) | Solo si cambia la API REST |
| `ServicioCrud` | Aplicar reglas de negocio (validar, normalizar) | Solo si cambian reglas de negocio |
| `RepositorioLecturaMysqlMariaDB` | Ejecutar SQL contra MySQL/MariaDB | Solo si cambia la sintaxis SQL |
| `ProveedorConexion` | Leer config y dar DSN/usuario/password | Solo si cambia como se lee la config |
| `PoliticaTablasProhibidasDesdeConfig` | Determinar si una tabla esta permitida | Solo si cambia la politica de acceso |

### O - Open/Closed Principle (Abierto/Cerrado)

Abierto para extension, cerrado para modificacion:

```
Quiero agregar PostgreSQL:
  - CREAR: src/Repositorios/RepositorioLecturaPostgreSQL.php (nueva clase)
  - NO MODIFICAR: ServicioCrud.php, EntidadesController.php, ProveedorConexion.php
  - SOLO AGREGAR: un case en index.php y una entrada en config.php
```

### L - Liskov Substitution Principle (Sustitucion de Liskov)

Cualquier clase que implemente `IRepositorioLecturaTabla` puede usarse donde se espera esa interface:

```php
// ServicioCrud funciona IDENTICO con cualquier repositorio:
$servicio = new ServicioCrud($repoMySQL, $politica);     // Funciona
$servicio = new ServicioCrud($repoPostgres, $politica);  // Funciona igual
// ServicioCrud nunca sabe (ni le importa) cual repositorio recibio
```

### I - Interface Segregation Principle (Segregacion de Interfaces)

Interfaces pequenas y especificas, no una "super-interface" que fuerce a implementar metodos innecesarios:

| Interface | Responsabilidad |
|---|---|
| `IRepositorioLecturaTabla` | Operaciones CRUD sobre tablas |
| `IServicioCrud` | Logica de negocio CRUD |
| `IProveedorConexion` | Datos de conexion a BD |
| `IPoliticaTablasProhibidas` | Politica de acceso a tablas |

### D - Dependency Inversion Principle (Inversion de Dependencias)

Modulos de alto nivel NO dependen de modulos de bajo nivel. Ambos dependen de abstracciones:

```
MAL (acoplado):
  EntidadesController -> ServicioCrud -> RepositorioLecturaMysqlMariaDB
  (Si cambio MySQL por PostgreSQL, tengo que modificar ServicioCrud)

BIEN (desacoplado — como esta implementado):
  EntidadesController -> IServicioCrud <- ServicioCrud
  ServicioCrud -> IRepositorioLecturaTabla <- RepositorioLecturaMysqlMariaDB
  (Si cambio MySQL por PostgreSQL, ServicioCrud NO cambia)
```

---

## Swagger UI (documentacion interactiva)

La API incluye **Swagger UI** para probar los endpoints desde el navegador:

```
http://localhost:8000/docs
```

Swagger UI se genera a partir de `public/openapi.json` (especificacion OpenAPI 3.0). Si agregas nuevos endpoints, actualiza ese archivo para que aparezcan en Swagger.

### Que es openapi.json

Es un archivo JSON que **describe** la API: que endpoints tiene, que parametros recibe, que respuestas devuelve. Swagger UI lo lee y genera la interfaz visual interactiva.

```json
{
  "openapi": "3.0.3",
  "info": {
    "title": "API Generica PHP",
    "version": "1.0.0"
  },
  "servers": [{ "url": "http://localhost:8000" }],
  "paths": {
    "/api/{tabla}": {
      "get": {
        "summary": "Listar...",
        "parameters": [...],
        "responses": {
          "200": { "description": "OK" },
          "404": { "description": "No encontrado" }
        }
      }
    }
  }
}
```

---

## Requisitos

| Requisito | Version |
|---|---|
| PHP | 8.0 o superior |
| Extensiones PHP | `pdo`, `pdo_mysql` |
| Composer | Para autoload PSR-4 (o usar el autoload manual incluido) |
| Base de datos | MySQL 8.0+ o MariaDB 10.4+ |

---

## Instalacion

### 1. Clonar o copiar el proyecto

Copiar la carpeta `ApiGenericaPhp` dentro de `C:\xampp\htdocs\`.

### 2. Instalar dependencias (o usar autoload manual)

```bash
# Opcion A: Con Composer (recomendado)
composer install

# Opcion B: Sin Composer (ya incluido)
# El archivo vendor/autoload.php ya tiene un autoloader PSR-4 manual
```

### 3. Crear la base de datos

```bash
mysql -u root --default-character-set=utf8mb4 -e "source script_bd/bdfacturas_mariadb.sql"
```

### 4. Configurar conexion

Editar `config/config.php` (por defecto: localhost, root, sin password, bdfacturas_mariadb_local).

### 5. Ejecutar la API

```bash
# Si PHP esta en el PATH:
php -S localhost:8000 -t public

# Si PHP NO esta en el PATH (comun en Windows/XAMPP):
C:\xampp\php\php.exe -S localhost:8000 -t public
```

### 6. Abrir Swagger

Navegar a: `http://localhost:8000/docs`

---

## Configuracion

### Archivo config/config.php

```php
return [
    // Proveedor activo (ESCALABLE: agregar "PostgreSQL")
    'DatabaseProvider' => 'MariaDB',

    // Cadenas de conexion
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

    // Tablas prohibidas (blacklist — vacio = todas permitidas)
    'TablasProhibidas' => [],

    // CORS
    'Cors' => [
        'AllowedOrigins' => '*',
        'AllowedMethods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'AllowedHeaders' => 'Content-Type, Authorization',
    ],
];
```

### Cambiar de base de datos

Solo modificar `DatabaseProvider` y agregar la entrada en `ConnectionStrings`:

| Valor | Base de datos | Estado |
|---|---|---|
| `MariaDB` | MariaDB | Implementado |
| `MySQL` | MySQL | Implementado |
| `PostgreSQL` | PostgreSQL | Escalable (crear repositorio) |

---

## Endpoints

### EntidadesController - CRUD Generico

| Metodo | Ruta | Descripcion |
|---|---|---|
| GET | `/` | Bienvenida y guia rapida |
| GET | `/docs` | Swagger UI interactivo |
| GET | `/api/info` | Informacion del controlador |
| GET | `/api/{tabla}` | Listar registros |
| GET | `/api/{tabla}?limite=50` | Listar con limite |
| GET | `/api/{tabla}/{clave}/{valor}` | Obtener por clave |
| POST | `/api/{tabla}` | Crear registro (body JSON) |
| POST | `/api/{tabla}?camposEncriptar=contrasena` | Crear con encriptacion BCrypt |
| PUT | `/api/{tabla}/{clave}/{valor}` | Actualizar registro (body JSON) |
| DELETE | `/api/{tabla}/{clave}/{valor}` | Eliminar registro |
| POST | `/api/{tabla}/verificar-contrasena` | Verificar credenciales BCrypt |

---

## Ejemplos de Uso

### Listar todos los productos

```http
GET /api/producto?limite=10
```

### Obtener producto por codigo

```http
GET /api/producto/codigo/PR001
```

### Crear un producto

```http
POST /api/producto
Content-Type: application/json

{"codigo":"PR_NUEVO","nombre":"Webcam HD","stock":50,"valorunitario":79990}
```

### Actualizar un producto

```http
PUT /api/producto/codigo/PR_NUEVO
Content-Type: application/json

{"stock":99,"valorunitario":69990}
```

### Eliminar un producto

```http
DELETE /api/producto/codigo/PR_NUEVO
```

### Crear usuario con contrasena encriptada

```http
POST /api/usuario?camposEncriptar=contrasena
Content-Type: application/json

{"email":"nuevo@correo.com","contrasena":"123456"}
```

### Verificar credenciales

```http
POST /api/usuario/verificar-contrasena
Content-Type: application/json

{
  "campoUsuario": "email",
  "campoContrasena": "contrasena",
  "valorUsuario": "admin@correo.com",
  "valorContrasena": "admin123"
}
```

---

## Estructura del Proyecto

```
ApiGenericaPhp/
|-- public/
|   |-- index.php                    # Entry point + Router + Inyeccion de Dependencias
|   |-- docs.html                    # Swagger UI
|   |-- openapi.json                 # Especificacion OpenAPI 3.0
|   +-- .htaccess                    # Rewrite para Apache/XAMPP
|
|-- src/
|   |-- Controllers/
|   |   +-- EntidadesController.php  # Controlador CRUD generico HTTP
|   |
|   |-- Servicios/
|   |   |-- Abstracciones/
|   |   |   |-- IServicioCrud.php            # Contrato logica de negocio
|   |   |   |-- IProveedorConexion.php       # Contrato conexion a BD
|   |   |   +-- IPoliticaTablasProhibidas.php # Contrato tablas prohibidas
|   |   +-- ServicioCrud.php                  # Implementacion logica de negocio
|   |
|   |-- Repositorios/
|   |   |-- Abstracciones/
|   |   |   +-- IRepositorioLecturaTabla.php  # Contrato acceso a datos
|   |   +-- RepositorioLecturaMysqlMariaDB.php # Implementacion SQL via PDO
|   |
|   |-- Conexion/
|   |   +-- ProveedorConexion.php    # Lee config, proporciona DSN/usuario/password
|   |
|   +-- Politicas/
|       +-- PoliticaTablasProhibidasDesdeConfig.php  # Blacklist de tablas
|
|-- config/
|   +-- config.php                   # Configuracion centralizada (BD, CORS, puerto)
|
|-- script_bd/
|   +-- bdfacturas_mariadb.sql       # 12 tablas + 5 triggers + 5 SPs + datos de ejemplo
|
|-- vendor/
|   +-- autoload.php                 # PSR-4 autoloader
|
|-- composer.json                    # Definicion del proyecto y autoload
+-- README.md                        # Este archivo
```

---

## Base de Datos: Triggers y Stored Procedures

El script `script_bd/bdfacturas_mariadb.sql` crea toda la base de datos.

### 12 Tablas

| Tabla | Descripcion |
|---|---|
| `empresa` | Empresas (PK: codigo) |
| `persona` | Personas (PK: codigo) |
| `producto` | Productos con stock (PK: codigo) |
| `cliente` | Clientes (FK: persona, empresa) |
| `vendedor` | Vendedores (FK: persona) |
| `factura` | Facturas (FK: cliente, vendedor) |
| `productosporfactura` | Detalle factura (FK: factura ON DELETE CASCADE, producto) |
| `usuario` | Usuarios con contrasena BCrypt |
| `rol` | Roles del sistema |
| `rol_usuario` | Asignacion usuario-rol |
| `ruta` | Rutas del sistema |
| `rutarol` | Permisos ruta-rol |

### 5 Triggers (automaticos en productosporfactura)

| Trigger | Evento | Que hace |
|---|---|---|
| `trg_prodfact_insert` | BEFORE INSERT | Valida stock, calcula subtotal, descuenta stock |
| `trg_prodfact_after_insert` | AFTER INSERT | Recalcula total de la factura |
| `trg_prodfact_update` | BEFORE UPDATE | Valida stock, recalcula subtotal, ajusta stock |
| `trg_prodfact_after_update` | AFTER UPDATE | Recalcula total de la factura |
| `trg_prodfact_delete` | AFTER DELETE | Restaura stock, recalcula total de la factura |

### 5 Stored Procedures (operaciones maestro-detalle)

| SP | Descripcion |
|---|---|
| `sp_insertar_factura_y_productosporfactura` | Crea factura + productos (JSON array) |
| `sp_consultar_factura_y_productosporfactura` | Consulta factura con detalle y nombres |
| `sp_listar_facturas_y_productosporfactura` | Lista todas las facturas con detalle |
| `sp_actualizar_factura_y_productosporfactura` | Reemplaza productos de una factura |
| `sp_borrar_factura_y_productosporfactura` | Elimina factura (CASCADE restaura stock) |

---

## Como Escalar Este Proyecto

Este proyecto esta disenado para crecer. La arquitectura permite agregar funcionalidad **sin modificar** el codigo existente (principio OCP).

### 1. Agregar soporte para PostgreSQL

```php
// CREAR: src/Repositorios/RepositorioLecturaPostgreSQL.php
class RepositorioLecturaPostgreSQL implements IRepositorioLecturaTabla { /* SQL de PostgreSQL */ }

// AGREGAR case en index.php:
case 'postgresql':
    $repositorio = new RepositorioLecturaPostgreSQL($proveedorConexion);

// NO MODIFICAR: ServicioCrud.php, EntidadesController.php
```

### 2. Agregar ProcedimientosController

```php
// CREAR: IRepositorioConsultas.php, RepositorioConsultasMysqlMariaDB.php
// CREAR: ServicioConsultas.php, ProcedimientosController.php
// AGREGAR rutas en index.php: POST /api/procedimientos/ejecutarsp
```

### 3. Agregar autenticacion JWT

```bash
composer require firebase/php-jwt
```

```php
// CREAR: AutenticacionController.php (POST /api/auth/login)
// AGREGAR middleware JWT en index.php antes del despacho de rutas
```

### 4. Agregar DiagnosticoController

```php
// Ya existe: $repositorio->obtenerDiagnosticoConexionAsync()
// Solo CREAR controlador y agregar ruta: GET /api/diagnostico/conexion
```

### 5. Agregar EstructurasController

```php
// CREAR: GET /api/estructuras/tablas (INFORMATION_SCHEMA.TABLES)
// CREAR: GET /api/estructuras/columnas/{tabla} (INFORMATION_SCHEMA.COLUMNS)
```

---

## Solucion de Problemas Comunes

### 1. Error de conexion a MariaDB

**Sintoma**: `Connection refused` o `Access denied`

**Solucion**:
- Verificar que MariaDB/MySQL este corriendo (XAMPP Control Panel)
- Revisar `config/config.php`: host, port, database, username, password
- Probar conexion directa: `mysql -u root -e "SELECT 1;"`

### 2. Error "Class not found"

**Sintoma**: `Class 'ApiGenericaPhp\...' not found`

**Solucion**:
- Si usas Composer: ejecutar `composer dump-autoload`
- Si usas autoload manual: verificar que `vendor/autoload.php` existe
- Verificar que los namespaces coincidan con la estructura de directorios

### 3. Tabla no encontrada (404)

**Sintoma**: `{"estado":404, "mensaje":"El recurso solicitado no fue encontrado."}`

**Solucion**:
- Verificar que la BD existe: `mysql -u root -e "USE bdfacturas_mariadb_local; SHOW TABLES;"`
- Si no existe, ejecutar el script: `mysql -u root --default-character-set=utf8mb4 -e "source script_bd/bdfacturas_mariadb.sql"`

### 4. Puerto en uso

**Sintoma**: `Address already in use`

**Solucion**:
- Usar otro puerto: `php -S localhost:8001 -t public`
- O matar el proceso: `netstat -ano | findstr :8000`

### 5. "php" no se reconoce como comando (Windows)

**Sintoma**: `El termino 'php' no se reconoce como nombre de un cmdlet`

**Solucion**:
- Usar la ruta completa: `C:\xampp\php\php.exe -S localhost:8000 -t public`
- O agregar `C:\xampp\php` al PATH del sistema (Variables de entorno > Path > Nuevo)

### 6. JSON invalido en POST/PUT

**Sintoma**: `{"estado":400, "mensaje":"El body de la peticion no es JSON valido."}`

**Solucion**:
- Verificar que el header sea `Content-Type: application/json`
- Validar el JSON en jsonlint.com antes de enviarlo

---

## Para que sirve .htaccess

### El problema

Cuando usted escribe en el navegador:

```
http://localhost:8000/api/producto
```

El servidor web busca un **archivo fisico** llamado `/api/producto`. Ese archivo no existe — no hay una carpeta `api/` ni un archivo `producto`. Entonces el servidor devuelve **404 Not Found**.

Pero nosotros queremos que TODAS las URLs pasen por `index.php`, que es quien parsea la URL y llama al controlador correcto.

### Que hace .htaccess

Le dice a Apache: "Si el archivo no existe fisicamente, redirige todo a `index.php`":

```
http://localhost:8000/api/producto      -> index.php (parsea: tabla=producto)
http://localhost:8000/api/cliente/id/1  -> index.php (parsea: tabla=cliente, clave=id, valor=1)
http://localhost:8000/docs              -> docs.html (existe fisicamente, se sirve directo)
```

### Cuando se usa y cuando no

- Cuando usamos `php -S localhost:8000 -t public`, el `.htaccess` **no se usa**. El servidor PHP integrado ya envia todo a `index.php`.
- Cuando usamos **Apache (XAMPP)**, Apache necesita `.htaccess` para saber que debe redirigir todo a `index.php`.

---

## Uso con XAMPP

### 1. Habilitar mod_rewrite en Apache

Abrir `c:\xampp\apache\conf\httpd.conf` y buscar esta linea:

```apache
#LoadModule rewrite_module modules/mod_rewrite.so
```

Quitarle el `#` para que quede asi:

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

Luego buscar el bloque `<Directory "C:/xampp/htdocs">` y cambiar `AllowOverride None` a:

```apache
<Directory "C:/xampp/htdocs">
    AllowOverride All
</Directory>
```

Reiniciar Apache desde el XAMPP Control Panel (boton Stop y luego Start).

### 2. Acceder via navegador

```
http://localhost/ApiGenericaPhp/public/
http://localhost/ApiGenericaPhp/public/docs
http://localhost/ApiGenericaPhp/public/api/producto
```

---

## Conceptos de PHP usados en este proyecto

### Superglobales ($_SERVER, $_GET, $_POST, $_SESSION)

PHP tiene **arrays superglobales** que siempre existen y se llenan automaticamente antes de que su codigo se ejecute:

| Superglobal | Que contiene | Ejemplo |
|---|---|---|
| `$_SERVER` | Info del servidor y la peticion | `$_SERVER['REQUEST_METHOD']` -> `"GET"` |
| `$_GET` | Parametros del query string | `?limite=10` -> `$_GET['limite']` -> `"10"` |
| `$_POST` | Datos enviados por formulario | `$_POST['nombre']` -> `"Juan"` |
| `$_SESSION` | Datos de sesion del usuario | `$_SESSION['mensaje']` -> `"Registro creado"` |
| `$_COOKIE` | Cookies del navegador | `$_COOKIE['token']` |
| `$_FILES` | Archivos subidos | `$_FILES['foto']` |

### Operador ?? (null coalescing)

`??` significa: "si lo de la izquierda es null o no existe, usar lo de la derecha":

```php
$limite = $_GET['limite'] ?? null;          // Si no viene en la URL, null
$nombre = $registro['nombre'] ?? '';        // Si no existe la clave, string vacio
$puerto = $config['port'] ?? 3306;          // Si no esta configurado, 3306 por defecto
```

### require, include, use: diferencias

| Instruccion | Que hace | Si no encuentra el archivo |
|---|---|---|
| `include` | Carga y ejecuta un archivo PHP | Warning, pero sigue ejecutando |
| `include_once` | Igual, pero solo la primera vez | Warning, pero sigue |
| `require` | Carga y ejecuta un archivo PHP | **Fatal error**, detiene todo |
| `require_once` | Igual, pero solo la primera vez | **Fatal error**, detiene todo |

`use` NO carga archivos. Solo crea un **alias corto** para un namespace:

```php
// Sin use: nombre completo cada vez
$p = new ApiGenericaPhp\Conexion\ProveedorConexion($config);

// Con use: alias corto
use ApiGenericaPhp\Conexion\ProveedorConexion;
$p = new ProveedorConexion($config);
```

### Autoload PSR-4

En PHP, cada clase vive en su propio archivo. El autoloader convierte el namespace en ruta de archivo:

```
Namespace:  ApiGenericaPhp \ Conexion    \ ProveedorConexion
Archivo:    src            / Conexion    / ProveedorConexion.php
```

Esto se configura en `composer.json`:

```json
{
  "autoload": {
    "psr-4": {
      "ApiGenericaPhp\\": "src/"
    }
  }
}
```

Flujo completo:

```
1. require_once 'vendor/autoload.php'  -> Registra el autoloader PSR-4
2. use ... ProveedorConexion           -> Crea alias corto (no carga nada)
3. new ProveedorConexion()             -> El autoloader busca y carga src/Conexion/ProveedorConexion.php
```

---

## Comandos Utiles

```bash
# Iniciar servidor PHP (si PHP esta en el PATH)
php -S localhost:8000 -t public

# Iniciar servidor PHP (Windows/XAMPP, si PHP no esta en el PATH)
C:\xampp\php\php.exe -S localhost:8000 -t public

# Crear/recrear base de datos
mysql -u root --default-character-set=utf8mb4 -e "source script_bd/bdfacturas_mariadb.sql"

# Verificar conexion a BD
mysql -u root -e "USE bdfacturas_mariadb_local; SELECT COUNT(*) FROM producto;"

# Regenerar autoload (si tienes Composer)
composer dump-autoload

# Verificar version PHP y extensiones
php -v
php -m | grep pdo
```

---

## Licencia

Este proyecto es de uso educativo.
