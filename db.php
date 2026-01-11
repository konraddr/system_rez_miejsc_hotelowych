<?php
try {
    $conn = new PDO("pgsql:host=localhost;port=5432;dbname=hotel_db", "postgres", "1234");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
     die("Błąd bazy: " . $e->getMessage()); 
     }
?>