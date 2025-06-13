<?php
$host = 'localhost';
$dbname = 'dbqv6kcasccnub';
$username = 'uxgukysg8xcbd';
$password = '6imcip8yfmic';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please try again later.");
}
?>
