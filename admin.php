<?php
session_start();
include 'db.php';

// Zabezpieczenie dostępu
if (!in_array($_SESSION['rola'], ['admin', 'manager'])) {
    die("Brak dostępu");
}

$hid = $_SESSION['hid'];
$rola = $_SESSION['rola'];

// --- OBSŁUGA FORMULARZY ---

// 1. Dodawanie Hotelu (Tylko Admin)
if (isset($_POST['add_hotel']) && $rola == 'admin') {
    try {
        $stmt = $conn->prepare("INSERT INTO hotele (nazwa, miasto, mnoznik_lato, mnoznik_zima) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['nazwa'], $_POST['miasto'], $_POST['lato'], $_POST['zima']]);
        echo "<script>alert('Dodano nowy hotel!');</script>";
    } catch (Exception $e) {
        echo "<script>alert('Błąd: " . $e->getMessage() . "');</script>";
    }
}

// 2. Zmiana Statusu Rezerwacji
if (isset($_POST['zmien_status'])) {
    try {
        // Trigger w bazie sam zaktualizuje płatności i logi
        $conn->prepare("UPDATE rezerwacje SET status=? WHERE id_rezerwacji=?")->execute([$_POST['st'], $_POST['rid']]);
        
        // Opcjonalne czyszczenie płatności przy zwykłym anulowaniu
        if ($_POST['st'] == 'anulowana') {
            $conn->prepare("UPDATE platnosci SET status='zwrocona', kwota=0 WHERE id_rezerwacji=?")->execute([$_POST['rid']]);
        }
        
        echo "<script>alert('Status zmieniony!');</script>";
    } catch (Exception $e) {
        echo "<script>alert('Błąd bazy: " . $e->getMessage() . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="style.css">
    <title>Panel Administracyjny</title>
</head>
<body>

<div class="nav">
    <strong>PANEL <?php echo strtoupper($rola); ?></strong> 
    <a href="admin_users.php">Użytkownicy</a> 
    <a href="index.php">Widok Klienta</a>
</div>

<div class="box" style="max-width:1100px">
    
    <h3>1. Lista Hoteli</h3>
    <?php if ($rola == 'admin'): ?>
    <form method="POST" style="background:#eef; padding:15px; border-radius:5px; display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
        <input type="text" name="nazwa" placeholder="Nazwa" required style="flex:2;">
        <input type="text" name="miasto" placeholder="Miasto" required style="flex:1;">
        <input type="number" step="0.01" name="lato" placeholder="Lato (np. 1.2)" required style="width:100px;">
        <input type="number" step="0.01" name="zima" placeholder="Zima (np. 0.9)" required style="width:100px;">
        <button name="add_hotel" class="btn btn-green">Dodaj</button>
    </form>
    <?php endif; ?>

    <table>
        <tr><th>ID</th><th>Nazwa</th><th>Miasto</th><th>Mnożniki</th><th>Akcja</th></tr>
        <?php
        $sql = "SELECT * FROM hotele";
        if ($rola == 'manager') $sql .= " WHERE hotel_id = $hid";
        $sql .= " ORDER BY hotel_id";
        
        $hotele = $conn->query($sql);
        while ($h = $hotele->fetch()) {
            echo "<tr>
                <td>{$h['hotel_id']}</td>
                <td><b>{$h['nazwa']}</b></td>
                <td>{$h['miasto']}</td>
                <td>L: x{$h['mnoznik_lato']} | Z: x{$h['mnoznik_zima']}</td>
                <td><a href='admin_hotel_details.php?hid={$h['hotel_id']}' class='btn'>Zarządzaj</a></td>
            </tr>";
        }
        ?>
    </table>
    
    <hr>

    <h3>2. Zarządzanie Rezerwacjami</h3>
    <table>
        <tr><th>ID</th><th>Hotel</th><th>Klient</th><th>Termin / Osoby</th><th>Status</th><th>Akcja</th></tr>
        <?php
        $sql = "SELECT r.*, u.email, h.nazwa as hotel, p.pojemnosc 
                FROM rezerwacje r 
                JOIN uzytkownik u ON r.id_uzytkownika=u.id_uzytkownika 
                JOIN pokoje p ON r.id_pokoj=p.id_pokoj 
                JOIN hotele h ON p.hotel_id=h.hotel_id";
        
        if ($rola == 'manager') $sql .= " WHERE h.hotel_id = $hid";
        $sql .= " ORDER BY r.id_rezerwacji DESC LIMIT 20";
        
        $res = $conn->query($sql);
        while ($r = $res->fetch()) {
            // Kolor statusu
            $style = "font-weight:bold;";
            if($r['status'] == 'anulowana_pozno') $style .= "color:red;";
            if($r['status'] == 'oplacona') $style .= "color:green;";
            if($r['status'] == 'zrealizowana') $style .= "color:blue;";

            echo "<tr>
                <td>{$r['id_rezerwacji']}</td>
                <td>{$r['hotel']}</td>
                <td>{$r['email']}</td>
                <td>{$r['rezerwacja_od']} - {$r['rezerwacja_do']}<br>
                    <small>{$r['liczba_doroslych']} dor + {$r['liczba_dzieci']} dz (Max {$r['pojemnosc']})</small>
                </td>
                <td style='$style'>{$r['status']}</td>
                <td>
                    <form method='POST'>
                        <input type='hidden' name='rid' value='{$r['id_rezerwacji']}'>
                        
                        <select name='st' style='padding:5px; border-radius:4px;'>
                            <option value='potwierdzona' " . ($r['status']=='potwierdzona' ? 'selected' : '') . ">potwierdzona (Oczekująca)</option>
                            <option value='oplacona' " . ($r['status']=='oplacona' ? 'selected' : '') . ">oplacona (Klient zapłacił)</option>
                            <option value='zrealizowana' " . ($r['status']=='zrealizowana' ? 'selected' : '') . " style='font-weight:bold; color:green;'>zrealizowana (Gość dotarł)</option>
                            <option value='anulowana' " . ($r['status']=='anulowana' ? 'selected' : '') . " style='color:gray;'>anulowana (Free)</option>
                            <option value='anulowana_pozno' " . ($r['status']=='anulowana_pozno' ? 'selected' : '') . " style='color:red;'>anulowana_pozno (Kara)</option>
                        </select>
                        
                        <button name='zmien_status' class='btn' style='padding:5px;'>Zapisz</button>
                    </form>
                </td>
            </tr>";
        }
        ?>
    </table>

    <hr>

    <h3>3. Raport Finansowy</h3>
    <table>
        <tr><th>Hotel</th><th>Typ Pokoju</th><th>Ilość Rezerwacji</th><th>Zysk Łączny</th></tr>
        <?php
        $rap = $conn->query("SELECT * FROM raport_przychodow");
        $my_hotel_name = ($rola == 'manager') ? $conn->query("SELECT nazwa FROM hotele WHERE hotel_id=$hid")->fetchColumn() : "";
        
        while ($r = $rap->fetch()) {
            if ($rola == 'manager' && $r['hotel'] != $my_hotel_name) continue;
            echo "<tr>
                <td>{$r['hotel']}</td>
                <td>{$r['typ']}</td>
                <td>{$r['liczba_rezerwacji']}</td>
                <td style='color:green'><b>{$r['laczny_zysk']} PLN</b></td>
            </tr>"; 
        }
        ?>
    </table>

    <hr>
    
    <h3 style="background:#333; color:white; padding:10px;">4. Centrum Monitoringu (Logi Triggerów)</h3>
    <div style="display:flex; gap:20px;">
        
        <div style="flex:1; min-width:300px;">
            <h4 style="margin-top:0;">Logi Systemowe (SQL)</h4>
            <div style="max-height:300px; overflow-y:auto; border:1px solid #ddd;">
                <table style="font-size:12px;">
                    <tr style="background:#eee; position:sticky; top:0;"><th>ID Rez.</th><th>Zmiana Statusu</th><th>Data</th></tr>
                    <?php
                    $logs_sql = "SELECT * FROM logi_systemowe ORDER BY id_logu DESC LIMIT 20";
                    $logs = $conn->query($logs_sql);
                    while ($l = $logs->fetch()) {
                        echo "<tr>
                            <td>{$l['id_rezerwacji']}</td>
                            <td>{$l['stary_status']} &rarr; <b>{$l['nowy_status']}</b></td>
                            <td>{$l['data_zmiany']}</td>
                        </tr>";
                    }
                    ?>
                </table>
            </div>
        </div>

        <div style="flex:1; min-width:300px;">
            <h4 style="margin-top:0;">Logi NoSQL (JSONB)</h4>
            <div style="max-height:300px; overflow-y:auto; border:1px solid #ddd;">
                <table style="font-size:12px;">
                    <tr style="background:#eee; position:sticky; top:0;"><th>Data</th><th>Dokument JSON</th></tr>
                    <?php
                    $logs_nosql = "SELECT id, data_zdarzenia, dokument_json::text as json FROM logi_nosql ORDER BY id DESC LIMIT 20";
                    $jlogs = $conn->query($logs_nosql);
                    while ($j = $jlogs->fetch()) {
                        echo "<tr>
                            <td style='white-space:nowrap;'>{$j['data_zdarzenia']}</td>
                            <td style='font-family:monospace; color:#555;'>{$j['json']}</td>
                        </tr>";
                    }
                    ?>
                </table>
            </div>
        </div>

    </div>

</div>
</body>
</html>