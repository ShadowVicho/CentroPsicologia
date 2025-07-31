<?php
require_once 'db_config.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Obtener detalles de la reserva
    $sql = "SELECT * FROM reservas WHERE id = $id";
    $result = $conn->query($sql);
    $reservation = $result->fetch_assoc();

    if ($reservation) {
        // Enviar correos de confirmación
        $mail = new PHPMailer(true);
        try {
            // Configuración del servidor SMTP
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';

            // Correo para el cliente
            $mail->setFrom(MAIL_USERNAME, 'Sistema de Reservas');
            $mail->addAddress($reservation['correo_electronico'], $reservation['nombre'] . ' ' . $reservation['apellido']);
            $mail->Subject = 'Confirmación de Cita';
            $mail->isHTML(true);
            $mail->Body = "
                <html>
                <head>
                    <title>Confirmación de Cita</title>
                </head>
                <body>
                    <h2>¡Hola {$reservation['nombre']}!</h2>
                    <p>Tu cita para el día <strong>{$reservation['fecha_reserva']}</strong> ha sido confirmada.</p>
                    <p>Gracias por elegirnos.</p>
                </body>
                </html>
            ";
            $mail->send();

            // Correo para el administrador
            $mail->clearAddresses();
            $mail->addAddress(ADMIN_EMAIL, 'Administrador');
            $mail->Subject = 'Cita Confirmada';
            $mail->Body = "
                <html>
                <head>
                    <title>Cita Confirmada</title>
                </head>
                <body>
                    <h2>¡Cita Confirmada!</h2>
                    <p>La cita del cliente <strong>{$reservation['nombre']} {$reservation['apellido']}</strong> para el día <strong>{$reservation['fecha_reserva']}</strong> ha sido confirmada.</p>
                </body>
                </html>
            ";
            $mail->send();

            echo "Cita confirmada. Se ha enviado un correo de confirmación.";
        } catch (Exception $e) {
            echo "Error al enviar el correo: {$mail->ErrorInfo}";
        }
    } else {
        echo "No se encontró la reserva.";
    }
} else {
    echo "ID de reserva no proporcionado.";
}

$conn->close();
?>
