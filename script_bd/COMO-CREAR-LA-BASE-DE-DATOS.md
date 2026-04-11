# COMO CREAR LA BASE DE DATOS

Hay dos formas de hacerlo. Elegir la que prefieras:

```
╔══════════════════════════════════════════════════╗
║                                                  ║
║   FORMA 1: Desde XAMPP (visual, con clicks)      ║
║   FORMA 2: Desde la terminal (un comando)        ║
║                                                  ║
║   Las dos hacen EXACTAMENTE lo mismo.            ║
║   Elegir la que te resulte mas comoda.           ║
║                                                  ║
╚══════════════════════════════════════════════════╝
```

Antes de cualquiera de las dos: **MySQL debe estar prendido**.

---

## Requisito previo: Prender MySQL

Abrir **XAMPP Control Panel** y hacer clic en **Start** en la fila de **MySQL**:

```
┌──────────────────────────────────────────────────────────────────┐
│  XAMPP Control Panel v3.3.0                                      │
│                                                                  │
│  Service  Module     PID(s)       Port(s)    Actions             │
│  ─────────────────────────────────────────────────────────────── │
│           Apache     60928,33728  80, 443    [Stop] Admin Config │
│           MySQL      40120        3306       [Stop] Admin Config │
│           FileZilla                          [Start]             │
│           Mercury                            [Start]             │
│           Tomcat                             [Start]             │
│                                                                  │
│  ◄── Apache y MySQL deben estar en VERDE (corriendo).            │
│      Si dicen [Start], hacer clic para prenderlos.               │
│      Si dicen [Stop], ya estan prendidos.                        │
└──────────────────────────────────────────────────────────────────┘
```

Cuando MySQL muestre un PID y el puerto **3306**, esta listo.

---

---

## FORMA 1: Desde XAMPP (phpMyAdmin) - visual, con clicks

phpMyAdmin es una herramienta web que viene con XAMPP para administrar la base de datos desde el navegador.

### Paso 1: Abrir phpMyAdmin

Con Apache y MySQL prendidos, abrir en el navegador:

```
http://localhost/phpmyadmin
```

O desde XAMPP Control Panel: clic en el boton **Admin** de la fila MySQL.

### Paso 2: Ir a la pestana "Importar"

```
┌──────────────────────────────────────────────────────────────────┐
│  phpMyAdmin                                                      │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐    │
│  │  Bases de datos | SQL | Estado | Cuentas de usuario |    │    │
│  │  Exportar | [Importar] | Configuracion | Replicacion   │    │
│  │              ▲                                           │    │
│  │              │                                           │    │
│  │         CLIC ACA                                        │    │
│  └──────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

**IMPORTANTE**: NO seleccionar ninguna base de datos antes de importar. El script ya incluye `CREATE DATABASE` y `USE`, asi que crea todo solo.

### Paso 3: Seleccionar el archivo

```
┌──────────────────────────────────────────────────────────────────┐
│  Importar                                                        │
│                                                                  │
│  Archivo a importar:                                             │
│  ┌─────────────────────────────────────────────────────────┐     │
│  │  [Seleccionar archivo]  ◄── clic aca                   │     │
│  └─────────────────────────────────────────────────────────┘     │
│                                                                  │
│  Navegar hasta:                                                  │
│  C:\xampp\htdocs\ApiGenericaPhp\script_bd\                      │
│                                                                  │
│  Seleccionar:                                                    │
│  bdfacturas_mariadb.sql                                          │
│                                                                  │
│  Juego de caracteres del archivo:                                │
│  [utf-8]  ◄── dejarlo en utf-8                                  │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Paso 4: Ejecutar la importacion

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                  │
│  Formato:                                                        │
│  [SQL]  ◄── dejarlo en SQL (ya viene seleccionado)              │
│                                                                  │
│                        [Importar]  ◄── clic aca                 │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Paso 5: Verificar

Si todo salio bien, phpMyAdmin muestra un mensaje verde:

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                  │
│  ✓ La importacion se ejecuto exitosamente,                      │
│    se ejecutaron XX consultas.                                   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

Y en el panel izquierdo aparece la base de datos **bdfacturas_mariadb_local** con 12 tablas:

```
┌─────────────────────────┐
│  bdfacturas_mariadb_    │
│  local                  │
│  ├── cliente            │
│  ├── empresa            │
│  ├── factura            │
│  ├── persona            │
│  ├── producto           │
│  ├── productosporfactura│
│  ├── rol                │
│  ├── rol_usuario        │
│  ├── ruta               │
│  ├── rutarol            │
│  ├── usuario            │
│  └── vendedor           │
└─────────────────────────┘
```

12 tablas = todo bien.

---

---

## FORMA 2: Desde la terminal (un comando)

### Paso 1: Abrir una terminal

Abrir **PowerShell**, **CMD**, o **Git Bash**. Cualquiera sirve.

```
Como abrir PowerShell:
  -> Clic derecho en el boton de Windows
  -> "Terminal" o "Windows PowerShell"

Como abrir CMD:
  -> Tecla Windows + R
  -> Escribir "cmd"
  -> Enter
```

### Paso 2: Copiar y pegar este comando

```bash
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source C:/xampp/htdocs/ApiGenericaPhp/script_bd/bdfacturas_mariadb.sql"
```

### Que significa cada parte del comando

```
C:\xampp\mysql\bin\mysql.exe
    El programa cliente de MySQL/MariaDB que viene con XAMPP.
    Es el que se conecta al motor de base de datos y le envia instrucciones.

-u root
    -u = usuario.
    "root" es el usuario administrador.
    En XAMPP viene sin contrasena por defecto.

--default-character-set=utf8mb4
    Le dice que use codificacion UTF-8.
    Sin esto, los acentos (Perez, Gomez, Diaz) pueden quedar mal.

-e "source C:/.../bdfacturas_mariadb.sql"
    -e = ejecutar.
    "source" = leer y ejecutar un archivo SQL.
    Le pasamos la ruta completa al archivo .sql
```

### Que deberias ver

```
┌──────────────────────────────────────────────────────────────────────┐
│                                                                      │
│  PS C:\Users\tu_usuario>                                             │
│  C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=...    │
│                                                                      │
│  PS C:\Users\tu_usuario> _                                           │
│                                                                      │
│  ◄── Si no muestra NINGUN error, funciono.                           │
│      MySQL no dice "todo bien", simplemente no dice nada.            │
│      Silencio = exito.                                               │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

### Paso 3: Verificar que se creo

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "USE bdfacturas_mariadb_local; SHOW TABLES;"
```

Deberia mostrar:

```
+--------------------------------------+
| Tables_in_bdfacturas_mariadb_local   |
+--------------------------------------+
| cliente                              |
| empresa                              |
| factura                              |
| persona                              |
| producto                             |
| productosporfactura                  |
| rol                                  |
| rol_usuario                          |
| ruta                                 |
| rutarol                              |
| usuario                              |
| vendedor                             |
+--------------------------------------+
```

12 tablas = todo bien.

### Paso 4 (opcional): Verificar que hay datos

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "USE bdfacturas_mariadb_local; SELECT codigo, nombre, stock, valorunitario FROM producto;"
```

Deberia mostrar 8 productos:

```
+-------+----------------------------+-------+----------------+
| codigo| nombre                     | stock | valorunitario  |
+-------+----------------------------+-------+----------------+
| PR001 | Laptop Lenovo IdeaPad      |    15 |     2500000.00 |
| PR002 | Monitor Samsung 24"        |    26 |      800000.00 |
| PR003 | Teclado Logitech K380      |    39 |      150000.00 |
| ...   | ...                        |   ... |           ...  |
+-------+----------------------------+-------+----------------+
```

(Los stocks son menores a los originales porque los triggers ya descontaron lo de las facturas de ejemplo.)

---

---

## Que es este archivo (bdfacturas_mariadb.sql)

Es un archivo de texto con instrucciones SQL. Cuando lo ejecutas, le dice a MariaDB/MySQL:

```
1. Crear la base de datos "bdfacturas_mariadb_local"
2. Crear 12 tablas dentro de esa base de datos
3. Crear 5 triggers (acciones automaticas)
4. Crear 5 stored procedures (procedimientos almacenados)
5. Insertar datos de ejemplo (productos, personas, facturas, etc.)
```

Es como un plano de construccion: le das el plano a MariaDB y ella construye todo.

### Que se crea

```
┌──────────────────────────────────────────────────────────────────┐
│  Base de datos: bdfacturas_mariadb_local                        │
│                                                                  │
│  12 TABLAS:                                                      │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  empresa        Empresas                                  │  │
│  │  persona        Personas (nombre, email, telefono)        │  │
│  │  producto       Productos con stock y precio              │  │
│  │  cliente        Clientes (referencia a persona y empresa) │  │
│  │  vendedor       Vendedores (referencia a persona)         │  │
│  │  factura        Facturas (cliente + vendedor + total)     │  │
│  │  productosporfactura  Detalle: que productos tiene cada   │  │
│  │                       factura y cuantos                   │  │
│  │  usuario        Usuarios (email + contrasena BCrypt)      │  │
│  │  rol            Roles (Administrador, Vendedor, etc.)     │  │
│  │  rol_usuario    Que rol tiene cada usuario                │  │
│  │  ruta           Rutas del sistema (/home, /facturas...)   │  │
│  │  rutarol        Que rol puede acceder a que ruta          │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  5 TRIGGERS (se ejecutan solos, automaticamente):                │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  Cuando insertas un producto en una factura:              │  │
│  │    -> Calcula el subtotal (cantidad x precio)             │  │
│  │    -> Descuenta el stock del producto                     │  │
│  │    -> Recalcula el total de la factura                    │  │
│  │                                                           │  │
│  │  Cuando actualizas un producto en una factura:            │  │
│  │    -> Devuelve el stock viejo, descuenta el nuevo         │  │
│  │    -> Recalcula subtotal y total                          │  │
│  │                                                           │  │
│  │  Cuando eliminas un producto de una factura:              │  │
│  │    -> Restaura el stock del producto                      │  │
│  │    -> Recalcula el total de la factura                    │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  5 STORED PROCEDURES (se llaman cuando los necesitas):           │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  sp_insertar_factura_y_productosporfactura                │  │
│  │    -> Crea una factura con todos sus productos de una vez │  │
│  │                                                           │  │
│  │  sp_consultar_factura_y_productosporfactura               │  │
│  │    -> Consulta una factura con todo su detalle            │  │
│  │                                                           │  │
│  │  sp_listar_facturas_y_productosporfactura                 │  │
│  │    -> Lista todas las facturas con sus productos          │  │
│  │                                                           │  │
│  │  sp_actualizar_factura_y_productosporfactura              │  │
│  │    -> Reemplaza los productos de una factura              │  │
│  │                                                           │  │
│  │  sp_borrar_factura_y_productosporfactura                  │  │
│  │    -> Elimina una factura y restaura el stock             │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
│  DATOS DE EJEMPLO:                                               │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  3 empresas, 6 personas, 8 productos, 5 clientes,        │  │
│  │  3 vendedores, 3 facturas con productos, 6 usuarios,     │  │
│  │  5 roles, 15 rutas, permisos de ejemplo                   │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## Puedo ejecutar el script mas de una vez?

**Si.** El script esta preparado para eso. Hace lo siguiente al inicio:

```
1. DROP TABLE IF EXISTS ... (borra las tablas si ya existen)
2. DROP TRIGGER IF EXISTS ... (borra los triggers si ya existen)
3. DROP PROCEDURE IF EXISTS ... (borra los SPs si ya existen)
4. CREATE TABLE ... (crea todo de cero)
5. INSERT INTO ... (inserta datos de ejemplo)
```

Ejecutarlo de nuevo **borra todo y lo recrea desde cero**. Es util si querias "resetear" la BD a su estado original despues de hacer pruebas.

```
╔══════════════════════════════════════════════════════════════════════════╗
║                                                                        ║
║   ATENCION: Si ya tenias datos propios (facturas que creaste,          ║
║   productos que agregaste), ejecutar el script de nuevo los BORRA.     ║
║   Solo lo recrea con los datos de ejemplo.                             ║
║                                                                        ║
╚══════════════════════════════════════════════════════════════════════════╝
```

---

## Troubleshooting

```
┌──────────────────────────────────────────────────────────────────────┐
│  PROBLEMA                          │  SOLUCION                      │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  "mysql" no se reconoce            │  Usar la ruta completa:        │
│                                    │  C:\xampp\mysql\bin\mysql.exe  │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Can't connect to MySQL server     │  MySQL no esta prendido.       │
│                                    │  XAMPP -> MySQL -> Start       │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Access denied for user 'root'     │  XAMPP por defecto no tiene    │
│                                    │  password. Si le pusiste una,  │
│                                    │  agregar: -p                   │
│                                    │  C:\xampp\mysql\bin\mysql.exe  │
│                                    │  -u root -p -e "source ..."   │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Failed to open file               │  La ruta al .sql esta mal.    │
│  (No such file or directory)       │  Verificar que el archivo     │
│                                    │  existe en esa ubicacion.     │
│                                    │  Usar barras / no \           │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Acentos se ven mal                │  Falta --default-character-   │
│  (PÃ©rez en vez de Perez)          │  set=utf8mb4 en el comando    │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  Puerto 3306 en uso                │  Otro MySQL ya esta corriendo.│
│  (MySQL no prende en XAMPP)        │  Cerrar ese servicio primero. │
│                                    │  Panel de control > Servicios │
│                                    │  > MySQL > Detener            │
│                                    │                                │
│──────────────────────────────────────────────────────────────────────│
│                                    │                                │
│  phpMyAdmin no abre                │  Apache no esta prendido.     │
│  (localhost/phpmyadmin no carga)   │  phpMyAdmin necesita Apache   │
│                                    │  para funcionar.              │
│                                    │  XAMPP -> Apache -> Start     │
│                                    │                                │
└──────────────────────────────────────────────────────────────────────┘
```
