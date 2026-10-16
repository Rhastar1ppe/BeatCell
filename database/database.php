<?php
class Database {
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private string $charset;
    private ?PDO $conn = null;

    public function __construct(?array $config = null) {
        if ($config === null) {
            $rutaConfig = dirname(__DIR__) . '/config/config.php';
            if (!is_file($rutaConfig)) {
                throw new RuntimeException('No se encontró la configuración de BeatCell.');
            }

            $config = require $rutaConfig;
        }

        $db = $config['database'] ?? [];
        $this->host = (string) ($db['host'] ?? 'localhost');
        $this->db_name = (string) ($db['name'] ?? 'beatcell');
        $this->username = (string) ($db['username'] ?? 'root');
        $this->password = (string) ($db['password'] ?? '');
        $this->charset = (string) ($db['charset'] ?? 'utf8mb4');
    }

    public function connect(): PDO {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('La extensión PDO MySQL no está habilitada en PHP.');
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $this->conn;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Error de conexión a la base de datos: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
