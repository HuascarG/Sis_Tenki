<?php
// Controlador/BackupController.php

class BackupController {
    private string $dbHost;
    private string $dbPort;
    private string $dbName;
    private string $dbUser;
    private string $dbPass;
    private string $dbSslMode;

    public function __construct() {
        if (!function_exists('env')) {
            $envPath = dirname(__DIR__) . '/Config/env.php';
            if (file_exists($envPath)) {
                require_once $envPath;
            }
        }
        $this->dbHost    = function_exists('env') ? env('DB_HOST', '127.0.0.1') : '127.0.0.1';
        $this->dbPort    = function_exists('env') ? (string)env('DB_PORT', '3306') : '3306';
        $this->dbName    = function_exists('env') ? env('DB_NAME', 'tenki_db')  : 'tenki_db';
        $this->dbUser    = function_exists('env') ? env('DB_USER', 'root')      : 'root';
        $this->dbPass    = function_exists('env') ? env('DB_PASS', '')          : '';
        $this->dbSslMode = function_exists('env') ? (string)env('DB_SSL_MODE', 'false') : 'false';
    }

    private function getBinary(string $tool): string {
        // En Windows buscar posibles rutas o usar el comando en PATH
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $candidates = [
                "C:\\xampp\\mysql\\bin\\{$tool}.exe",
                "C:\\laragon\\bin\\mysql\\current\\bin\\{$tool}.exe",
                "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\{$tool}.exe"
            ];
            // Detectar automáticamente cualquier versión de MySQL en Laragon
            $laragonMysql = glob("C:\\laragon\\bin\\mysql\\*\\bin\\{$tool}.exe");
            if (!empty($laragonMysql)) {
                $candidates = array_merge($laragonMysql, $candidates);
            }
            $candidates[] = "{$tool}.exe";

            foreach ($candidates as $c) {
                if (file_exists($c)) return $c;
            }
            return "{$tool}.exe";
        }
        return $tool; // Linux / Docker en Render (encontrado en PATH)
    }

    public function generarBackup(): void {
        $date = date('Y-m-d_H-i-s');
        $fileName = "tenki_backup_{$date}.sql";
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;

        $binary = $this->getBinary('mysqldump');
        $passParam = !empty($this->dbPass) ? '--password=' . escapeshellarg($this->dbPass) : '';
        $portParam = !empty($this->dbPort) ? '--port=' . escapeshellarg($this->dbPort) : '';

        // Soporte SSL para bases de datos en la nube (Aiven, PlanetScale, etc.)
        $sslParam = '';
        if (in_array(strtolower($this->dbSslMode), ['required', 'true', '1'], true)) {
            $sslParam = '--ssl';
        }

        // Banderas seguras para la nube:
        // --single-transaction evita bloqueos de tablas durante el backup
        // --no-tablespaces evita requerir privilegio PROCESS/SUPER en Aiven/RDS
        $cloudFlags = '--single-transaction --quick --no-tablespaces';

        $command = sprintf(
            '%s --host=%s %s --user=%s %s %s %s %s > %s 2>&1',
            escapeshellcmd($binary),
            escapeshellarg($this->dbHost),
            $portParam,
            escapeshellarg($this->dbUser),
            $passParam,
            $sslParam,
            $cloudFlags,
            escapeshellarg($this->dbName),
            escapeshellarg($tmpPath)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($tmpPath) && filesize($tmpPath) > 0) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($tmpPath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($tmpPath));

            readfile($tmpPath);
            unlink($tmpPath);
            exit;
        } else {
            $errMsg = !empty($output) ? implode(' ', $output) : 'mysqldump no disponible';
            if (file_exists($tmpPath)) unlink($tmpPath);
            error_log('[TENKI Backup] Error al generar backup: ' . $errMsg);
            throw new Exception("Error al ejecutar mysqldump: " . $errMsg);
        }
    }

    public function restaurarBackup(string $sqlFilePath): bool {
        if (!file_exists($sqlFilePath)) {
            return false;
        }

        $binary = $this->getBinary('mysql');
        $passParam = !empty($this->dbPass) ? '--password=' . escapeshellarg($this->dbPass) : '';
        $portParam = !empty($this->dbPort) ? '--port=' . escapeshellarg($this->dbPort) : '';

        $sslParam = '';
        if (in_array(strtolower($this->dbSslMode), ['required', 'true', '1'], true)) {
            $sslParam = '--ssl';
        }

        $command = sprintf(
            '%s --host=%s %s --user=%s %s %s %s < %s 2>&1',
            escapeshellcmd($binary),
            escapeshellarg($this->dbHost),
            $portParam,
            escapeshellarg($this->dbUser),
            $passParam,
            $sslParam,
            escapeshellarg($this->dbName),
            escapeshellarg($sqlFilePath)
        );

        exec($command, $output, $returnVar);

        if (file_exists($sqlFilePath)) {
            unlink($sqlFilePath);
        }

        if ($returnVar !== 0) {
            error_log('[TENKI Backup] Error al restaurar backup: ' . implode(' ', $output));
        }

        return $returnVar === 0;
    }
}
