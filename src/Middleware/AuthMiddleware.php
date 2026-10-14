<?php

class AuthMiddleware
{
	public static function requireRole(array $roles, string $loginUrl): array
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			session_start();
		}

		$usuario = $_SESSION['usuario'] ?? null;
		$idUsuario = $_SESSION['id_usuario']
			?? (is_array($usuario) ? ($usuario['id_usuario'] ?? null) : null);
		$rol = is_array($usuario) ? ($usuario['rol'] ?? null) : null;
		$rol = $rol ?? $_SESSION['rol'] ?? $_SESSION['usuario_rol'] ?? null;

		if (is_array($rol)) {
			$rol = $rol['rol'] ?? null;
		}

		$idUsuario = filter_var(
			$idUsuario,
			FILTER_VALIDATE_INT,
			['options' => ['min_range' => 1]]
		);

		if ($idUsuario === false || $idUsuario === null || !is_string($rol) || $rol === '') {
			header('Location: ' . $loginUrl);
			exit;
		}

		if (!in_array($rol, $roles, true)) {
			http_response_code(403);
			exit('No tienes permiso para acceder a esta página.');
		}

		return [
			'id_usuario' => $idUsuario,
			'rol' => $rol,
		];
	}
}
