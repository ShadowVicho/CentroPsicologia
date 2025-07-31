<?php
// edit_reservation.php
require_once 'db_config.php';

$reservation = null;

// Verificar si se ha enviado el ID de la reserva
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM reservas WHERE id = $id";
    $result = $conn->query($sql);
    $reservation = $result->fetch_assoc();
}

// Manejar la actualización de la reserva
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $rut = $_POST['rut'];
    $correo = $_POST['correo'];
    $telefono = $_POST['telefono'];
    $fecha_reserva = $_POST['fecha_reserva'];
    $hora_reserva = $_POST['hora_reserva'];

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
    <title>Modificar Reserva</title>
</head>
<body>
    <h1>Modificar Reserva</h1>
    <?php if ($reservation): ?>
        <form method="POST" action="">
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($reservation['nombre']); ?>" required>

            <label for="apellido">Apellido:</label>
            <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($reservation['apellido']); ?>" required>

            <label for="rut">RUT:</label>
            <input type="text" id="rut" name="rut" value="<?php echo htmlspecialchars($reservation['rut']); ?>" required>

            <label for="correo">Correo Electrónico:</label>
            <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($reservation['correo_electronico']); ?>" required>

            <label for="telefono">Número de Teléfono:</label>
            <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($reservation['numero_telefono']); ?>" required>

            <label for="fecha_reserva">Fecha de Reserva:</label>
            <input type="date" id="fecha_reserva" name="fecha_reserva" value="<?php echo htmlspecialchars($reservation['fecha_reserva']); ?>" required>

            <label for="hora_reserva">Hora de Reserva:</label>
            <input type="time" id="hora_reserva" name="hora_reserva" value="<?php echo htmlspecialchars($reservation['hora_reserva']); ?>" required>

            <button type="submit">Actualizar Reserva</button>
        </form>
    <?php else: ?>
        <p>No se encontró la reserva.</p>
    <?php endif; ?>
</body>
</html>
