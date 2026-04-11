# COMO EJECUTAR LA API (paso a paso)

```
╔══════════════════════════════════════════════════════════════════════════╗
║                                                                        ║
║   COMANDO PARA EJECUTAR LA API:                                        ║
║                                                                        ║
║   C:\xampp\php\php.exe -S localhost:8000 -t public                     ║
║                                                                        ║
║   (ejecutar desde la carpeta C:\xampp\htdocs\ApiGenericaPhp)           ║
║                                                                        ║
╚══════════════════════════════════════════════════════════════════════════╝
```

### Que significa cada parte

```
C:\xampp\php\php.exe       -> El ejecutable de PHP.
                              Se usa la ruta completa porque en Windows
                              "php" solo no funciona si no esta en el PATH.

-S localhost:8000          -> -S = Server mode.
                              Le dice a PHP: "levanta un servidor HTTP".
                              localhost = solo accesible desde esta PC.
                              8000 = el puerto donde escucha.

-t public                  -> -t = document root.
                              Le dice: "la carpeta raiz del sitio es public/".
                              Ahi esta index.php, que es el punto de entrada.
                              TODAS las peticiones pasan por ese archivo.
```

### Que deberias ver al ejecutarlo

```
PS C:\xampp\htdocs\ApiGenericaPhp> C:\xampp\php\php.exe -S localhost:8000 -t public
[Fri Apr 10 18:07:32 2026] PHP 8.0.30 Development Server (http://localhost:8000) started
_
```

El cursor se queda esperando. **Eso es normal**, no se colgo. El servidor esta escuchando peticiones. Cuando alguien haga un request vas a ver lineas como:

```
[18:07:45] 127.0.0.1:52341 [200]: GET /api/producto
[18:07:46] 127.0.0.1:52342 [404]: GET /api/tabla_que_no_existe
```

### Por que el puerto 8000 y no el 80

```
Puerto 80   = lo usa Apache (XAMPP) para servir el frontend (paginas PHP con HTML)
Puerto 8000 = lo usa el servidor PHP built-in para servir la API (respuestas JSON)

Son dos servidores distintos corriendo al mismo tiempo en la misma PC.
No se pueden usar el mismo puerto.
```

### Por que -t public y no -t . (la raiz)

```
ApiGenericaPhp/
├── public/              <--- ACA apunta -t public
│   ├── index.php        <--- Este archivo recibe TODAS las peticiones
│   ├── docs.html        <--- Swagger UI
│   └── openapi.json     <--- Especificacion OpenAPI
├── src/                 <--- Codigo fuente (NO accesible desde el navegador)
├── config/              <--- Configuracion (NO accesible desde el navegador)
└── ...

Si usaras -t . (la raiz), cualquiera podria acceder a config/config.php
desde el navegador y ver tu usuario/password de la base de datos.

Con -t public, solo se expone la carpeta public/. El resto queda protegido.
```

---

```
╔══════════════════════════════════════════════════════════════════════════╗
║                                                                        ║
║   La API necesita 2 cosas prendidas:                                   ║
║                                                                        ║
║       1. MariaDB/MySQL   (la base de datos)     -> XAMPP               ║
║       2. PHP Server      (la API en si)          -> Terminal            ║
║                                                                        ║
╚══════════════════════════════════════════════════════════════════════════╝
```

---

## Que es esta API

Es un servidor HTTP que recibe peticiones REST y devuelve JSON. No tiene interfaz visual (no tiene HTML). Se usa desde otro programa: un frontend, Postman, cURL, o Swagger UI.

```
   Alguien pide:                            La API responde:

   GET localhost:8000/api/producto    --->   { "datos": [...productos...] }
   POST localhost:8000/api/cliente    --->   { "mensaje": "Registro creado" }
   DELETE localhost:8000/api/rol/id/5 --->   { "mensaje": "Registro eliminado" }
```

---

## PASO 0: Solo la primera vez (crear la base de datos)

### 0.1 Prender MySQL desde XAMPP

Abrir el **XAMPP Control Panel** y hacer clic en **Start** en la fila de **MySQL**.

```
┌──────────────────────────────────────────────────┐
│  XAMPP Control Panel                             │
│                                                  │
│  Module        │  Actions                        │
│  ──────────────────────────────                  │
│  Apache        │  [Start]                        │
│  MySQL         │  [Start]  ◄── hacer clic aca    │
│  FileZilla     │  [Start]                        │
│                                                  │
│  Cuando MySQL se ponga VERDE = esta prendido     │
└──────────────────────────────────────────────────┘
```

### 0.2 Ejecutar el script SQL

Abrir una terminal (CMD, PowerShell, o Git Bash) y ejecutar:

```bash
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source C:/xampp/htdocs/ApiGenericaPhp/script_bd/bdfacturas_mariadb.sql"
```

Esto crea TODO:

```
┌────────────────────────────────────────────────┐
│  Base de datos: bdfacturas_mariadb_local       │
│                                                │
│  12 tablas:                                    │
│    empresa, persona, producto, cliente,        │
│    vendedor, factura, productosporfactura,     │
│    usuario, rol, rol_usuario, ruta, rutarol    │
│                                                │
│  5 triggers:                                   │
│    Calculan subtotales, manejan stock,         │
│    recalculan totales de factura               │
│                                                │
│  5 stored procedures:                          │
│    Operaciones maestro-detalle de facturacion  │
│                                                │
│  Datos de ejemplo:                             │
│    Productos, personas, empresas, etc.         │
└────────────────────────────────────────────────┘
```

### 0.3 Verificar

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "USE bdfacturas_mariadb_local; SHOW TABLES;"
```

Si ves 12 tablas, esta listo. Este paso NO se repite (a menos que quieras recrear la BD).

---

## PASO 1: Prender MySQL

```
╔══════════════════════════════════════════════════╗
║  XAMPP Control Panel -> MySQL -> [Start]         ║
║                                                  ║
║  Debe ponerse VERDE y mostrar puerto 3306        ║
╚══════════════════════════════════════════════════╝
```

---

## PASO 2: Prender la API

Ejecutar el comando explicado al inicio de este documento:

```bash
cd C:\xampp\htdocs\ApiGenericaPhp
C:\xampp\php\php.exe -S localhost:8000 -t public
```

La terminal debe quedar abierta (no cerrarla). Ver la seccion inicial para la explicacion completa del comando.

---

## PASO 3: Verificar que funciona

### Opcion A: En el navegador

```
http://localhost:8000/
```

Deberia mostrar un mensaje de bienvenida.

```
http://localhost:8000/api/producto
```

Deberia mostrar un JSON con productos:

```json
{
  "tabla": "producto",
  "total": 8,
  "datos": [
    { "codigo": "PR001", "nombre": "Teclado Mecanico", "stock": 50, "valorunitario": 89990 },
    { "codigo": "PR002", "nombre": "Mouse Gamer", "stock": 30, "valorunitario": 59990 },
    ...
  ]
}
```

### Opcion B: Swagger UI (documentacion interactiva)

```
http://localhost:8000/docs
```

Abre una interfaz visual donde podes probar TODOS los endpoints sin escribir codigo.

### Opcion C: Desde la terminal (cURL)

```bash
curl http://localhost:8000/api/producto
```

---

## Resumen visual

```
┌─────────────────────────────────────────────────────────────────────┐
│                         ORDEN DE ENCENDIDO                         │
│                                                                     │
│         PASO 1                    PASO 2                            │
│                                                                     │
│    ┌──────────────┐         ┌──────────────────┐                   │
│    │   MySQL      │         │   Terminal        │                   │
│    │              │         │                    │                   │
│    │  XAMPP       │   --->  │  cd ApiGenericaPhp │                  │
│    │  [Start]     │         │  php -S :8000      │                  │
│    │              │         │  -t public         │                  │
│    │  puerto 3306 │         │  puerto 8000       │                  │
│    └──────────────┘         └──────────────────┘                   │
│                                                                     │
│    Base de datos              Servidor de la API                    │
│                                                                     │
│                                                                     │
│         PASO 3: Verificar                                           │
│                                                                     │
│    ┌───────────────────────────────────────────┐                   │
│    │  Navegador: localhost:8000/api/producto   │                   │
│    │  Swagger:   localhost:8000/docs           │                   │
│    │  cURL:      curl localhost:8000/api/...   │                   │
│    └───────────────────────────────────────────┘                   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Todos los endpoints disponibles

Una vez que la API esta corriendo, estos son los endpoints:

```
┌──────────┬──────────────────────────────────────────┬──────────────────────────┐
│  Metodo  │  URL                                     │  Que hace                │
├──────────┼──────────────────────────────────────────┼──────────────────────────┤
│  GET     │  /                                       │  Bienvenida              │
│  GET     │  /docs                                   │  Swagger UI              │
│  GET     │  /api/info                               │  Info del controlador    │
├──────────┼──────────────────────────────────────────┼──────────────────────────┤
│  GET     │  /api/{tabla}                            │  Listar registros        │
│  GET     │  /api/{tabla}?limite=50                  │  Listar con limite       │
│  GET     │  /api/{tabla}/{clave}/{valor}            │  Obtener por clave       │
│  POST    │  /api/{tabla}                            │  Crear registro          │
│  PUT     │  /api/{tabla}/{clave}/{valor}            │  Actualizar registro     │
│  DELETE  │  /api/{tabla}/{clave}/{valor}            │  Eliminar registro       │
├──────────┼──────────────────────────────────────────┼──────────────────────────┤
│  POST    │  /api/{tabla}?camposEncriptar=contrasena │  Crear con BCrypt        │
│  POST    │  /api/{tabla}/verificar-contrasena       │  Verificar credenciales  │
└──────────┴──────────────────────────────────────────┴──────────────────────────┘

{tabla} = cualquier tabla de la BD: producto, cliente, vendedor, factura, etc.
{clave} = nombre de la columna: codigo, id, numero, email, etc.
{valor} = valor a buscar: PR001, 1, admin@correo.com, etc.
```

### Ejemplos rapidos

```bash
# Listar productos
curl http://localhost:8000/api/producto

# Obtener producto por codigo
curl http://localhost:8000/api/producto/codigo/PR001

# Crear producto
curl -X POST http://localhost:8000/api/producto \
  -H "Content-Type: application/json" \
  -d "{\"codigo\":\"TEST\",\"nombre\":\"Prueba\",\"stock\":10,\"valorunitario\":9990}"

# Actualizar producto
curl -X PUT http://localhost:8000/api/producto/codigo/TEST \
  -H "Content-Type: application/json" \
  -d "{\"stock\":99}"

# Eliminar producto
curl -X DELETE http://localhost:8000/api/producto/codigo/TEST
```

---

## Configuracion (config/config.php)

```php
return [
    'DatabaseProvider' => 'MariaDB',          // Motor de BD activo

    'ConnectionStrings' => [
        'MariaDB' => [
            'host'     => 'localhost',         // Servidor de BD
            'port'     => 3306,                // Puerto de BD
            'database' => 'bdfacturas_mariadb_local',  // Nombre de la BD
            'username' => 'root',              // Usuario
            'password' => '',                  // Contrasena (vacia en XAMPP)
            'charset'  => 'utf8mb4',           // Codificacion
        ],
    ],

    'TablasProhibidas' => [],                  // Tablas que la API NO puede acceder

    'Cors' => [
        'AllowedOrigins' => '*',               // Quien puede llamar a la API
        'AllowedMethods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'AllowedHeaders' => 'Content-Type, Authorization',
    ],
];
```

---

## Troubleshooting

```
┌──────────────────────────────────────────────────────────────────────┐
│  PROBLEMA                          │  SOLUCION                      │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  "php" no se reconoce              │  Usar ruta completa:           │
│                                    │  C:\xampp\php\php.exe          │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Address already in use            │  Ya hay algo en el puerto 8000 │
│  (puerto 8000 ocupado)             │  Cerrar esa terminal, o usar:  │
│                                    │  php -S localhost:8001 -t public│
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Connection refused                │  MySQL no esta prendido        │
│  (al pedir /api/producto)          │  XAMPP -> MySQL -> Start       │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Access denied for user            │  Password incorrecto en        │
│                                    │  config/config.php             │
│                                    │  En XAMPP default: root, sin   │
│                                    │  password                      │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Table doesn't exist               │  La BD no se creo.             │
│                                    │  Ejecutar el script del Paso 0 │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Class not found                   │  Ejecutar desde la carpeta     │
│                                    │  ApiGenericaPhp (no otra).     │
│                                    │  O correr: composer dump-autoload
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  La API devuelve HTML de XAMPP     │  Estas accediendo por Apache   │
│  en vez de JSON                    │  (puerto 80), no por el server │
│                                    │  PHP (puerto 8000).            │
│                                    │  Usar: localhost:8000          │
│                                    │                                │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Apagar la API

```
Ctrl+C en la terminal donde corre el servidor PHP
```

O simplemente cerrar esa terminal.
