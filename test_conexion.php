<?php
$host = "totem-bd.cqjg0u2k0i35.us-east-1.rds.amazonaws.com";
$user = "admin";
$pass = "Demetrio72w!";
$db   = "bg02";
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Error al conectar: " . $conn->connect_error);
}
echo "✅ Conexión exitosa a la base de datos!";
?>
