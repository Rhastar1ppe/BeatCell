<?php

class PreguntaRepository{
    private PDO $db;

    public function __construct(PDO $db){
        
        $this->db = $db;
    }

    public function obtenerPorId(int $idPregunta): ?array{

        $sql = "SELECT * FROM preguntas WHERE id_pregunta = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idPregunta]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function obtenerPorActividad(int $idActividad): array{

        $sql = "SELECT * FROM preguntas WHERE id_actividad = ? ORDER BY id_pregunta DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idActividad]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerConOpciones(int $idPregunta): ?array{

        $pregunta = $this->obtenerPorId($idPregunta);
        if (!$pregunta) {
            return null;
        }

        $sql = "SELECT * FROM opciones_respuesta WHERE id_pregunta = ? ORDER BY id_opcion ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idPregunta]);
        $pregunta['opciones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $pregunta;
    }

    public function crear(array $data, array $opciones = []): int{

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO preguntas (id_actividad, pregunta, tipo, puntos, imagen, audio, tiempo_limite, explicacion, estado) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['id_actividad'],
                $data['pregunta'],
                $data['tipo'],
                $data['puntos'] ?? 1,
                $data['imagen'] ?? null,
                $data['audio'] ?? null,
                $data['tiempo_limite'] ?? null,
                $data['explicacion'] ?? null,
                $data['estado'] ?? 'Activo'
            ]);

            $idPregunta = (int) $this->db->lastInsertId();

            if (!empty($opciones)) {
                $sqlOpcion = "INSERT INTO opciones_respuesta (id_pregunta, texto, imagen, correcta, retroalimentacion) 
                              VALUES (?, ?, ?, ?, ?)";
                $stmtOpcion = $this->db->prepare($sqlOpcion);

                foreach ($opciones as $opcion) {
                    $stmtOpcion->execute([
                        $idPregunta,
                        $opcion['texto'] ?? '',
                        $opcion['imagen'] ?? null,
                        $opcion['correcta'] ? 1 : 0,
                        $opcion['retroalimentacion'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            return $idPregunta;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $idPregunta, array $data, array $opciones = []): bool{

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE preguntas SET id_actividad = ?, pregunta = ?, tipo = ?, puntos = ?, imagen = ?, audio = ?, tiempo_limite = ?, explicacion = ?, estado = ? 
                    WHERE id_pregunta = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['id_actividad'],
                $data['pregunta'],
                $data['tipo'],
                $data['puntos'] ?? 1,
                $data['imagen'] ?? null,
                $data['audio'] ?? null,
                $data['tiempo_limite'] ?? null,
                $data['explicacion'] ?? null,
                $data['estado'] ?? 'Activo',
                $idPregunta
            ]);

            $stmtBuscarOpcion = $this->db->prepare(
                "SELECT id_opcion FROM opciones_respuesta WHERE id_opcion = ? AND id_pregunta = ?"
            );
            $stmtActualizarOpcion = $this->db->prepare(
                "UPDATE opciones_respuesta SET texto = ?, imagen = ?, correcta = ?, retroalimentacion = ?
                 WHERE id_opcion = ? AND id_pregunta = ?"
            );
            $stmtInsertarOpcion = $this->db->prepare(
                "INSERT INTO opciones_respuesta (id_pregunta, texto, imagen, correcta, retroalimentacion)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $idsOpcionesConservadas = [];

            foreach ($opciones as $opcion) {
                $idOpcion = (int) ($opcion['id_opcion'] ?? 0);
                if ($idOpcion > 0 && !isset($idsOpcionesConservadas[$idOpcion])) {
                    $stmtBuscarOpcion->execute([$idOpcion, $idPregunta]);
                    $existe = $stmtBuscarOpcion->fetchColumn();
                } else {
                    $existe = false;
                }

                $valores = [
                    $opcion['texto'] ?? '',
                    $opcion['imagen'] ?? null,
                    !empty($opcion['correcta']) ? 1 : 0,
                    $opcion['retroalimentacion'] ?? null
                ];

                if ($existe) {
                    $stmtActualizarOpcion->execute(array_merge($valores, [$idOpcion, $idPregunta]));
                    $idsOpcionesConservadas[$idOpcion] = true;
                } else {
                    $stmtInsertarOpcion->execute(array_merge([$idPregunta], $valores));
                    $idsOpcionesConservadas[(int) $this->db->lastInsertId()] = true;
                }
            }

            if ($idsOpcionesConservadas) {
                $placeholders = implode(',', array_fill(0, count($idsOpcionesConservadas), '?'));
                $stmtEliminarOpciones = $this->db->prepare(
                    "DELETE FROM opciones_respuesta WHERE id_pregunta = ? AND id_opcion NOT IN ($placeholders)"
                );
                $stmtEliminarOpciones->execute(array_merge([$idPregunta], array_keys($idsOpcionesConservadas)));
            } else {
                $stmtEliminarOpciones = $this->db->prepare(
                    "DELETE FROM opciones_respuesta WHERE id_pregunta = ?"
                );
                $stmtEliminarOpciones->execute([$idPregunta]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $idPregunta, string $estado): bool{

        $sql = "UPDATE preguntas SET estado = ? WHERE id_pregunta = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$estado, $idPregunta]);
    }

    public function eliminar(int $idPregunta): bool{

        $sql = "DELETE FROM preguntas WHERE id_pregunta = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$idPregunta]);
    }
}