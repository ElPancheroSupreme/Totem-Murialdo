<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>Test Inserción</title>
<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.error{background:#f8d7da;padding:15px;margin:10px 0;border-radius:4px;border-left:4px solid #dc3545}
.success{background:#d4edda;padding:15px;margin:10px 0;border-radius:4px;border-left:4px solid #00a650}
.info{background:#d1ecf1;padding:15px;margin:10px 0;border-radius:4px;border-left:4px solid #0c5460}
table{width:100%;border-collapse:collapse;background:white;margin:10px 0}
th{background:#3A9A53;color:white;padding:12px;text-align:left}
td{padding:10px;border-bottom:1px solid #ddd}
code{background:#e9ecef;padding:2px 6px;border-radius:3px}
</style>
</head>
<body>
<h1>Test Inserción Pedido</h1>
<?php
try {
    echo "<div class='info'>Conectando...</div>";
    $pdo = new PDO('mysql:host=192.168.101.93;dbname=bg02;charset=utf8','BG02','St2025#QkcwMg',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    echo "<div class='success'> Conectado</div>";
    $ts = round(microtime(true)*1000);
    $rnd = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'),0,9);
    $ext = "CP_{$ts}_{$rnd}";
    $num = 'TEST-'.time();
    $monto = 2500;
    echo "<div class='info'>External: <code>$ext</code></div>";
    $stmt = $pdo->prepare("INSERT INTO pedidos(numero_pedido,external_reference,monto_total,metodo_pago,estado,creado_en)VALUES(?,?,?,'VIRTUAL','PREPARACION',NOW())");
    $stmt->execute([$num,$ext,$monto]);
    $id = $pdo->lastInsertId();
    echo "<div class='success'> Pedido insertado ID: $id</div>";
    $stmt2 = $pdo->prepare("INSERT INTO items_pedido(id_pedido,id_producto,cantidad,precio_unitario,precio_total_item,es_personalizable)VALUES(?,?,?,?,?,?)");
    $stmt2->execute([$id,21,1,1100,1100,0]);
    echo "<div class='success'>✅ Item insertado ID: ".$pdo->lastInsertId()."</div>";
    $ver = $pdo->prepare("SELECT * FROM pedidos WHERE id_pedido=?");
    $ver->execute([$id]);
    $p = $ver->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Pedido verificado:</h2><table><tr><th>Campo</th><th>Valor</th></tr>";
    foreach($p as $k=>$v){
        $bg = ($k==='external_reference')?'background:#d4edda':'';
        echo "<tr style='$bg'><td><code>$k</code></td><td>".htmlspecialchars($v??'NULL')."</td></tr>";
    }
    echo "</table>";
    $last = $pdo->query("SELECT id_pedido,numero_pedido,external_reference,monto_total,estado FROM pedidos ORDER BY id_pedido DESC LIMIT 5");
    echo "<h2>Últimos 5 pedidos:</h2><table><tr><th>ID</th><th>Número</th><th>External Ref</th><th>Monto</th><th>Estado</th></tr>";
    while($r=$last->fetch(PDO::FETCH_ASSOC)){
        $bg=($r['id_pedido']==$id)?'background:#d4edda':'';
        $ex=$r['external_reference']??'<i>NULL</i>';
        echo "<tr style='$bg'><td>$r[id_pedido]</td><td>$r[numero_pedido]</td><td><small>$ex</small></td><td>$r[monto_total]</td><td>$r[estado]</td></tr>";
    }
    echo "</table>";
}catch(Exception $e){
    echo "<div class='error'> Error: ".htmlspecialchars($e->getMessage())."</div>";
}
?>
</body>
</html>
