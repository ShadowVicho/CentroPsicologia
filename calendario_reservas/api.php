<?php
// api.php
     ini_set('display_errors', 1);
     ini_set('display_startup_errors', 1);
     error_reporting(E_ALL);
     
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Permite solicitudes desde cualquier origen (para desarrollo)
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'db_config.php';

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// require 'vendor/autoload.php'; // Comentar si no usas Composer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetReservations($conn);
        break;
    case 'POST':
        handlePostReservation($conn);
        break;
    case 'OPTIONS':
        // Manejar preflight requests para CORS
        http_response_code(200);
        exit();
    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['message' => 'Método no permitido']);
        break;
}

function handleGetReservations($conn) {
    $sql = "SELECT slot_key FROM reservas";
    $result = $conn->query($sql);

    $reservedSlots = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reservedSlots[] = $row['slot_key'];
        }
        echo json_encode($reservedSlots);
    } else {
        // Si hay un error en la consulta, lo registramos y enviamos un mensaje de error JSON
        error_log("Error en la consulta GET: " . $conn->error);
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Error en la consulta de reservas: ' . $conn->error]);
    }
}

function handlePostReservation($conn) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['slotKey'], $data['fechaReserva'], $data['horaReserva'], $data['nombre'], $data['apellido'], $data['rut'], $data['correoElectronico'], $data['numeroTelefono'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['message' => 'Datos incompletos']);
        return;
    }

    $slotKey = $conn->real_escape_string($data['slotKey']);
    $fechaReserva = $conn->real_escape_string($data['fechaReserva']);
    $horaReserva = $conn->real_escape_string($data['horaReserva']);
    $nombre = $conn->real_escape_string($data['nombre']);
    $apellido = $conn->real_escape_string($data['apellido']);
    $rut = $conn->real_escape_string($data['rut']);
    $correoElectronico = $conn->real_escape_string($data['correoElectronico']);
    $numeroTelefono = $conn->real_escape_string($data['numeroTelefono']);

    // Verificar si la franja ya está reservada para evitar duplicados
    $checkSql = "SELECT id FROM reservas WHERE slot_key = '$slotKey'";
    $checkResult = $conn->query($checkSql);
    if ($checkResult && $checkResult->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['message' => 'Esta franja horaria ya está reservada.']);
        return;
    }

    $sql = "INSERT INTO reservas (slot_key, fecha_reserva, hora_reserva, nombre, apellido, rut, correo_electronico, numero_telefono) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $slotKey, $fechaReserva, $horaReserva, $nombre, $apellido, $rut, $correoElectronico, $numeroTelefono);

    if ($stmt->execute()) {
        // Si la reserva se guarda, intentar enviar correos
        $reservationDetails = [
            'fecha' => $fechaReserva,
            'hora' => $horaReserva,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'rut' => $rut,
            'correo' => $correoElectronico,
            'telefono' => $numeroTelefono
        ];
        sendReservationEmails($reservationDetails);

        echo json_encode(['message' => 'Reserva guardada exitosamente y correos enviados.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['message' => 'Error al guardar la reserva: ' . $stmt->error]);
    }

    $stmt->close();
}

function sendReservationEmails($details) {
    $mail = new PHPMailer(true); // Pasar `true` habilita excepciones

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8'; // Asegura que los caracteres especiales se envíen correctamente

        // Remitente
        $mail->setFrom(MAIL_USERNAME, 'Sistema de Reservas');

        // Correo para el cliente
        $mail->addAddress($details['correo'], $details['nombre'] . ' ' . $details['apellido']);
        $mail->Subject = 'Confirmación de tu Reserva';
        $mail->isHTML(true);
        $mail->Body = "
            <html>
            <head>
                <title>Confirmación de Reserva</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        background-color: #ffffff;
                        border-radius: 8px;
                        padding: 20px;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    }
                    h2 {
                        color: #333;
                    }
                    p {
                        color: #555;
                    }
                    .details {
                        margin: 20px 0;
                        padding: 10px;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        background-color: #f9f9f9;
                    }
                    .footer {
                        margin-top: 20px;
                        font-size: 12px;
                        color: #999;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>¡Hola {$details['nombre']}!</h2>
                    <p>Tu reserva ha sido confirmada exitosamente.</p>
                    <div class='details'>
                        <strong>Detalles de tu reserva:</strong>
                        <ul>
                            <li><strong>Fecha:</strong> {$details['fecha']}</li>
                            <li><strong>Hora:</strong> {$details['hora']}</li>
                            <li><strong>Nombre Completo:</strong> {$details['nombre']} {$details['apellido']}</li>
                            <li><strong>RUT:</strong> {$details['rut']}</li>
                            <li><strong>Correo:</strong> {$details['correo']}</li>
                            <li><strong>Teléfono:</strong> {$details['telefono']}</li>
                        </ul>
                    </div>
                    <p>¡Te esperamos!</p>
                    <p class='footer'>Atentamente,<br>El equipo de Reservas</p>
                </div>
            </body>
            </html>
        ";
        $mail->send();
        $mail->clearAddresses(); // Limpiar direcciones para el siguiente correo

        // Correo para el administrador
        $mail->addAddress(ADMIN_EMAIL, 'Administrador');
        $mail->Subject = 'Nueva Reserva Registrada';
        $mail->Body = "
            <html>
            <head>
                <title>Nueva Reserva</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        background-color: #ffffff;
                        border-radius: 8px;
                        padding: 20px;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    }
                    h2 {
                        color: #333;
                    }
                    p {
                        color: #555;
                    }
                    .details {
                        margin: 20px 0;
                        padding: 10px;
                        border: 1px solid #ddd;
                        border-radius: 5px;
                        background-color: #f9f9f9;
                    }
                    .footer {
                        margin-top: 20px;
                        font-size: 12px;
                        color: #999;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>¡Nueva Reserva!</h2>
                    <p>Se ha registrado una nueva reserva en el calendario.</p>
                    <div class='details'>
                        <strong>Detalles de la reserva:</strong>
                        <ul>
                            <li><strong>Fecha:</strong> {$details['fecha']}</li>
                            <li><strong>Hora:</strong> {$details['hora']}</li>
                            <li><strong>Nombre Completo:</strong> {$details['nombre']} {$details['apellido']}</li>
                            <li><strong>RUT:</strong> {$details['rut']}</li>
                            <li><strong>Correo:</strong> {$details['correo']}</li>
                            <li><strong>Teléfono:</strong> {$details['telefono']}</li>
                        </ul>
                    </div>
                    <p>Revisa el panel de administración para más detalles.</p>
                    <p class='footer'>Atentamente,<br>El equipo de Reservas</p>
                </div>
            </body>
            </html>
        ";
        $mail->send();

    } catch (Exception $e) {
        echo "Error al enviar el correo: {$mail->ErrorInfo}";
    }
}

$conn->close();
?>
