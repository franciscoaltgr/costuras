<?php
/**
 * Capa de Conexión a Base de Datos con PDO
 * Archivo: conexion.php
 * 
 * Implementa conexión segura mediante PDO con manejo de excepciones (PDO::ERRMODE_EXCEPTION),
 * soporte completo para UTF-8 en español (utf8mb4_unicode_ci) y desactivación de emulación de sentencias preparadas para
 * garantizar seguridad frente a SQL Injection y atomicidad en transacciones ACID.
 */

// Configuración profesional de codificación para PHP a nivel de entorno
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

// Parámetros de configuración de la Base de Datos
$dbHost    = getenv('DB_HOST') ?: 'localhost';
$dbPort    = getenv('DB_PORT') ?: '3306';
$dbName    = getenv('DB_NAME') ?: 'taller_costuras';
$dbUser    = getenv('DB_USER') ?: 'root';
$dbPass    = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";

$options = [
    // Lanza PDOException en caso de error SQL, permitiendo capturarlo en bloques try/catch
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    
    // Retorna filas como arreglos asociativos por defecto
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // Deshabilita la emulación de consultas preparadas para usar sentencias preparadas nativas
    PDO::ATTR_EMULATE_PREPARES   => false,
    
    // Forzar collation y charset UTF-8 en español en la sesión MySQL
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
} catch (PDOException $e) {
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error de Conexión - Grill Guard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex align-items-center min-vh-100">
        <div class="container" style="max-width: 600px;">
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Error de Conexión a la Base de Datos</h5>
                </div>
                <div class="card-body">
                    <p class="text-danger fw-bold">No se pudo establecer la conexión con MySQL mediante PDO.</p>
                    <p><strong>Detalle del error:</strong> <?= htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                    <hr>
                    <h6>Verificaciones sugeridas:</h6>
                    <ol class="small text-muted">
                        <li>Verifica que MySQL/MariaDB esté iniciado en XAMPP o Laragon.</li>
                        <li>Asegúrate de haber importado el archivo <code>schema.sql</code> para crear la base de datos <code>taller_costuras</code>.</li>
                        <li>Verifica que las credenciales en <code>conexion.php</code> coincidan con tu servidor local (Usuario: <code>root</code>, Contraseña vacía por defecto).</li>
                    </ol>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Función auxiliar para obtener la instancia activa de PDO
 * @return PDO
 */
function getDBConnection(): PDO {
    global $pdo;
    return $pdo;
}
