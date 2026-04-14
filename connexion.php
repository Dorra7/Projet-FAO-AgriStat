<?php
$host = '127.0.0.1'; // On oublie "localhost", on utilise l'IP directe
$port = '3309';      // CHANGE ICI SI XAMPP AFFICHE 3306
$db   = 'projet_fao';
$user = 'root';
$pass = '';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // Si on arrive ici, c'est gagné !
} catch (PDOException $e) {
    echo "<h3>❌ Erreur de connexion</h3>";
    echo "Port tenté : $port <br>";
    echo "Message : " . $e->getMessage();
    exit;
}