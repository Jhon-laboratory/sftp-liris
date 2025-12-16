<?php
require __DIR__ . '/phpseclib/vendor/autoload.php';
use phpseclib3\Net\SFTP;

$host = '40.121.159.89';
$port = 22;
$user = 'lirisprd';
$pass = 'lirisPROD01';

$sftp = new SFTP($host, $port);

if (!$sftp->login($user, $pass)) {
    exit("❌ Error: No se pudo conectar");
}

echo "✅ Conectado correctamente<br>";

// carpeta válida: /ENVIA
$remoteFile = '/ENVIA/prueba.txt';

$contenido = "Archivo enviado desde PHP correctamente.\nHora: " . date("Y-m-d H:i:s");

if ($sftp->put($remoteFile, $contenido)) {
    echo "✅ Archivo enviado correctamente a /ENVIA/prueba.txt";
} else {
    echo "❌ Error al subir el archivo";
}
