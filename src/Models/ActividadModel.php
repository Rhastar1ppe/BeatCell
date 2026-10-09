<?php
declare(strict_types=1); // Hace que PHP sea estricto con los tipos declarados.

require_once __DIR__ . '/../../database/database.php'; // Carga la clase que abre la conexión PDO.
require_once __DIR__ . '/ValidadorDatos.php';
require_once __DIR__ . '/TiempoActividad.php';

class ActividadModel
{
    private PDO $conn; // Guarda la conexión que este modelo usará para consultar MySQL.

    // Consulta base que devuelve los datos de cada actividad y cuenta sus preguntas.
    // La subconsulta evita depender del valor almacenado en numero_preguntas, que podría quedar desactualizado.
    private const SELECT_BASE = '
        SELECT a.*,
               (SELECT COUNT(*)
                FROM preguntas p
                WHERE p.id_actividad = a.id_actividad) AS preguntas_registradas
        FROM actividades a';

    /**
     * Prepara el modelo para usar la base de datos.
     *
     * Si se pasa una conexión PDO, la reutiliza; si no, la crea mediante Database.
     *
     * @param PDO|null $conn Conexión existente opcional.
     */
    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect(); // Reutiliza PDO o solicita una conexión nueva.
    }

    /**
     * Obtiene todas las actividades, opcionalmente solo las activas.
     *
     * @param bool $soloActivas Si es true, excluye las actividades inactivas.
     * @return array Lista de actividades; cada fila incluye preguntas_registradas.
     */
    public function obtenerTodos(bool $soloActivas = false): array
    {
        $sql = self::SELECT_BASE; // Empieza con la consulta base común a las búsquedas.
        if ($soloActivas) { // Comprueba si el llamador pidió solo actividades activas.
            $sql .= " WHERE a.estado = 'Activo'"; // Añade el filtro de estado activo.
        }
        $sql .= ' ORDER BY a.id_tema, a.id_actividad'; // Ordena primero por tema y luego por actividad.

        return $this->conn->query($sql)->fetchAll(); // Ejecuta la consulta y devuelve todas las filas.
    }

    /**
     * Busca una actividad por su id.
     *
     * @param int $idActividad Id de la actividad que se busca.
     * @return array|null Devuelve la fila encontrada o null si no existe.
     */
    public function obtenerPorId(int $idActividad): ?array
    {
        $sql = self::SELECT_BASE . ' WHERE a.id_actividad = :id_actividad'; // Agrega un filtro con parámetro seguro.
        $stmt = $this->conn->prepare($sql); // Prepara la consulta para evitar insertar el id directamente en SQL.
        $stmt->execute(['id_actividad' => $idActividad]); // Envía el id como parámetro de la consulta.
        $actividad = $stmt->fetch(); // Lee una fila; PDO devuelve false si no encontró ninguna.

        return $actividad === false ? null : $actividad; // Convierte el false de PDO en null para el llamador.
    }

    /**
     * Lista las actividades asociadas a un tema.
     *
     * @param int $idTema Id del tema al que pertenecen las actividades.
     * @param bool $soloActivas Si es true, devuelve únicamente las activas.
     * @return array Lista de actividades encontradas.
     */
    public function obtenerPorTema(int $idTema, bool $soloActivas = false): array
    {
        $sql = self::SELECT_BASE . ' WHERE a.id_tema = :id_tema'; // Limita la búsqueda al tema indicado.
        if ($soloActivas) { // Comprueba si también se pidió filtrar por estado.
            $sql .= " AND a.estado = 'Activo'"; // Conserva el filtro del tema y agrega el estado activo.
        }
        $sql .= ' ORDER BY a.id_actividad'; // Mantiene las actividades en un orden estable.

        $stmt = $this->conn->prepare($sql); // Prepara la consulta antes de pasarle el id del tema.
        $stmt->execute(['id_tema' => $idTema]); // Asocia el valor recibido con el marcador :id_tema.

        return $stmt->fetchAll(); // Devuelve todas las actividades que coinciden.
    }

    /**
     * Crea una actividad usando los datos recibidos.
     *
     * numero_preguntas queda en el valor predeterminado de la tabla (0).
     *
     * @param array $datos Datos como id_tema, titulo y tipo.
     * @return int Id generado para la actividad nueva.
     */
    public function crear(array $datos): int
    {
        $idTema = $this->validarIdTema($datos['id_tema'] ?? null); // Exige que el tema tenga un id entero válido.
        $titulo = ValidadorDatos::texto($datos['titulo'] ?? null, 'titulo'); // Exige un título no vacío.
        $descripcion = ValidadorDatos::texto($datos['descripcion'] ?? null, 'descripcion', true); // Acepta descripción vacía o null.
        $tipo = ValidadorDatos::texto($datos['tipo'] ?? 'Cuestionario', 'tipo'); // Usa Cuestionario si no se indicó el tipo.
        [$modoTiempo, $tiempoLimite] = $this->resolverTiempo($datos); // Convierte minutos / segundos por pregunta del formulario a modo + segundos.
        $puntajeMinimo = $this->validarPuntajeMinimo($datos['puntaje_minimo'] ?? null); // Valida el umbral porcentual o guarda null.
        $estado = ValidadorDatos::estado($datos['estado'] ?? 'Activo'); // Usa Activo como estado predeterminado.

        // Inserta los campos permitidos; los valores se envían aparte para evitar inyección SQL.
        $sql = 'INSERT INTO actividades
                    (id_tema, titulo, descripcion, tipo, modo_tiempo, tiempo_limite, puntaje_minimo, estado)
                VALUES
                    (:id_tema, :titulo, :descripcion, :tipo, :modo_tiempo, :tiempo_limite, :puntaje_minimo, :estado)';
        $stmt = $this->conn->prepare($sql); // Prepara el INSERT con marcadores para cada valor.
        $stmt->execute([ // Ejecuta el INSERT y asigna cada valor a su marcador.
            'id_tema' => $idTema, // Relaciona la actividad con su tema.
            'titulo' => $titulo, // Guarda el título validado.
            'descripcion' => $descripcion, // Guarda la descripción o null.
            'tipo' => $tipo, // Guarda el tipo de actividad.
            'modo_tiempo' => $modoTiempo, // Guarda cómo se mide el tiempo: sin_limite, total o por_pregunta.
            'tiempo_limite' => $tiempoLimite, // Guarda los segundos (totales o por pregunta) o null.
            'puntaje_minimo' => $puntajeMinimo, // Guarda el puntaje mínimo o null.
            'estado' => $estado, // Guarda si comienza activa o inactiva.
        ]);

        return (int) $this->conn->lastInsertId(); // Devuelve el id autogenerado por MySQL.
    }

    /**
     * Actualiza únicamente los campos permitidos que se incluyan en $datos.
     *
     * @param int $idActividad Id de la actividad a modificar.
     * @param array $datos Campos nuevos.
     * @return bool True si MySQL reporta que una fila cambió; false si ninguna cambió.
     */
    public function actualizar(int $idActividad, array $datos): bool
    {
        if ($idActividad < 1) { // Rechaza ids que no pueden corresponder a una fila real.
            throw new InvalidArgumentException('El id de la actividad debe ser mayor que cero.'); // Informa el dato inválido.
        }

        // Lista blanca: impide que una clave arbitraria termine dentro de la consulta UPDATE.
        $permitidos = [
            'id_tema', 'titulo', 'descripcion', 'tipo',
            'modo_tiempo', 'tiempo_limite', 'puntaje_minimo', 'estado',
        ];
        $campos = array_intersect_key($datos, array_flip($permitidos)); // Conserva solo las claves autorizadas.
        if ($campos === []) { // Comprueba que haya al menos un campo modificable.
            throw new InvalidArgumentException('No se enviaron campos válidos para actualizar.'); // Evita un UPDATE vacío.
        }

        $sets = []; // Aquí se reúnen expresiones como titulo = :titulo.
        $parametros = ['id_actividad' => $idActividad]; // Agrega el id usado en el WHERE.
        foreach ($campos as $campo => $valor) { // Recorre cada dato autorizado que se quiere cambiar.
            switch ($campo) { // Aplica la validación correspondiente al tipo de dato.
                case 'id_tema':
                    $parametros[$campo] = $this->validarIdTema($valor); // Valida la relación con el tema.
                    break; // Termina este caso del switch.
                case 'titulo':
                case 'tipo':
                    $parametros[$campo] = ValidadorDatos::texto($valor, $campo); // Exige texto no vacío.
                    break; // Termina este caso del switch.
                case 'descripcion':
                    $parametros[$campo] = ValidadorDatos::texto($valor, $campo, true); // La descripción puede ser null.
                    break; // Termina este caso del switch.
                case 'tiempo_limite':
                    $parametros[$campo] = $this->validarEnteroOpcional($valor, $campo); // Acepta un entero no negativo o null.
                    break; // Termina este caso del switch.
                case 'modo_tiempo':
                    if (!is_string($valor) || !in_array($valor, TiempoActividad::MODOS, true)) { // Solo modos conocidos.
                        throw new InvalidArgumentException('El modo de tiempo no es válido.'); // Informa el dato inválido.
                    }
                    $parametros[$campo] = $valor; // Guarda el modo validado.
                    break; // Termina este caso del switch.
                case 'puntaje_minimo':
                    $parametros[$campo] = $this->validarPuntajeMinimo($valor); // Acepta un porcentaje entre 0 y 100 o null.
                    break; // Termina este caso del switch.
                case 'estado':
                    $parametros[$campo] = ValidadorDatos::estado($valor); // Acepta únicamente Activo o Inactivo.
                    break; // Termina este caso del switch.
                default:
                    continue 2; // Salta a la siguiente clave si no hay un caso aplicable.
            }
            $sets[] = $campo . ' = :' . $campo; // Añade la asignación validada a la consulta dinámica.
        }

        if ($sets === []) { // Comprueba que la lista de asignaciones no haya quedado vacía.
            throw new InvalidArgumentException('No se enviaron campos válidos para actualizar.'); // Evita ejecutar SQL incompleto.
        }

        $sql = 'UPDATE actividades SET ' . implode(', ', $sets) . ' WHERE id_actividad = :id_actividad'; // Arma el UPDATE con solo columnas permitidas.
        $stmt = $this->conn->prepare($sql); // Prepara la consulta con sus parámetros.
        $stmt->execute($parametros); // Aplica los nuevos valores a la fila indicada.

        return $stmt->rowCount() > 0; // Indica si MySQL reportó al menos una fila modificada.
    }

    /** Cambia el estado reutilizando la validación del método actualizar(). */
    public function cambiarEstado(int $idActividad, string $estado): bool
    {
        return $this->actualizar($idActividad, ['estado' => $estado]); // Actualiza únicamente el estado y devuelve el resultado.
    }

    /** Elimina una actividad; sus preguntas y resultados dependen de las reglas CASCADE del SQL. */
    public function eliminar(int $idActividad): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM actividades WHERE id_actividad = :id_actividad'); // Prepara el borrado por id.
        $stmt->execute(['id_actividad' => $idActividad]); // Ejecuta el borrado usando un parámetro.

        return $stmt->rowCount() > 0; // Devuelve true si se eliminó una fila.
    }

    /** Cuenta cuántas preguntas están registradas para la actividad indicada. */
    public function contarPreguntas(int $idActividad): int
    {
        $stmt = $this->conn->prepare( // Prepara una consulta que devuelve un único número.
            'SELECT COUNT(*) FROM preguntas WHERE id_actividad = :id_actividad'
        );
        $stmt->execute(['id_actividad' => $idActividad]); // Cuenta las preguntas de esa actividad.

        return (int) $stmt->fetchColumn(); // Lee la primera columna y la devuelve como entero.
    }

    /** Comprueba que el id_tema sea un entero positivo. */
    private function validarIdTema($valor): int
    {
        $idTema = filter_var($valor, FILTER_VALIDATE_INT); // Intenta convertir/validar el valor como entero.
        if ($idTema === false || $idTema < 1) { // Detecta texto no entero, cero o números negativos.
            throw new InvalidArgumentException('id_tema debe ser un entero mayor que cero.'); // Detiene la operación con un error claro.
        }

        return $idTema; // Devuelve el id validado.
    }

    /** Valida un entero opcional no negativo, como el tiempo límite. */
    private function validarEnteroOpcional($valor, string $campo): ?int
    {
        if ($valor === null || $valor === '') { // Trata null o cadena vacía como dato no definido.
            return null; // La columna quedará sin valor.
        }

        $numero = filter_var($valor, FILTER_VALIDATE_INT); // Comprueba que el dato sea entero.
        if ($numero === false || $numero < 0) { // Rechaza valores inválidos o negativos.
            throw new InvalidArgumentException($campo . ' debe ser un entero igual o mayor que cero.'); // Explica el formato requerido.
        }

        return $numero; // Devuelve el entero validado.
    }

    /**
     * Interpreta los campos de tiempo del formulario y devuelve [modo_tiempo, tiempo_limite en segundos].
     *
     * - sin_limite:   no usa ningún número.
     * - total:        lee tiempo_minutos y lo convierte a segundos.
     * - por_pregunta: lee tiempo_por_pregunta (segundos).
     * Si no llega modo_tiempo (llamadas antiguas), tiempo_limite se trata como segundos de tiempo total.
     */
    private function resolverTiempo(array $datos): array
    {
        $modo = $datos['modo_tiempo'] ?? null;

        if ($modo === null || $modo === '') {
            $segundos = $this->validarEnteroOpcional($datos['tiempo_limite'] ?? null, 'tiempo_limite');
            return ($segundos === null || $segundos === 0)
                ? [TiempoActividad::SIN_LIMITE, null]
                : [TiempoActividad::TOTAL, $segundos];
        }
        if (!is_string($modo) || !in_array($modo, TiempoActividad::MODOS, true)) {
            throw new InvalidArgumentException('El modo de tiempo no es válido.');
        }
        if ($modo === TiempoActividad::SIN_LIMITE) {
            return [TiempoActividad::SIN_LIMITE, null];
        }

        if ($modo === TiempoActividad::TOTAL) {
            $minutos = filter_var($datos['tiempo_minutos'] ?? null, FILTER_VALIDATE_INT);
            if ($minutos === false || $minutos < TiempoActividad::MIN_TOTAL_MINUTOS || $minutos > TiempoActividad::MAX_TOTAL_MINUTOS) {
                throw new InvalidArgumentException(sprintf(
                    'La duración total debe ser un número entero de minutos entre %d y %d.',
                    TiempoActividad::MIN_TOTAL_MINUTOS,
                    TiempoActividad::MAX_TOTAL_MINUTOS
                ));
            }
            return [TiempoActividad::TOTAL, $minutos * 60];
        }

        $segundos = filter_var($datos['tiempo_por_pregunta'] ?? null, FILTER_VALIDATE_INT);
        if ($segundos === false || $segundos < TiempoActividad::MIN_PREGUNTA_SEGUNDOS || $segundos > TiempoActividad::MAX_PREGUNTA_SEGUNDOS) {
            throw new InvalidArgumentException(sprintf(
                'El tiempo por pregunta debe ser un número entero de segundos entre %d y %d.',
                TiempoActividad::MIN_PREGUNTA_SEGUNDOS,
                TiempoActividad::MAX_PREGUNTA_SEGUNDOS
            ));
        }
        return [TiempoActividad::POR_PREGUNTA, $segundos];
    }

    /** Valida un número decimal opcional no negativo, como el puntaje mínimo. */
    private function validarDecimalOpcional($valor, string $campo): ?float
    {
        if ($valor === null || $valor === '') { // Trata null o cadena vacía como valor no definido.
            return null; // La columna quedará sin valor.
        }
        if (!is_numeric($valor) || (float) $valor < 0) { // Rechaza texto no numérico y números negativos.
            throw new InvalidArgumentException($campo . ' debe ser un número igual o mayor que cero.'); // Informa la regla esperada.
        }

        return (float) $valor; // Convierte y devuelve el valor como decimal.
    }

    private function validarPuntajeMinimo($valor): ?float
    {
        $puntajeMinimo = $this->validarDecimalOpcional($valor, 'puntaje_minimo');
        if ($puntajeMinimo !== null && $puntajeMinimo > 100) {
            throw new InvalidArgumentException('El puntaje mínimo debe ser un porcentaje entre 0 y 100.');
        }

        return $puntajeMinimo;
    }

}