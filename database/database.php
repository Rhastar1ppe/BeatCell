<?php
require_once __DIR__ . '/../config/config.php';

class Database {
    /** @var PDO|null */
private $conn;

    public function connect(): PDO {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('La extensión PDO MySQL no está habilitada en PHP.');
        }

        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return $this->conn;
        } catch (PDOException $e) {
            throw new RuntimeException('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }
}