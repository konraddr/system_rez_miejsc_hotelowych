<?php
session_start(); include 'db.php';
if($_SERVER['REQUEST_METHOD']=='POST'){
    try{
        $sql = "INSERT INTO uzytkownik (imie, nazwisko, email, haslo) VALUES (?, ?, ?, ?)";
        $conn->prepare($sql)->execute([$_POST['imie'], $_POST['nazwisko'], $_POST['email'], $_POST['haslo']]);
        header("Location: login.php");
    } catch(Exception $e) { $err = "Email zajęty!"; }
}
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="style.css"></head><body>
<div class="box" style="max-width:400px">
    <h3>Rejestracja</h3>
    <?php if(isset($err)) echo "<p style='color:red'>$err</p>"; ?>
    <form method="POST">
        Imię: <input type="text" name="imie" required>
        Nazwisko: <input type="text" name="nazwisko" required>
        Email: <input type="email" name="email" required>
        Hasło: <input type="password" name="haslo" required>
        <button class="btn" style="width:100%">Załóż konto</button>
    </form>
</div>
</body></html>