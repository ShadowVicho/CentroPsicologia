<?php
// db_users_config.php

// Configuración de la base de datos para usuarios
define('DB_SERVER_USERS', 'localhost');
define('DB_USERNAME_USERS', 'root'); // Cambia si es necesario
define('DB_PASSWORD_USERS', 'marco123'); // Cambia si es necesario
define('DB_NAME_USERS', 'calendario_db'); // Tu base de datos actual

// Conexión a la base de datos
$conn_users = new mysqli(DB_SERVER_USERS, DB_USERNAME_USERS, DB_PASSWORD_USERS, DB_NAME_USERS);

// Verificar conexión
if ($conn_users->connect_error) {
    die("Error de conexión a la base de datos de usuarios: " . $conn_users->connect_error);
}

// Establecer conjunto de caracteres
$conn_users->set_charset("utf8mb4");
?>
