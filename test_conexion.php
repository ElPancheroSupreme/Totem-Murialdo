<?php
$host = "tu-endpoint-de-AWS-rds.amazonaws.com";
$user = "admin";
$pass = "tu-contraseña";
$db   = "nombre_de_tu_base";
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Error al conectar: " . $conn->connect_error);
}
echo "✅ Conexión exitosa a la base de datos!";
?>
