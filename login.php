<?php
session_start(); include 'db.php';
if($_SERVER['REQUEST_METHOD']=='POST'){
    $stmt = $conn->prepare("SELECT * FROM uzytkownik WHERE email=? AND haslo=?");
    $stmt->execute([$_POST['email'], $_POST['haslo']]);
    $user = $stmt->fetch();
    if($user){
        $_SESSION['uid'] = $user['id_uzytkownika'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['rola'] = $user['rola'];
        $_SESSION['hid'] = $user['manager_hotel_id']; // Dla Managera
        
        if(isset($_SESSION['temp'])) header("Location: rezerwacja.php");
        else if(in_array($user['rola'], ['admin', 'manager'])) header("Location: admin.php");
        else header("Location: index.php");
        exit;
    } else { $err = "Błędne dane! (admin@hotel.pl / 1234)"; }
}
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="style.css"></head><body>
<div class="box" style="max-width:400px">
    <h3>Zaloguj się</h3>
    <?php if(isset($err)) echo "<p style='color:red'>$err</p>"; ?>
    <form method="POST">
        Email: <input type="text" name="email" value="anna@test.pl">
        Hasło: <input type="password" name="haslo" value="1234">
        <button class="btn" style="width:100%">Wejdź</button>
    </form>
    <br><a href="register.php">Nie masz konta?</a>
</div>
</body></html>