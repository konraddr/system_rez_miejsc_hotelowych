<?php
session_start(); include 'db.php';
if(!isset($_SESSION['uid'])) header("Location: login.php");

$uid = $_SESSION['uid']; 
$msg = "";
$msg_type = "";

// 1. ZMIANA DANYCH
if(isset($_POST['upd'])){
    try {
        $conn->prepare("UPDATE uzytkownik SET imie=?, nazwisko=? WHERE id_uzytkownika=?")->execute([$_POST['imie'], $_POST['nazwisko'], $uid]);
        $msg="Zaktualizowano dane!"; $msg_type = "success";
    } catch(Exception $e) { $msg = "Błąd: " . $e->getMessage(); $msg_type = "error"; }
}

// 2. ZMIANA HASŁA
if(isset($_POST['change_pass'])){
    try {
        $stmt = $conn->prepare("SELECT zmien_haslo(?, ?, ?)");
        $stmt->execute([$uid, $_POST['old_pass'], $_POST['new_pass']]);
        $wynik = $stmt->fetchColumn();
        $msg = $wynik;
        $msg_type = (strpos($wynik, 'SUKCES') !== false) ? "success" : "error";
    } catch(Exception $e) { $msg = "Błąd: " . $e->getMessage(); $msg_type = "error"; }
}

// 3. ANULOWANIE REZERWACJI (Logika SQL z karami)
if(isset($_POST['anuluj_rez'])){
    try {
        $stmt = $conn->prepare("SELECT anuluj_rezerwacje_klienta(:rid, :uid)");
        $stmt->execute([':rid'=>$_POST['rid'], ':uid'=>$uid]);
        $msg = $stmt->fetchColumn();
        $msg_type = (strpos($msg, 'Błąd') === false) ? "success" : "error";
    } catch(Exception $e) { $msg = "Błąd: ".$e->getMessage(); $msg_type = "error"; }
}

// 4. [ZMIANA] ZAPŁAĆ TERAZ (PŁATNOŚĆ ONLINE)
if(isset($_POST['pay_now'])){
    try {
        // Aktualizujemy tabelę płatności. Trigger 'trg_auto_oplacenie' w SQL sam zmieni status rezerwacji!
        $stmt = $conn->prepare("UPDATE platnosci SET status = 'oplacona' WHERE id_rezerwacji = ?");
        $stmt->execute([$_POST['rid']]);
        $msg = "Dziękujemy! Płatność przyjęta. Status rezerwacji: OPŁACONA.";
        $msg_type = "success";
    } catch(Exception $e) { $msg = "Błąd: ".$e->getMessage(); $msg_type = "error"; }
}

// USUWANIE KONTA
if(isset($_POST['del'])){
    $conn->prepare("DELETE FROM uzytkownik WHERE id_uzytkownika=?")->execute([$uid]);
    session_destroy(); header("Location: index.php");
}

$u = $conn->query("SELECT * FROM uzytkownik WHERE id_uzytkownika=".$uid)->fetch();
?>
<!DOCTYPE html><html><head>
    <link rel="stylesheet" href="style.css">
    <style>
        .msg-box { padding: 10px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head><body>

<div class="nav"><strong>Mój Profil</strong><a href="index.php">Wróć</a></div>

<div class="box">
    <?php if($msg): ?>
        <div class="msg-box <?php echo ($msg_type == 'success') ? 'msg-success' : 'msg-error'; ?>">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div style="display:flex; gap:30px; flex-wrap:wrap;">
        
        <div style="flex:1; min-width:300px;">
            <h3>Moje Dane</h3>
            <form method="POST" style="background:#f9f9f9; padding:15px; border-radius:5px;">
                <label>Imię</label><input type="text" name="imie" value="<?php echo $u['imie']; ?>" required>
                <label>Nazwisko</label><input type="text" name="nazwisko" value="<?php echo $u['nazwisko']; ?>" required>
                <button name="upd" class="btn">Zapisz Zmiany</button>
            </form>
            
            <h3 style="margin-top:20px;">Bezpieczeństwo</h3>
            <form method="POST" style="background:#f9f9f9; padding:15px; border-radius:5px;">
                <label>Zmiana hasła</label>
                <input type="password" name="old_pass" placeholder="Stare hasło">
                <input type="password" name="new_pass" placeholder="Nowe hasło">
                <button name="change_pass" class="btn" style="background:#555;">Zmień hasło</button>
            </form>
        </div>

        <div style="flex:2; min-width:400px;">
            <h3 style="margin-top:0;">Moje Rezerwacje</h3>
            <table style="font-size:14px;">
                <tr><th>Szczegóły</th><th>Status</th><th>Akcje</th></tr>
                <?php
                $sql = "SELECT r.*, p.nr_pokoj, h.nazwa, pl.rabat, p.pojemnosc 
                FROM rezerwacje r 
                JOIN pokoje p ON r.id_pokoj=p.id_pokoj 
                JOIN hotele h ON p.hotel_id=h.hotel_id
                LEFT JOIN platnosci pl ON r.id_rezerwacji=pl.id_rezerwacji 
                WHERE r.id_uzytkownika=$uid ORDER BY r.rezerwacja_od DESC";
                $res = $conn->query($sql);
                while($r=$res->fetch()){
                    $cena = $r['cena_ostateczna'];
                    
                    // [ZMIANA] Kolorowanie statusów
                    $stat_html = "<b>{$r['status']}</b>";
                    if($r['status'] == 'potwierdzona') $stat_html = "<span style='color:#d35400'>POTWIERDZONA<br><small>(Czeka na wpłatę/przyjazd)</small></span>";
                    if($r['status'] == 'oplacona') $stat_html = "<span style='color:green'>OPŁACONA </span>";
                    if($r['status'] == 'zrealizowana') $stat_html = "<span style='color:blue'>ZAKOŃCZONA</span>";
                    if(strpos($r['status'], 'anulowana') !== false) $stat_html = "<span style='color:gray'>ANULOWANA</span>";

                    echo "<tr>
                        <td>
                            <b>{$r['nazwa']}</b> (Pokój {$r['nr_pokoj']})<br>
                            {$r['rezerwacja_od']} - {$r['rezerwacja_do']}<br>
                            Koszt: <b>$cena PLN</b>
                        </td>
                        <td>$stat_html</td>
                        <td>
                            <div style='display:flex; gap:5px; flex-direction:column;'>";
                            
                    // [ZMIANA] OPCJA A: Przycisk Zapłać (Tylko jeśli potwierdzona)
                    if($r['status'] == 'potwierdzona') {
                        echo "<form method='POST'>
                                <input type='hidden' name='rid' value='{$r['id_rezerwacji']}'>
                                <button name='pay_now' class='btn btn-green' style='width:100%; padding:5px;'> Zapłać teraz</button>
                              </form>";
                    }

                    // [ZMIANA] Przycisk Anuluj (Blokada dla zrealizowanych/anulowanych)
                    if(in_array($r['status'], ['potwierdzona','oczekujaca','oplacona'])) {
                        echo "<form method='POST' onsubmit='return confirm(\"Czy na pewno anulować?\")'>
                                <input type='hidden' name='rid' value='{$r['id_rezerwacji']}'>
                                <button name='anuluj_rez' class='btn btn-red' style='width:100%; padding:5px;'> Anuluj</button>
                              </form>";
                    }
                    
                    echo "</div></td></tr>";
                }
                ?>
            </table>
        </div>
    </div>
</div>
</body></html>