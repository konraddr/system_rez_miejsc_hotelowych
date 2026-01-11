<?php
session_start(); include 'db.php';

// Zabezpieczenia
if(!in_array($_SESSION['rola'], ['admin', 'manager'])) die("Brak dostępu");
if(!isset($_GET['hid'])) die("Brak ID hotelu");

$hid = $_GET['hid'];

// Manager nie może wejść w inny hotel
if($_SESSION['rola'] == 'manager' && $_SESSION['hid'] != $hid) die("Brak uprawnień do tego hotelu!");

// A. EDYCJA DANYCH HOTELU (MNOŻNIKI)
if(isset($_POST['update_hotel'])){
    $stmt = $conn->prepare("UPDATE hotele SET nazwa=?, miasto=?, mnoznik_lato=?, mnoznik_zima=? WHERE hotel_id=?");
    $stmt->execute([$_POST['nazwa'], $_POST['miasto'], $_POST['lato'], $_POST['zima'], $hid]);
    echo "<script>alert('Zaktualizowano dane hotelu!');</script>";
}

// B. DODAWANIE POKOJU
if(isset($_POST['add_room'])){
    try {
        $stmt = $conn->prepare("INSERT INTO pokoje (hotel_id, nr_pokoj, typ_pokoju, pojemnosc, max_dzieci, cena_doba) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hid, $_POST['nr'], $_POST['typ'], $_POST['os'], $_POST['dzieci'], $_POST['cena']]);
        echo "<script>alert('Dodano pokój!');</script>";
    } catch(Exception $e) { echo "<script>alert('Błąd: ".$e->getMessage()."');</script>"; }
}

// C. USUWANIE POKOJU
if(isset($_POST['del_room'])){
    $stmt = $conn->prepare("SELECT usun_pokoj_bezpiecznie(?)");
    $stmt->execute([$_POST['pid']]);
    $msg = $stmt->fetchColumn();
    
    if(strpos($msg, 'BŁĄD') !== false) echo "<script>alert('$msg');</script>";
    else echo "<script>alert('$msg'); window.location.reload();</script>";
}

// Pobranie danych hotelu
$hotel = $conn->query("SELECT * FROM hotele WHERE hotel_id = $hid")->fetch();
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="style.css"></head><body>
<div class="nav">
    <strong>Zarządzanie: <?php echo $hotel['nazwa']; ?></strong>
    <a href="admin.php">Wróć do listy</a>
</div>

<div class="box" style="max-width:900px">

    <h3>Edytuj Ustawienia Hotelu</h3>
    <form method="POST" style="background:#f9f9f9; padding:20px; border:1px solid #ddd; border-radius:5px;">
        <label>Nazwa:</label>
        <input type="text" name="nazwa" value="<?php echo $hotel['nazwa']; ?>" required>
        
        <label>Miasto:</label>
        <input type="text" name="miasto" value="<?php echo $hotel['miasto']; ?>" required>
        
        <div style="display:flex; gap:20px;">
            <div style="flex:1">
                <label style="color:orange; font-weight:bold;">Mnożnik Lato (np. 1.20):</label>
                <input type="number" step="0.01" name="lato" value="<?php echo $hotel['mnoznik_lato']; ?>" required>
            </div>
            <div style="flex:1">
                <label style="color:blue; font-weight:bold;">Mnożnik Zima (np. 0.90):</label>
                <input type="number" step="0.01" name="zima" value="<?php echo $hotel['mnoznik_zima']; ?>" required>
            </div>
        </div>
        
        <button name="update_hotel" class="btn btn-green" style="width:100%; margin-top:10px;">Zapisz Zmiany</button>
    </form>

    <hr style="margin: 30px 0;">

    <h3>Pokoje w tym hotelu</h3>
    
    <div style="background:#eef; padding:15px; border-radius:5px; margin-bottom:20px;">
        <h4>+ Dodaj Nowy Pokój</h4>
        <form method="POST" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
            <div style="width:80px"><label style="font-size:12px">Nr pokoju</label><input type="text" name="nr" placeholder="np. 101" required style="margin:0"></div>
            <div style="flex:1"><label style="font-size:12px">Typ (np. Standard)</label><input type="text" name="typ" placeholder="Typ" required style="margin:0"></div>
            <div style="width:60px"><label style="font-size:12px">Osoby</label><input type="number" name="os" value="2" required style="margin:0"></div>
            <div style="width:60px"><label style="font-size:12px">Dzieci</label><input type="number" name="dzieci" value="0" required style="margin:0"></div>
            <div style="width:100px"><label style="font-size:12px">Cena (PLN)</label><input type="number" name="cena" placeholder="200" required style="margin:0"></div>
            <button name="add_room" class="btn" style="margin:0; height:42px;">Dodaj</button>
        </form>
    </div>

    <table>
        <tr><th>Nr</th><th>Typ</th><th>Pojemność</th><th>Cena Bazowa</th><th>Akcja</th></tr>
        <?php
        $pokoje = $conn->query("SELECT * FROM pokoje WHERE hotel_id = $hid ORDER BY nr_pokoj");
        while($p=$pokoje->fetch()){
            echo "<tr>
                <td><b>{$p['nr_pokoj']}</b></td>
                <td>{$p['typ_pokoju']}</td>
                <td>{$p['pojemnosc']} dorosłych + {$p['max_dzieci']} dzieci</td>
                <td>{$p['cena_doba']} PLN</td>
                <td>
                    <form method='POST' onsubmit='return confirm(\"Usunąć ten pokój?\");'>
                        <input type='hidden' name='pid' value='{$p['id_pokoj']}'>
                        <button name='del_room' class='btn btn-red' style='padding:5px 10px; font-size:12px;'>Usuń</button>
                    </form>
                </td>
            </tr>";
        }
        ?>
    </table>

</div>
</body></html>