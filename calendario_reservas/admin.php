<?php
// admin.php
require_once 'db_config.php';
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inicializar variable de búsqueda
$searchTerm = '';

// Verificar si se ha enviado el formulario de búsqueda
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
}

// Construir la consulta SQL
$sql = "SELECT * FROM reservas WHERE 1=1"; // 1=1 para facilitar la concatenación de condiciones

if (!empty($searchTerm)) {
    $sql .= " AND (rut LIKE '%" . $conn->real_escape_string($searchTerm) . "%' 
                OR nombre LIKE '%" . $conn->real_escape_string($searchTerm) . "%' 
                OR correo_electronico LIKE '%" . $conn->real_escape_string($searchTerm) . "%')";
}

$sql .= " ORDER BY fecha_reserva DESC, hora_reserva ASC"; // Ordenar resultados
$result = $conn->query($sql);

$reservations = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
}

// Función para enviar notificaciones de modificación
function sendModificationNotification($clientEmail, $adminEmail, $details, $action) {
    $mail = new PHPMailer(true); // Habilita excepciones

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
        $mail->addAddress($clientEmail); // Correo del cliente
        $mail->Subject = 'Notificación de Modificación de Reserva';
        $mail->isHTML(true);
        $mail->Body = "
            <html>
            <head>
                <title>Notificación de Modificación</title>
            </head>
            <body>
                <h2>¡Hola!</h2>
                <p>Tu reserva ha sido {$action}.</p>
                <p><strong>Detalles de la reserva:</strong></p>
                <ul>
                    <li><strong>Fecha:</strong> {$details['fecha']}</li>
                    <li><strong>Hora:</strong> {$details['hora']}</li>
                    <li><strong>Nombre Completo:</strong> {$details['nombre']} {$details['apellido']}</li>
                </ul>
                <p>Gracias por elegirnos.</p>
            </body>
            </html>
        ";
        $mail->send(); // Enviar correo al cliente

        // Correo para el administrador
        $mail->clearAddresses(); // Limpiar direcciones
        $mail->addAddress($adminEmail); // Correo del administrador
        $mail->Subject = 'Reserva Modificada';
        $mail->Body = "
            <html>
            <head>
                <title>Actualización de Reserva</title>
            </head>
            <body>
                <h2>¡Actualización de Reserva!</h2>
                <p>Se ha {$action} una reserva.</p>
                <p><strong>Detalles de la reserva:</strong></p>
                <ul>
                    <li><strong>Fecha:</strong> {$details['fecha']}</li>
                    <li><strong>Hora:</strong> {$details['hora']}</li>
                    <li><strong>Nombre Completo:</strong> {$details['nombre']} {$details['apellido']}</li>
                </ul>
                <p>Revisa el panel de administración para más detalles.</p>
            </body>
            </html>
        ";
        $mail->send(); // Enviar correo al administrador

    } catch (Exception $e) {
        echo "Error al enviar el correo: {$mail->ErrorInfo}";
    }
}

// Manejar eliminación de reservas
if (isset($_GET['delete_id'])) {
    $deleteId = $_GET['delete_id'];
    $deleteSql = "SELECT * FROM reservas WHERE id = " . intval($deleteId);
    $deleteResult = $conn->query($deleteSql);
    $reservationToDelete = $deleteResult->fetch_assoc();

    if ($reservationToDelete) {
        // Enviar correo de notificación
        sendModificationNotification($reservationToDelete['correo_electronico'], ADMIN_EMAIL, $reservationToDelete, 'eliminada');
        
        // Eliminar la reserva
        $conn->query("DELETE FROM reservas WHERE id = " . intval($deleteId));
        header("Location: admin.php"); // Redirigir después de eliminar
        exit();
    }
}

// Manejar la actualización de la reserva
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $rut = $_POST['rut'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $fecha_reserva = $_POST['fecha_reserva'];
    $hora_reserva = $_POST['hora_reserva'];

    // Obtener los detalles anteriores para enviar en el correo
    $previousSql = "SELECT * FROM reservas WHERE id = " . intval($id);
    $previousResult = $conn->query($previousSql);
    $previousReservation = $previousResult->fetch_assoc();

    $updateSql = "UPDATE reservas SET 
        nombre = '$nombre', 
        apellido = '$apellido', 
        rut = '$rut', 
        correo_electronico = '$correo', 
        numero_telefono = '$telefono', 
        fecha_reserva = '$fecha_reserva', 
        hora_reserva = '$hora_reserva' 
        WHERE id = $id";

    if ($conn->query($updateSql) === TRUE) {
        // Enviar correo de notificación sobre la actualización
        $details = [
            'fecha' => $fecha_reserva,
            'hora' => $hora_reserva,
            'nombre' => $nombre,
            'apellido' => $apellido
        ];
        sendModificationNotification($previousReservation['correo_electronico'], ADMIN_EMAIL, $details, 'actualizada');
        
        header("Location: admin.php"); // Redirigir después de actualizar
        exit();
    } else {
        echo "Error al actualizar la reserva: " . $conn->error;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración de Reservas</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #166088;
            margin-bottom: 30px;
        }
        .search-box {
            margin-bottom: 20px;
            text-align: center;
        }
        .search-box input {
            padding: 10px;
            width: 300px;
            border: 1px solid #ccc;
            border-radius: 4px;
            transition: border-color 0.3s;
        }
        .search-box input:focus {
            border-color: #4a6fa5;
            outline: none;
        }
        .search-box button {
            padding: 10px 15px;
            background-color: #4a6fa5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .search-box button:hover {
            background-color: #3a5a8a;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #4a6fa5;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .no-reservations {
            text-align: center;
            color: #9e9e9e;
            font-style: italic;
            padding: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-buttons a, .action-buttons button {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: white;
        }
        .action-buttons a {
            background-color: #e74c3c; /* Color rojo para eliminar */
        }
        .action-buttons a:hover {
            background-color: #c0392b; /* Color rojo oscuro al pasar el mouse */
        }
        .action-buttons button {
            background-color: #4a6fa5; /* Color azul para modificar */
        }
        .action-buttons button:hover {
            background-color: #3a5a8a; /* Color azul oscuro al pasar el mouse */
        }
        .edit-form {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .edit-form label {
            display: block;
            margin: 10px 0 5px;
        }
        .edit-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .edit-form button {
            padding: 10px 15px;
            background-color: #4a6fa5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .edit-form button:hover {
            background-color: #3a5a8a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Panel de Administración de Reservas</h1>

        <!-- Formulario de búsqueda -->
        <div class="search-box">
            <form method="POST" action="">
                <input type="text" name="search" placeholder="Buscar por RUT, Nombre o Correo..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit">Buscar</button>
            </form>
        </div>

        <?php if (empty($reservations)): ?>
            <div class="no-reservations">No hay reservas registradas.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>RUT</th>
                        <th>Correo</th>
                        <th>Teléfono</th>
                        <th>Fecha Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['fecha_reserva']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['hora_reserva']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['apellido']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['rut']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['correo_electronico']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['numero_telefono']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['fecha_creacion']); ?></td>
                            <td class="action-buttons">
                                <a href="?delete_id=<?php echo htmlspecialchars($reservation['id']); ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar esta reserva?');">Eliminar</a>
                                <button onclick="showEditForm(<?php echo htmlspecialchars($reservation['id']); ?>, '<?php echo htmlspecialchars($reservation['nombre']); ?>', '<?php echo htmlspecialchars($reservation['apellido']); ?>', '<?php echo htmlspecialchars($reservation['rut']); ?>', '<?php echo htmlspecialchars($reservation['correo_electronico']); ?>', '<?php echo htmlspecialchars($reservation['numero_telefono']); ?>', '<?php echo htmlspecialchars($reservation['fecha_reserva']); ?>', '<?php echo htmlspecialchars($reservation['hora_reserva']); ?>')">Modificar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Formulario de edición -->
        <div id="editForm" class="edit-form" style="display:none;">
            <h2>Modificar Reserva</h2>
            <form method="POST" action="">
                <input type="hidden" name="id" id="editId">
                <label for="nombre">Nombre:</label>
                <input type="text" id="editNombre" name="nombre" required>

                <label for="apellido">Apellido:</label>
                <input type="text" id="editApellido" name="apellido" required>

                <label for="rut">RUT:</label>
                <input type="text" id="editRut" name="rut" required>

                <label for="correo">Correo Electrónico:</label>
                <input type="email" id="editCorreo" name="correo" required>

                <label for="telefono">Número de Teléfono:</label>
                <input type="tel" id="editTelefono" name="telefono" required>

                <label for="fecha_reserva">Fecha de Reserva:</label>
                <input type="date" id="editFechaReserva" name="fecha_reserva" required>

                <label for="hora_reserva">Hora de Reserva:</label>
                <input type="time" id="editHoraReserva" name="hora_reserva" required>

                <button type="submit" name="update">Actualizar Reserva</button>
                <button type="button" onclick="hideEditForm()">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function showEditForm(id, nombre, apellido, rut, correo, telefono, fecha_reserva, hora_reserva) {
            document.getElementById('editId').value = id;
            document.getElementById('editNombre').value = nombre;
            document.getElementById('editApellido').value = apellido;
            document.getElementById('editRut').value = rut;
            document.getElementById('editCorreo').value = correo;
            document.getElementById('editTelefono').value = telefono;
            document.getElementById('editFechaReserva').value = fecha_reserva;
            document.getElementById('editHoraReserva').value = hora_reserva;
            document.getElementById('editForm').style.display = 'block';
        }

        function hideEditForm() {
            document.getElementById('editForm').style.display = 'none';
        }
    </script>
</body>
</html>
