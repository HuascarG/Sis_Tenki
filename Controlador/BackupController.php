<?php
// Controlador/BackupController.php

class BackupController {
    private string $dbHost;
    private string $dbName;
    private string $dbUser;
    private string $dbPass;

    public function __construct() {
        if (!function_exists('env')) {
            $envPath = dirname(__DIR__) . '/Config/env.php';
            if (file_exists($envPath)) {
                require_once $envPath;
            }
        }
        $this->dbHost = function_exists('env') ? env('DB_HOST', '127.0.0.1') : '127.0.0.1';
        $this->dbName = function_exists('env') ? env('DB_NAME', 'tenki_db')  : 'tenki_db';
        $this->dbUser = function_exists('env') ? env('DB_USER', 'root')      : 'root';
        $this->dbPass = function_exists('env') ? env('DB_PASS', '')          : '';
    }

    private function getBinary(string $tool): string {
        // En Windows buscar posibles rutas o usar el comando en PATH
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $candidates = [
                "C:\\xampp\\mysql\\bin\\{$tool}.exe",
                "C:\\laragon\\bin\\mysql\\current\\bin\\{$tool}.exe",
                "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\{$tool}.exe",
                "{$tool}.exe"
            ];
            foreach ($candidates as $c) {
                if (file_exists($c)) return $c;
            }
            return "{$tool}.exe";
        }
        return $tool; // Linux / cPanel (encontrado en PATH)
    }

    public function generarBackup(): void {
        $date = date('Y-m-d_H-i-s');
        $fileName = "tenki_backup_{$date}.sql";
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $fileName;

        $binary = $this->getBinary('mysqldump');
        $passParam = !empty($this->dbPass) ? '--password=' . escapeshellarg($this->dbPass) : '';

        $command = sprintf(
            '%s --host=%s --user=%s %s %s > %s 2>&1',
            escapeshellcmd($binary),
            escapeshellarg($this->dbHost),
            escapeshellarg($this->dbUser),
            $passParam,
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
            if (file_exists($tmpPath)) unlink($tmpPath);
            throw new Exception("Error al ejecutar mysqldump. Verifique que mysqldump esté disponible en el servidor.");
        }
    }

    public function restaurarBackup(string $sqlFilePath): bool {
        if (!file_exists($sqlFilePath)) {
            return false;
        }

        $binary = $this->getBinary('mysql');
        $passParam = !empty($this->dbPass) ? '--password=' . escapeshellarg($this->dbPass) : '';

        $command = sprintf(
            '%s --host=%s --user=%s %s %s < %s',
            escapeshellcmd($binary),
            escapeshellarg($this->dbHost),
            escapeshellarg($this->dbUser),
            $passParam,
            escapeshellarg($this->dbName),
            escapeshellarg($sqlFilePath)
        );

        exec($command, $output, $returnVar);

        if (file_exists($sqlFilePath)) {
            unlink($sqlFilePath);
        }

        return $returnVar === 0;
    }
}
