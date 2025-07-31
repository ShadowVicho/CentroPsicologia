<?php
// db_config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Cambia esto si es necesario
define('DB_PASSWORD', 'marco123'); // Cambia esto si es necesario
define('DB_NAME', 'calendario_db');

// Configuración de correo electrónico
define('MAIL_HOST', 'smtp.gmail.com'); // Ej: 'smtp.gmail.com' o 'smtp-mail.outlook.com'
define('MAIL_USERNAME', 'vega72183@gmail.com'); // Tu dirección de correo para enviar
define('MAIL_PASSWORD', 'qxjb uygr ptzy iuzw'); // La contraseña de tu correo
define('MAIL_PORT', 587); // Puerto SMTP (587 para TLS, 465 para SSL)
define('MAIL_ENCRYPTION', 'tls'); // 'ssl' o 'tls'

define('ADMIN_EMAIL', 'likailagos@gmail.com'); // Correo del administrador

// Intentar conectar a la base de datos MySQL
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres a utf8mb4
$conn->set_charset("utf8mb4");
?>
