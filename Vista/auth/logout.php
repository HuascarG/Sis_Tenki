<?php
define('ROOT', dirname(dirname(__DIR__)));
require_once ROOT . '/Config/database.php';
require_once ROOT . '/Config/config.php';
require_once ROOT . '/Config/auth.php';
require_once ROOT . '/Controlador/AuthController.php';

(new AuthController())->logout();
header('Location: ' . BASE_URL . 'Vista/auth/login.php');
exit;
