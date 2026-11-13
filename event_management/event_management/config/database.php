<?php
$host = 'localhost';
$dbname = 'quanly_sukien';
$username = 'root';
$password = '';
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Không thể kết nối cơ sở dữ liệu. Hãy kiểm tra XAMPP và import file database.sql.');
}
?>