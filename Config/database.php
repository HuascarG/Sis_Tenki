<?php
class Database {
    private static $instance = null;
    private $pdo;

    private $host;
    private $port;
    private $dbname;
    private $user;
    private $pass;
    private $charset;

    private function __construct() {
        if (!function_exists('env')) {
            $envPath = dirname(__DIR__) . '/Config/env.php';
            if (file_exists($envPath)) {
                require_once $envPath;
            }
        }

        $this->host    = function_exists('env') ? env('DB_HOST', 'localhost') : 'localhost';
        $this->port    = function_exists('env') ? env('DB_PORT', '3306')      : '3306';
        $this->dbname  = function_exists('env') ? env('DB_NAME', 'tenki_db')  : 'tenki_db';
        $this->user    = function_exists('env') ? env('DB_USER', 'root')      : 'root';
        $this->pass    = function_exists('env') ? env('DB_PASS', '')          : '';
        $this->charset = function_exists('env') ? env('DB_CHARSET', 'utf8mb4'): 'utf8mb4';

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        // Soporte para SSL en bases de datos en la nube (Aiven, PlanetScale, AWS)
        $sslMode = function_exists('env') ? env('DB_SSL_MODE', '') : '';
        if ($sslMode === 'REQUIRED' || $sslMode === 'true') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            error_log('[TENKI DB] Error de conexión: ' . $e->getMessage());
            die(json_encode(['error' => 'Error de conexión a la base de datos. Contacte al administrador.']));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}

function db(): PDO {
    return Database::getInstance()->getConnection();
}
