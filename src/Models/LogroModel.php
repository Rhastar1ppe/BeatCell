<?php
declare(strict_types=1); // Hace que PHP respete estrictamente los tipos declarados.

require_once __DIR__ . '/../../database/database.php'; // Carga la clase Database usada para conectarse a MySQL.
require_once __DIR__ . '/ValidadorDatos.php';

class LogroModel
{
    private PDO $conn; // Guarda la conexión PDO que usarán las consultas de este modelo.

    /**
     * Inicializa el modelo con una conexión.
     *
     * Si se entrega una conexión PDO, la reutiliza; de lo contrario, pide una a Database.
     *
     * @param PDO|null $conn Conexión existente opcional.
     */
    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect(); // Usa la conexión recibida o abre una nueva.
    }

    /**
     * Obtiene todos los logros o únicamente los activos.
     *
     * @param bool $soloActivos Si es true, filtra los logros inactivos.
     * @return array Lista de filas de la tabla logros.
     */
    public function obtenerTodos(bool $soloActivos = false): array
    {
        $sql = 'SELECT * FROM logros'; // Prepara una consulta para recuperar los campos del logro.
        if ($soloActivos) { // Comprueba si solo se pidieron logros activos.
            $sql .= " WHERE estado = 'Activo'"; // Agrega el filtro de estado.
        }
        $sql .= ' ORDER BY id_logro'; // Ordena los resultados por id.

        return $this->conn->query($sql)->fetchAll(); // Ejecuta y devuelve todas las filas encontradas.
    }

    /**
     * Busca un logro por su id.
     *
     * @param int $idLogro Id que se quiere buscar.
     * @return array|null Devuelve el logro o null si no existe.
     */
    public function obtenerPorId(int $idLogro): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM logros WHERE id_logro = :id_logro'); // Prepara la búsqueda por id.
        $stmt->execute(['id_logro' => $idLogro]); // Envía el id separado del SQL.
        $logro = $stmt->fetch(); // Obtiene la fila; PDO devuelve false cuando no encuentra una.

        return $logro === false ? null : $logro; // Devuelve null en vez de false cuando no existe.
    }

    /**
     * Obtiene los logros que ya se otorgaron a un usuario.
     *
     * @param int $idUsuario Id del usuario.
     * @return array Logros del usuario, incluyendo la fecha en que se otorgaron.
     */
    public function obtenerPorUsuario(int $idUsuario): array
    {
        // Une la tabla intermedia usuario_logros con logros para devolver el detalle de cada logro obtenido.
        $sql = 'SELECT l.*, ul.fecha_obtenido
                FROM usuario_logros ul
                INNER JOIN logros l ON l.id_logro = ul.id_logro
                WHERE ul.id_usuario = :id_usuario
                ORDER BY ul.fecha_obtenido DESC, l.id_logro';
        $stmt = $this->conn->prepare($sql); // Prepara la consulta con un marcador para el usuario.
        $stmt->execute(['id_usuario' => $idUsuario]); // Busca únicamente las filas asociadas a ese usuario.

        return $stmt->fetchAll(); // Devuelve todos los logros obtenidos por el usuario.
    }

    /**
     * Crea un logro nuevo.
     *
     * @param array $datos Datos del logro: nombre y, opcionalmente, descripción, icono, tipo, condición y estado.
     * @return int Id generado para el logro.
     */
    public function crear(array $datos): int
    {
        $nombre = ValidadorDatos::texto($datos['nombre'] ?? null, 'nombre'); // Exige un nombre no vacío.
        $descripcion = ValidadorDatos::texto($datos['descripcion'] ?? null, 'descripcion', true); // Acepta descripción vacía o null.
        $icono = ValidadorDatos::texto($datos['icono'] ?? null, 'icono', true); // Acepta un icono opcional.
        $tipo = ValidadorDatos::texto($datos['tipo'] ?? null, 'tipo', true); // Acepta un tipo opcional.
        $condicion = ValidadorDatos::texto($datos['condicion'] ?? null, 'condicion', true); // Acepta una condición opcional.
        $estado = ValidadorDatos::estado($datos['estado'] ?? 'Activo'); // Si no se indicó estado, usa Activo.

        // Inserta los campos del logro usando parámetros preparados.
        $sql = 'INSERT INTO logros (nombre, descripcion, icono, tipo, condicion, estado)
                VALUES (:nombre, :descripcion, :icono, :tipo, :condicion, :estado)';
        $stmt = $this->conn->prepare($sql); // Prepara el INSERT para separar el SQL de los valores.
        $stmt->execute([ // Inserta el logro con los valores validados.
            'nombre' => $nombre, // Guarda el nombre obligatorio.
            'descripcion' => $descripcion, // Guarda la descripción o null.
            'icono' => $icono, // Guarda la ruta o nombre del icono, o null.
            'tipo' => $tipo, // Guarda la categoría del logro, o null.
            'condicion' => $condicion, // Guarda el texto de la condición, o null.
            'estado' => $estado, // Guarda si el logro está activo o inactivo.
        ]);

        return (int) $this->conn->lastInsertId(); // Devuelve el id que generó MySQL.
    }

    /**
     * Actualiza los campos válidos que se incluyan en $datos.
     *
     * @param int $idLogro Id del logro que se modificará.
     * @param array $datos Campos nuevos.
     * @return bool True si MySQL reporta una fila modificada; false si ninguna cambió.
     */
    public function actualizar(int $idLogro, array $datos): bool
    {
        if ($idLogro < 1) { // Revisa que el id pueda corresponder a una fila real.
            throw new InvalidArgumentException('El id del logro debe ser mayor que cero.'); // Detiene la operación si el id es inválido.
        }

        $permitidos = ['nombre', 'descripcion', 'icono', 'tipo', 'condicion', 'estado']; // Define las columnas que este método puede modificar.
        $campos = array_intersect_key($datos, array_flip($permitidos)); // Descarta claves que no estén en la lista permitida.
        if ($campos === []) { // Comprueba que haya al menos un campo válido.
            throw new InvalidArgumentException('No se enviaron campos válidos para actualizar.'); // Evita generar un UPDATE vacío.
        }

        $sets = []; // Acumula las asignaciones que formarán la parte SET del UPDATE.
        $parametros = ['id_logro' => $idLogro]; // Guarda el id que se usará en la condición WHERE.
        foreach ($campos as $campo => $valor) { // Revisa uno por uno los campos enviados.
            if ($campo === 'estado') { // El estado solo acepta dos valores.
                $parametros[$campo] = ValidadorDatos::estado($valor); // Comprueba que sea Activo o Inactivo.
            } elseif ($campo === 'nombre') { // El nombre es obligatorio y no puede quedar vacío.
                $parametros[$campo] = ValidadorDatos::texto($valor, $campo); // Limpia y valida el nombre.
            } else { // Descripción, icono, tipo y condición son opcionales.
                $parametros[$campo] = ValidadorDatos::texto($valor, $campo, true); // Convierte texto vacío en null.
            }
            $sets[] = $campo . ' = :' . $campo; // Añade una asignación con parámetro, por ejemplo nombre = :nombre.
        }

        $sql = 'UPDATE logros SET ' . implode(', ', $sets) . ' WHERE id_logro = :id_logro'; // Une asignaciones y limita el cambio al id indicado.
        $stmt = $this->conn->prepare($sql); // Prepara la consulta dinámica con nombres de columna permitidos.
        $stmt->execute($parametros); // Guarda los cambios usando parámetros seguros.

        return $stmt->rowCount() > 0; // Informa si MySQL reportó que la fila cambió.
    }

    /** Cambia el estado reutilizando las validaciones del método actualizar(). */
    public function cambiarEstado(int $idLogro, string $estado): bool
    {
        return $this->actualizar($idLogro, ['estado' => $estado]); // Actualiza solo el estado y devuelve el resultado.
    }

    /** Elimina un logro; usuario_logros se elimina en cascada según la definición SQL. */
    public function eliminar(int $idLogro): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM logros WHERE id_logro = :id_logro'); // Prepara el borrado por id.
        $stmt->execute(['id_logro' => $idLogro]); // Ejecuta el borrado usando un parámetro.

        return $stmt->rowCount() > 0; // Devuelve true si se eliminó una fila.
    }

    /**
     * Registra que un usuario obtuvo un logro, sin crear asociaciones duplicadas.
     *
     * @return bool True si se insertó ahora; false si ya existía o el logro no existe.
     */
    public function otorgarAUsuario(int $idUsuario, int $idLogro): bool
    {
        if ($idUsuario < 1 || $idLogro < 1) { // Revisa que ambos ids sean positivos.
            throw new InvalidArgumentException('Los ids de usuario y logro deben ser mayores que cero.'); // Detiene la operación si alguno es inválido.
        }

        // Inserta la relación solo si existe el logro; la clave única del SQL evita duplicar el mismo logro para el usuario.
        $sql = 'INSERT INTO usuario_logros (id_usuario, id_logro)
                SELECT :id_usuario, l.id_logro
                FROM logros l
                WHERE l.id_logro = :id_logro
                ON DUPLICATE KEY UPDATE id_usuario_logro = id_usuario_logro';
        $stmt = $this->conn->prepare($sql); // Prepara el INSERT SELECT.
        $stmt->execute([ // Ejecuta la asociación con los ids indicados.
            'id_usuario' => $idUsuario, // Indica a qué usuario se asigna el logro.
            'id_logro' => $idLogro, // Indica qué logro se está asignando.
        ]);

        return $stmt->rowCount() === 1; // True significa que se creó una asociación nueva.
    }

    /** Comprueba si un usuario ya tiene un logro concreto. */
    public function tieneLogro(int $idUsuario, int $idLogro): bool
    {
        $sql = 'SELECT 1
                FROM usuario_logros
                WHERE id_usuario = :id_usuario AND id_logro = :id_logro
                LIMIT 1'; // Devuelve una marca si existe la relación; LIMIT 1 evita buscar más filas.
        $stmt = $this->conn->prepare($sql); // Prepara la comprobación con dos parámetros.
        $stmt->execute([ // Busca la combinación de usuario y logro.
            'id_usuario' => $idUsuario, // Primer valor de la relación.
            'id_logro' => $idLogro, // Segundo valor de la relación.
        ]);

        return $stmt->fetchColumn() !== false; // Convierte el resultado de la consulta en true o false.
    }

    /** Cuenta cuántos logros tiene registrados un usuario. */
    public function contarPorUsuario(int $idUsuario): int
    {
        $stmt = $this->conn->prepare( // Prepara una consulta que devuelve un único total.
            'SELECT COUNT(*) FROM usuario_logros WHERE id_usuario = :id_usuario'
        );
        $stmt->execute(['id_usuario' => $idUsuario]); // Cuenta las relaciones de ese usuario.

        return (int) $stmt->fetchColumn(); // Lee el total y lo devuelve como entero.
    }

}
