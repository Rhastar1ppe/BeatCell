<?php

require_once __DIR__ . '/../Models/TemaModel.php';

class TemaController
{
    private TemaModel $temaModel;

    public function __construct($db)
    {
        $this->temaModel = new TemaModel($db);
    }

    public function listarTodos()
    {
        return $this->temaModel->listarTodos();
    }

    public function listarPorModulo($idModulo)
    {
        if (empty($idModulo) || $idModulo <= 0) {
            return [];
        }

        return $this->temaModel->listarPorModulo($idModulo);
    }

    public function buscarPorId($idTema)
    {
        if (empty($idTema) || $idTema <= 0) {
            return false;
        }

        return $this->temaModel->buscarPorId($idTema);
    }

    public function crear($datos)
    {
        if (
            empty($datos['id_modulo']) ||
            $datos['id_modulo'] <= 0
        ) {
            return [
                'success' => false,
                'mensaje' => 'Debe seleccionar un modulo.'
            ];
        }
        if (
            empty($datos['nombre']) ||
            trim($datos['nombre']) === ''
        ) {
            return [
                'success' => false,
                'mensaje' => 'El nombre del tema es obligatorio.'
            ];
        }
        if (
            empty($datos['orden']) ||
            $datos['orden'] <= 0
        ) {
            return [
                'success' => false,
                'mensaje' => 'El orden debe ser mayor que cero.'
            ];
        }

        $idModulo = (int) $datos['id_modulo'];

        $nombre = trim($datos['nombre']);

        $descripcion =
            isset($datos['descripcion'])
            ? trim($datos['descripcion'])
            : null;

        $materialApoyo =
            isset($datos['material_apoyo'])
            ? trim($datos['material_apoyo'])
            : null;

        $orden = (int) $datos['orden'];


        try {

            $resultado = $this->temaModel->crear(
                $idModulo,
                $nombre,
                $descripcion,
                $materialApoyo,
                $orden
            );


            if ($resultado) {
                return [
                    'success' => true,
                    'mensaje' => 'Tema registrado correctamente.'
                ];
            }


            return [
                'success' => false,
                'mensaje' => 'No se pudo registrar el tema.'
            ];

        } catch (PDOException $e) {

            return [
                'success' => false,
                'mensaje' => 'Error al registrar el tema.'
            ];
        }
    }

    public function actualizar($idTema, $datos)
    {
        // Validar ID del tema
        if (empty($idTema) || $idTema <= 0) {
            return [
                'success' => false,
                'mensaje' => 'El tema seleccionado no es válido.'
            ];
        }

        if (
            empty($datos['id_modulo']) ||
            $datos['id_modulo'] <= 0
        ) {
            return [
                'success' => false,
                'mensaje' => 'Debe seleccionar un modulo.'
            ];
        }

        if (
            empty($datos['nombre']) ||
            trim($datos['nombre']) === ''
        ) {
            return [
                'success' => false,
                'mensaje' => 'El nombre del tema es obligatorio.'
            ];
        }

        if (
            empty($datos['orden']) ||
            $datos['orden'] <= 0
        ) {
            return [
                'success' => false,
                'mensaje' => 'El orden debe ser mayor que cero.'
            ];
        }

        $idModulo = (int) $datos['id_modulo'];

        $nombre = trim($datos['nombre']);

        $descripcion =
            isset($datos['descripcion'])
            ? trim($datos['descripcion'])
            : null;

        $materialApoyo =
            isset($datos['material_apoyo'])
            ? trim($datos['material_apoyo'])
            : null;

        $orden = (int) $datos['orden'];


        try {

            $resultado = $this->temaModel->actualizar(
                $idTema,
                $idModulo,
                $nombre,
                $descripcion,
                $materialApoyo,
                $orden
            );


            if ($resultado) {
                return [
                    'success' => true,
                    'mensaje' => 'Tema actualizado correctamente.'
                ];
            }


            return [
                'success' => false,
                'mensaje' => 'No se pudo actualizar el tema.'
            ];

        } catch (PDOException $e) {

            return [
                'success' => false,
                'mensaje' => 'Error al actualizar el tema.'
            ];
        }
    }

    public function cambiarEstado($idTema, $estado)
    {
        if (empty($idTema) || $idTema <= 0) {
            return [
                'success' => false,
                'mensaje' => 'El tema seleccionado no es válido.'
            ];
        }

        $estadosPermitidos = [
            'Activo',
            'Inactivo'
        ];


        if (!in_array($estado, $estadosPermitidos)) {
            return [
                'success' => false,
                'mensaje' => 'El estado no es válido.'
            ];
        }


        try {

            $resultado =
                $this->temaModel->cambiarEstado(
                    $idTema,
                    $estado
                );


            if ($resultado) {
                return [
                    'success' => true,
                    'mensaje' => 'Estado actualizado correctamente.'
                ];
            }


            return [
                'success' => false,
                'mensaje' => 'No se pudo cambiar el estado.'
            ];

        } catch (PDOException $e) {

            return [
                'success' => false,
                'mensaje' => 'Error al cambiar el estado.'
            ];
        }
    }
}