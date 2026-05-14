<?php
declare(strict_types=1);

// TODO: colocar la direccion correcta de esta funcion legacy si vive en otro repositorio/servidor.
if (!defined('DB_SERVER_NAME')) {
    define('DB_SERVER_NAME', getenv('BOOKING_DB_HOST') ?: 'tcp:y8xavxasp6.database.windows.net,1433');
}
if (!defined('DB_DATABASE_NAME')) {
    define('DB_DATABASE_NAME', getenv('BOOKING_DB_NAME') ?: 'bdoptol');
}

function ejecutarQueryAzureSQLServerV2($query, $onlyRead = false, $validar = false, $returnStmt = false)
{
    if ($validar) {
        return validarSQL($query);
    }

    sqlsrv_configure('WarningsReturnAsErrors', 0);
    $connectionOptions = [
        'Database' => DB_DATABASE_NAME,
        'Uid' => $onlyRead ? (getenv('DB_USERNAME_READ') ?: getenv('DB_USERNAME') ?: '') : (getenv('DB_USERNAME') ?: ''),
        'PWD' => $onlyRead ? (getenv('DB_PASSWORD_READ') ?: getenv('DB_PASSWORD') ?: '') : (getenv('DB_PASSWORD') ?: ''),
        'TrustServerCertificate' => true,
        'Encrypt' => false,
        'LoginTimeout' => 30,
        'CharacterSet' => 'UTF-8',
    ];

    $conn = sqlsrv_connect(DB_SERVER_NAME, $connectionOptions);
    if (!$conn) {
        throw new RuntimeException('Error de conexión SQL Server');
    }

    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        sqlsrv_close($conn);
        throw new RuntimeException('Error en la consulta SQL Server');
    }

    $resultados = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $resultados[] = (object) $row;
    }

    if ($returnStmt) {
        return ['stmt' => $stmt, 'data' => $resultados, 'conn' => $conn];
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    return $resultados;
}

function validarSQL($query): array
{
    return ['valido' => true, 'error' => null];
}
