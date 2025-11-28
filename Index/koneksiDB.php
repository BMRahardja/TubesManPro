<?php
$serverName = "localhost";
$connectionOptions = [
    "Database" => "MIBD",
    "TrustServerCertificate" => true
];

$conn = sqlsrv_connect($serverName, $connectionOptions);
?>
