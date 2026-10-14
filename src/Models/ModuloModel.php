<<<<<<< HEAD
<?php

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/ValidadorDatos.php';

class ModuloModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->connect();
    }
    public function obtenerTodos(): array
    {
        $stmt = $this->db->query(
            "SELECT m.*, c.nombre AS curso_nombre
         FROM modulos m
         INNER JOIN cursos c ON c.id_curso = m.id_curso
         ORDER BY c.nombre ASC, m.orden ASC, m.id_modulo ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorCurso(int $idCurso): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, c.nombre AS curso_nombre
             FROM modulos m
             INNER JOIN cursos c ON c.id_curso = m.id_curso
             WHERE m.id_curso = ?
             ORDER BY m.orden ASC"
        );
        $stmt->execute([$idCurso]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idModulo): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM modulos WHERE id_modulo = ?"
        );
        $stmt->execute([$idModulo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $datos): int
    {
        [$idCurso, $nombre, $descripcion, $orden] = $this->validar($datos);

        $video = $this->normalizarVideo($datos['video_url'] ?? null);

        $stmt = $this->db->prepare(
            "INSERT INTO modulos (id_curso, nombre, descripcion, video_url, orden)
             VALUES (?, ?, ?, ?, ?)"
        );

        try {
            $stmt->execute([$idCurso, $nombre, $descripcion, $video, $orden]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException(
                    'Ya existe un módulo con ese orden en el curso.'
                );
            }

            throw $e;
        }

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $idModulo, array $datos): bool
    {
        if (!$this->obtenerPorId($idModulo)) {
            throw new InvalidArgumentException('El módulo no existe.');
        }

        [$idCurso, $nombre, $descripcion, $orden] = $this->validar($datos);

        $campos = 'id_curso = ?, nombre = ?, descripcion = ?, orden = ?';
        $parametros = [$idCurso, $nombre, $descripcion, $orden];

        // El video solo se toca si el formulario lo envía; así otras
        // llamadas a actualizar() no borran el video del módulo.
        if (array_key_exists('video_url', $datos)) {
            $campos .= ', video_url = ?';
            $parametros[] = $this->normalizarVideo($datos['video_url']);
        }

        $parametros[] = $idModulo;

        $stmt = $this->db->prepare(
            "UPDATE modulos SET $campos WHERE id_modulo = ?"
        );

        try {
            return $stmt->execute($parametros);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException(
                    'Ya existe un módulo con ese orden en el curso.'
                );
            }

            throw $e;
        }
    }

    public function cambiarEstado(int $idModulo, string $estado): bool
    {
        if (!$this->obtenerPorId($idModulo)) {
            throw new InvalidArgumentException('El módulo no existe.');
        }

        $estado = ValidadorDatos::estado($estado);

        $stmt = $this->db->prepare(
            "UPDATE modulos SET estado = ? WHERE id_modulo = ?"
        );

        return $stmt->execute([$estado, $idModulo]);
    }

    /**
     * Acepta una ruta local de video subido (public/uploads/videos/xxx.mp4)
     * o una URL http(s). Cualquier otro valor se rechaza.
     */
    private function normalizarVideo(mixed $video): ?string
    {
        if ($video === null) {
            return null;
        }

        $video = trim((string) $video);

        if ($video === '') {
            return null;
        }

        $esLocal = preg_match(
            '#^public/uploads/videos/[a-f0-9]{32}\.mp4$#',
            $video
        ) === 1;

        $esUrl = strlen($video) <= 2048
            && filter_var($video, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $video) === 1;

        if (!$esLocal && !$esUrl) {
            throw new InvalidArgumentException(
                'El video del módulo no es válido.'
            );
        }

        return $video;
    }

    private function validar(array $datos): array
    {
        $idCurso = filter_var(
            $datos['id_curso'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idCurso === false) {
            throw new InvalidArgumentException('Debes elegir un curso.');
        }

        $nombre = ValidadorDatos::texto($datos['nombre'] ?? '', 'El nombre');

        if (mb_strlen($nombre, 'UTF-8') > 150) {
            throw new InvalidArgumentException(
                'El nombre no puede pasar de 150 caracteres.'
            );
        }

        $descripcion = ValidadorDatos::texto(
            $datos['descripcion'] ?? null,
            'La descripción',
            true
        );

        $orden = filter_var(
            $datos['orden'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($orden === false) {
            throw new InvalidArgumentException(
                'El orden debe ser un número mayor que cero.'
            );
        }

        return [$idCurso, $nombre, $descripcion, $orden];
    }
}
=======
<?php

require_once __DIR__ . '/../../database/database.php';
require_once __DIR__ . '/ValidadorDatos.php';

class ModuloModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Database())->connect();
    }
    public function obtenerTodos(): array
    {
        $stmt = $this->db->query(
            "SELECT m.*, c.nombre AS curso_nombre
         FROM modulos m
         INNER JOIN cursos c ON c.id_curso = m.id_curso
         ORDER BY c.nombre ASC, m.orden ASC, m.id_modulo ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorCurso(int $idCurso): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*, c.nombre AS curso_nombre
             FROM modulos m
             INNER JOIN cursos c ON c.id_curso = m.id_curso
             WHERE m.id_curso = ?
             ORDER BY m.orden ASC"
        );
        $stmt->execute([$idCurso]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $idModulo): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM modulos WHERE id_modulo = ?"
        );
        $stmt->execute([$idModulo]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function crear(array $datos): int
    {
        [$idCurso, $nombre, $descripcion, $orden] = $this->validar($datos);

        $video = $this->normalizarVideo($datos['video_url'] ?? null);

        $stmt = $this->db->prepare(
            "INSERT INTO modulos (id_curso, nombre, descripcion, video_url, orden)
             VALUES (?, ?, ?, ?, ?)"
        );

        try {
            $stmt->execute([$idCurso, $nombre, $descripcion, $video, $orden]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException(
                    'Ya existe un módulo con ese orden en el curso.'
                );
            }

            throw $e;
        }

        return (int) $this->db->lastInsertId();
    }

    public function actualizar(int $idModulo, array $datos): bool
    {
        if (!$this->obtenerPorId($idModulo)) {
            throw new InvalidArgumentException('El módulo no existe.');
        }

        [$idCurso, $nombre, $descripcion, $orden] = $this->validar($datos);

        $campos = 'id_curso = ?, nombre = ?, descripcion = ?, orden = ?';
        $parametros = [$idCurso, $nombre, $descripcion, $orden];

        // El video solo se toca si el formulario lo envía; así otras
        // llamadas a actualizar() no borran el video del módulo.
        if (array_key_exists('video_url', $datos)) {
            $campos .= ', video_url = ?';
            $parametros[] = $this->normalizarVideo($datos['video_url']);
        }

        $parametros[] = $idModulo;

        $stmt = $this->db->prepare(
            "UPDATE modulos SET $campos WHERE id_modulo = ?"
        );

        try {
            return $stmt->execute($parametros);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new InvalidArgumentException(
                    'Ya existe un módulo con ese orden en el curso.'
                );
            }

            throw $e;
        }
    }

    public function cambiarEstado(int $idModulo, string $estado): bool
    {
        if (!$this->obtenerPorId($idModulo)) {
            throw new InvalidArgumentException('El módulo no existe.');
        }

        $estado = ValidadorDatos::estado($estado);

        $stmt = $this->db->prepare(
            "UPDATE modulos SET estado = ? WHERE id_modulo = ?"
        );

        return $stmt->execute([$estado, $idModulo]);
    }

    /**
     * Acepta una ruta local de video subido (public/uploads/videos/xxx.mp4)
     * o una URL http(s). Cualquier otro valor se rechaza.
     */
    private function normalizarVideo(mixed $video): ?string
    {
        if ($video === null) {
            return null;
        }

        $video = trim((string) $video);

        if ($video === '') {
            return null;
        }

        $esLocal = preg_match(
            '#^public/uploads/videos/[a-f0-9]{32}\.mp4$#',
            $video
        ) === 1;

        $esUrl = strlen($video) <= 2048
            && filter_var($video, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $video) === 1;

        if (!$esLocal && !$esUrl) {
            throw new InvalidArgumentException(
                'El video del módulo no es válido.'
            );
        }

        return $video;
    }

    private function validar(array $datos): array
    {
        $idCurso = filter_var(
            $datos['id_curso'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idCurso === false) {
            throw new InvalidArgumentException('Debes elegir un curso.');
        }

        $nombre = ValidadorDatos::texto($datos['nombre'] ?? '', 'El nombre');

        if (mb_strlen($nombre, 'UTF-8') > 150) {
            throw new InvalidArgumentException(
                'El nombre no puede pasar de 150 caracteres.'
            );
        }

        $descripcion = ValidadorDatos::texto(
            $datos['descripcion'] ?? null,
            'La descripción',
            true
        );

        $orden = filter_var(
            $datos['orden'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($orden === false) {
            throw new InvalidArgumentException(
                'El orden debe ser un número mayor que cero.'
            );
        }

        return [$idCurso, $nombre, $descripcion, $orden];
    }
}
>>>>>>> upstream/main
