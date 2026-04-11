<?php
// ============================================================================
// ServicioCrud.php — Logica de negocio (la capa intermedia entre controlador y repositorio)
// Ubicacion: src/Servicios/ServicioCrud.php
//
// PARA QUE SIRVE ESTE ARCHIVO:
//   Es el intermediario entre el controlador (que recibe HTTP) y el
//   repositorio (que ejecuta SQL). Su trabajo es:
//
//     1. VALIDAR:    ¿Los parametros estan bien? ¿No estan vacios?
//     2. VERIFICAR:  ¿La tabla esta permitida? (politica de tablas prohibidas)
//     3. NORMALIZAR: Limpiar espacios, poner valores por defecto
//     4. DELEGAR:    Pasarle el trabajo al repositorio
//
//   El servicio NO sabe de HTTP (eso es del controlador).
//   El servicio NO sabe de SQL (eso es del repositorio).
//   Solo aplica reglas de negocio.
//
//   Analogia del restaurante:
//     Controlador = mesero (habla con el cliente, anota el pedido)
//     Servicio    = chef (verifica ingredientes, aplica receta, delega al ayudante)
//     Repositorio = ayudante de cocina (saca ingredientes de la bodega)
//
// CUANDO SE USA EN EL FLUJO:
//   1. Llega: GET /api/producto
//   2. index.php lo envia al controlador: $controlador->listarAsync('producto',...)
//   3. El controlador llama al servicio: $this->servicio->listarAsync('producto',...)
//      ^^^^ ACA entra esta clase
//   4. El servicio valida, verifica permisos, y delega al repositorio
//   5. El repositorio ejecuta el SQL y devuelve los datos
//   6. El servicio retorna los datos al controlador
//   7. El controlador arma el JSON y lo envia al navegador
// ============================================================================

namespace ApiGenericaPhp\Servicios;

use ApiGenericaPhp\Servicios\Abstracciones\IServicioCrud;                // Interface que implementamos
use ApiGenericaPhp\Repositorios\Abstracciones\IRepositorioLecturaTabla;  // Para acceder a la BD
use ApiGenericaPhp\Servicios\Abstracciones\IPoliticaTablasProhibidas;    // Para verificar permisos

class ServicioCrud implements IServicioCrud
{
    // Las dos dependencias que esta clase necesita para trabajar:
    private IRepositorioLecturaTabla $repositorioLectura;       // Para leer/escribir en la BD
    private IPoliticaTablasProhibidas $politicaTablasProhibidas; // Para verificar si la tabla esta permitida

    // Constructor: recibe las dos dependencias y las guarda.
    // NOTA: recibe INTERFACES, no clases concretas. Esto significa que no le importa
    // si el repositorio es MySQL, PostgreSQL, o un mock para tests.
    // Solo le importa que tenga los metodos definidos en la interface.
    public function __construct(
        IRepositorioLecturaTabla $repositorioLectura,
        IPoliticaTablasProhibidas $politicaTablasProhibidas
    ) {
        $this->repositorioLectura = $repositorioLectura;
        $this->politicaTablasProhibidas = $politicaTablasProhibidas;
    }

    // =======================================================================
    // listarAsync — Listar registros de una tabla
    // =======================================================================
    // Ejemplo: listarAsync('producto', null, 10)
    //
    // Flujo interno:
    //   1. Validar que 'producto' no este vacio
    //   2. Verificar que 'producto' no este en la lista de tablas prohibidas
    //   3. Normalizar parametros (limpiar espacios, valores por defecto)
    //   4. Llamar al repositorio: $this->repositorioLectura->obtenerFilasAsync(...)
    //   5. Retornar el resultado al controlador
    public function listarAsync(string $nombreTabla, ?string $esquema, ?int $limite): array
    {
        // --- FASE 1: VALIDAR ---
        // Verificar que el nombre de tabla no este vacio ni sea solo espacios
        if (empty(trim($nombreTabla))) {
            // throw = lanzar un error. InvalidArgumentException = "el argumento que me pasaron es invalido"
            // El controlador va a atrapar este error y devolver un JSON con el mensaje
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }

        // --- FASE 2: VERIFICAR PERMISOS ---
        // Preguntar a la politica: ¿esta tabla esta permitida?
        // Si devuelve false, lanzar error de acceso denegado
        if (!$this->politicaTablasProhibidas->esTablaPermitida($nombreTabla)) {
            // RuntimeException = "algo fallo en tiempo de ejecucion" (no es un bug, es una restriccion)
            throw new \RuntimeException(
                "Acceso denegado: La tabla '{$nombreTabla}' esta restringida y no puede ser consultada. " .
                "Verifique los permisos de acceso o contacte al administrador del sistema."
            );
        }

        // --- FASE 3: NORMALIZAR ---
        // Limpiar el esquema: si esta vacio o es solo espacios, ponerlo en null
        $esquemaNormalizado = (empty($esquema) || empty(trim($esquema))) ? null : trim($esquema);

        // Limpiar el limite: si es null o negativo, ponerlo en null (el repositorio usara 1000)
        $limiteNormalizado = ($limite === null || $limite <= 0) ? null : $limite;

        // --- FASE 4: DELEGAR AL REPOSITORIO ---
        // El repositorio es el que realmente ejecuta el SQL.
        // $this->repositorioLectura es la interface IRepositorioLecturaTabla.
        // Atras de esa interface esta RepositorioLecturaMysqlMariaDB (o cualquier otra implementacion).
        return $this->repositorioLectura->obtenerFilasAsync($nombreTabla, $esquemaNormalizado, $limiteNormalizado);
    }

    // =======================================================================
    // obtenerPorClaveAsync — Buscar por una columna especifica
    // =======================================================================
    // Ejemplo: obtenerPorClaveAsync('producto', null, 'codigo', 'PR001')
    // Misma estructura: validar -> verificar permisos -> normalizar -> delegar
    public function obtenerPorClaveAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valor): array
    {
        // Validar que ningun parametro obligatorio este vacio
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($nombreClave))) {
            throw new \InvalidArgumentException("El nombre de la clave no puede estar vacio.");
        }
        if (empty(trim($valor))) {
            throw new \InvalidArgumentException("El valor no puede estar vacio.");
        }

        // Verificar permisos
        if (!$this->politicaTablasProhibidas->esTablaPermitida($nombreTabla)) {
            throw new \RuntimeException(
                "Acceso denegado: La tabla '{$nombreTabla}' esta restringida y no puede ser consultada."
            );
        }

        // Normalizar y delegar
        $esquemaNormalizado = (empty($esquema) || empty(trim($esquema))) ? null : trim($esquema);
        return $this->repositorioLectura->obtenerPorClaveAsync($nombreTabla, $esquemaNormalizado, trim($nombreClave), trim($valor));
    }

    // =======================================================================
    // crearAsync — Crear un registro nuevo
    // =======================================================================
    // Ejemplo: crearAsync('producto', null, ['codigo'=>'PR099','nombre'=>'Test',...])
    // Mismo patron: validar -> verificar permisos -> normalizar -> delegar
    public function crearAsync(string $nombreTabla, ?string $esquema, array $datos, ?string $camposEncriptar = null): bool
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty($datos)) {
            throw new \InvalidArgumentException("Los datos no pueden estar vacios.");
        }

        if (!$this->politicaTablasProhibidas->esTablaPermitida($nombreTabla)) {
            throw new \RuntimeException(
                "Acceso denegado: La tabla '{$nombreTabla}' esta restringida y no puede ser modificada."
            );
        }

        $esquemaNormalizado = (empty($esquema) || empty(trim($esquema))) ? null : trim($esquema);
        $camposNormalizados = (empty($camposEncriptar) || empty(trim($camposEncriptar))) ? null : trim($camposEncriptar);

        // Delegar al repositorio. Si $camposNormalizados tiene valor (ej: 'contrasena'),
        // el repositorio los hasheara con BCrypt antes de guardarlos.
        return $this->repositorioLectura->crearAsync($nombreTabla, $esquemaNormalizado, $datos, $camposNormalizados);
    }

    // =======================================================================
    // actualizarAsync — Actualizar un registro existente
    // =======================================================================
    // Ejemplo: actualizarAsync('producto', null, 'codigo', 'PR001', ['stock'=>99])
    public function actualizarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave, array $datos, ?string $camposEncriptar = null): int
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($nombreClave))) {
            throw new \InvalidArgumentException("El nombre de la clave no puede estar vacio.");
        }
        if (empty(trim($valorClave))) {
            throw new \InvalidArgumentException("El valor de la clave no puede estar vacio.");
        }
        if (empty($datos)) {
            throw new \InvalidArgumentException("Los datos a actualizar no pueden estar vacios.");
        }

        if (!$this->politicaTablasProhibidas->esTablaPermitida($nombreTabla)) {
            throw new \RuntimeException(
                "Acceso denegado: La tabla '{$nombreTabla}' esta restringida y no puede ser modificada."
            );
        }

        $esquemaNormalizado = (empty($esquema) || empty(trim($esquema))) ? null : trim($esquema);
        $camposNormalizados = (empty($camposEncriptar) || empty(trim($camposEncriptar))) ? null : trim($camposEncriptar);

        return $this->repositorioLectura->actualizarAsync(
            $nombreTabla, $esquemaNormalizado, trim($nombreClave), trim($valorClave), $datos, $camposNormalizados
        );
    }

    // =======================================================================
    // eliminarAsync — Eliminar un registro
    // =======================================================================
    // Ejemplo: eliminarAsync('producto', null, 'codigo', 'PR001')
    public function eliminarAsync(string $nombreTabla, ?string $esquema, string $nombreClave, string $valorClave): int
    {
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($nombreClave))) {
            throw new \InvalidArgumentException("El nombre de la clave no puede estar vacio.");
        }
        if (empty(trim($valorClave))) {
            throw new \InvalidArgumentException("El valor de la clave no puede estar vacio.");
        }

        if (!$this->politicaTablasProhibidas->esTablaPermitida($nombreTabla)) {
            throw new \RuntimeException(
                "Acceso denegado: La tabla '{$nombreTabla}' esta restringida y no puede ser modificada."
            );
        }

        $esquemaNormalizado = (empty($esquema) || empty(trim($esquema))) ? null : trim($esquema);

        return $this->repositorioLectura->eliminarAsync($nombreTabla, $esquemaNormalizado, trim($nombreClave), trim($valorClave));
    }

    // =======================================================================
    // verificarContrasenaAsync — Verificar login (email + contrasena)
    // =======================================================================
    // Ejemplo: verificarContrasenaAsync('usuario', null, 'email', 'contrasena', 'admin@correo.com', 'admin123')
    //
    // Flujo:
    //   1. Validar que todos los campos esten completos
    //   2. Pedir al repositorio el hash almacenado en la BD para ese email
    //   3. Si no existe el email -> devolver 404 "Usuario no encontrado"
    //   4. Si existe, comparar la contrasena en texto plano con el hash BCrypt
    //      usando password_verify():
    //        password_verify('admin123', '$2y$10$7Fl0IeW...')
    //        Esto internamente hashea 'admin123' y lo compara con el hash guardado
    //   5. Si coinciden -> devolver 200 "Credenciales validas"
    //   6. Si no coinciden -> devolver 401 "Contrasena incorrecta"
    //
    // NOTA: password_verify() es la unica forma segura de comparar contrasenas BCrypt.
    //   NO se puede comparar con == porque el hash es diferente cada vez que se genera.
    //   password_hash('admin123') -> '$2y$10$ABC...' (un hash)
    //   password_hash('admin123') -> '$2y$10$XYZ...' (OTRO hash diferente)
    //   Pero password_verify('admin123', '$2y$10$ABC...') -> true (las dos veces)
    public function verificarContrasenaAsync(string $nombreTabla, ?string $esquema, string $campoUsuario, string $campoContrasena, string $valorUsuario, string $valorContrasena): array
    {
        // Validar todos los campos obligatorios
        if (empty(trim($nombreTabla))) {
            throw new \InvalidArgumentException("El nombre de la tabla no puede estar vacio.");
        }
        if (empty(trim($campoUsuario))) {
            throw new \InvalidArgumentException("El campo de usuario no puede estar vacio.");
        }
        if (empty(trim($campoContrasena))) {
            throw new \InvalidArgumentException("El campo de contrasena no puede estar vacio.");
        }
        if (empty(trim($valorUsuario))) {
            throw new \InvalidArgumentException("El valor de usuario no puede estar vacio.");
        }
        if (empty(trim($valorContrasena))) {
            throw new \InvalidArgumentException("La contrasena no puede estar vacia.");
        }

        // Verificar permisos
        if (!$this->politicaTablasProhibidas->esTablaPermitida($nombreTabla)) {
            throw new \RuntimeException(
                "Acceso denegado: La tabla '{$nombreTabla}' esta restringida."
            );
        }

        $esquemaNormalizado = (empty($esquema) || empty(trim($esquema))) ? null : trim($esquema);

        try {
            // Paso 1: Pedir al repositorio el hash de contrasena para ese usuario
            // Ejemplo: busca en la tabla 'usuario' donde email='admin@correo.com'
            //          y trae el valor de la columna 'contrasena'
            $hashAlmacenado = $this->repositorioLectura->obtenerHashContrasenaAsync(
                $nombreTabla, $esquemaNormalizado, trim($campoUsuario), trim($campoContrasena), trim($valorUsuario)
            );

            // Paso 2: Si devolvio null, el usuario no existe
            if ($hashAlmacenado === null) {
                return ['codigo' => 404, 'mensaje' => 'Usuario no encontrado'];
            }

            // Paso 3: Comparar la contrasena ingresada con el hash de la BD
            // password_verify('admin123', '$2y$10$7Fl0IeW...') -> true o false
            if (password_verify($valorContrasena, $hashAlmacenado)) {
                return ['codigo' => 200, 'mensaje' => 'Credenciales validas'];     // Contrasena correcta
            } else {
                return ['codigo' => 401, 'mensaje' => 'Contrasena incorrecta'];    // Contrasena incorrecta
            }

        // Si algo fallo (error de BD, error de conexion, etc.)
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Error durante la verificacion de credenciales: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
