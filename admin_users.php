<?php
session_start(); include 'db.php';

// Ochrona: Tylko admin ma tu wstęp
if ($_SESSION['rola'] != 'admin') die("Brak dostępu");

// Pobranie listy hoteli do formularzy
$hotele = $conn->query("SELECT hotel_id, nazwa FROM hotele")->fetchAll();

// =========================================================
// 1. OBSŁUGA FORMULARZY
// =========================================================

// Tworzenie nowego usera
if (isset($_POST['create_user'])) {
    $hotel_id = ($_POST['rola'] == 'manager' && $_POST['hotel_id'] != 'NULL') ? $_POST['hotel_id'] : NULL;
    
    $stmt = $conn->prepare("SELECT utworz_uzytkownika(?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['imie'], $_POST['nazwisko'], $_POST['email'], 
        $_POST['haslo'], $_POST['rola'], $hotel_id
    ]);
    $wynik = $stmt->fetchColumn();
    echo "<script>alert('$wynik');</script>";
}
// Blokowanie
if (isset($_POST['toggle_block_id'])) {
    $conn->prepare("UPDATE uzytkownik SET czy_zablokowany = NOT czy_zablokowany WHERE id_uzytkownika = ?")->execute([$_POST['toggle_block_id']]);
}

// Usuwanie
if (isset($_POST['delete_user_id'])) {
    $conn->prepare("DELETE FROM uzytkownik WHERE id_uzytkownika = ?")->execute([$_POST['delete_user_id']]);
}

// Zmiana hotelu managera
if (isset($_POST['assign_hotel'])) {
    $hid = ($_POST['hotel_id'] == "NULL") ? NULL : $_POST['hotel_id'];
    $conn->prepare("UPDATE uzytkownik SET manager_hotel_id = ? WHERE id_uzytkownika = ?")->execute([$hid, $_POST['user_id']]);
    echo "<script>alert('Zaktualizowano przypisanie hotelu!');</script>";
}

// =========================================================
// 2. LOGIKA WYSZUKIWANIA
// =========================================================
$where = "1=1"; 
$params = [];

if (!empty($_GET['q'])) {
    $where .= " AND (imie ILIKE ? OR nazwisko ILIKE ? OR email ILIKE ?)";
    $txt = "%" . $_GET['q'] . "%";
    $params[] = $txt; $params[] = $txt; $params[] = $txt;
}
if (!empty($_GET['r'])) {
    $where .= " AND rola = ?";
    $params[] = $_GET['r'];
}
if (!empty($_GET['s'])) {
    if($_GET['s'] == 'blocked') $where .= " AND czy_zablokowany = TRUE";
    if($_GET['s'] == 'active')  $where .= " AND czy_zablokowany = FALSE";
}

$sql = "SELECT u.*, h.nazwa as hotel_nazwa 
        FROM uzytkownik u 
        LEFT JOIN hotele h ON u.manager_hotel_id = h.hotel_id 
        WHERE $where 
        ORDER BY u.id_uzytkownika DESC";
        
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head><link rel="stylesheet" href="style.css"></head>
<body>

<div class="nav">
    <strong>Zarządzanie Użytkownikami</strong>
    <a href="admin.php">Wróć do Panelu</a>
</div>

<div class="box" style="max-width:1200px;">
    
    <div style="background: #eef; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ccd;">
        <h3 style="margin-top:0;">+ Stwórz Nowe Konto</h3>
        <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1"><label style="font-size:12px;">Imię</label><input type="text" name="imie" required style="margin:0"></div>
            <div style="flex:1"><label style="font-size:12px;">Nazwisko</label><input type="text" name="nazwisko" required style="margin:0"></div>
            <div style="flex:1"><label style="font-size:12px;">Email</label><input type="email" name="email" required style="margin:0"></div>
            <div style="width:100px"><label style="font-size:12px;">Hasło</label><input type="text" name="haslo" value="1234" required style="margin:0"></div>
            <div style="width:120px">
                <label style="font-size:12px;">Rola</label>
                <select name="rola" style="margin:0">
                    <option value="klient">Klient</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div style="width:180px">
                <label style="font-size:12px;">Hotel (dla Managera)</label>
                <select name="hotel_id" style="margin:0">
                    <option value="NULL">-- Brak --</option>
                    <?php foreach($hotele as $h): echo "<option value='{$h['hotel_id']}'>{$h['nazwa']}</option>"; endforeach; ?>
                </select>
            </div>
            <button name="create_user" class="btn btn-green" style="height:42px; margin:0;">Stwórz</button>
        </form>
    </div>

    <form method="GET" style="background: #003580; padding: 15px; border-radius: 8px; display: flex; gap: 10px; align-items: flex-end; margin-bottom: 20px; flex-wrap:wrap;">
        <div style="flex:1; min-width:200px;">
            <label style="color:white; font-size:12px;">Szukaj:</label>
            <input type="text" name="q" value="<?php echo $_GET['q'] ?? ''; ?>" placeholder="Email, Imię, Nazwisko..." style="margin:0;">
        </div>
        <div style="width:150px;">
            <label style="color:white; font-size:12px;">Rola:</label>
            <select name="r" style="margin:0;">
                <option value="">Wszyscy</option>
                <option value="klient" <?php if(($_GET['r']??'')=='klient') echo 'selected'; ?>>Klienci</option>
                <option value="manager" <?php if(($_GET['r']??'')=='manager') echo 'selected'; ?>>Managerowie</option>
                <option value="admin" <?php if(($_GET['r']??'')=='admin') echo 'selected'; ?>>Admini</option>
            </select>
        </div>
        <div style="width:150px;">
            <label style="color:white; font-size:12px;">Status:</label>
            <select name="s" style="margin:0;">
                <option value="">Wszystkie</option>
                <option value="active" <?php if(($_GET['s']??'')=='active') echo 'selected'; ?>>Aktywni</option>
                <option value="blocked" <?php if(($_GET['s']??'')=='blocked') echo 'selected'; ?>>Zablokowani</option>
            </select>
        </div>
        <button class="btn btn-green" style="height:42px; margin:0;">SZUKAJ</button>
        <a href="admin_users.php" class="btn" style="height:42px; margin:0; line-height:42px; background:#666; text-decoration:none;">RESET</a>
    </form>

    <h3>Lista Użytkowników (Znaleziono: <?php echo count($users); ?>)</h3>
    <table style="border-collapse: collapse; width: 100%;">
        <tr>
            <th>ID</th>
            <th>Dane Użytkownika</th>
            <th>Rola</th>
            <th>Przypisany Hotel</th>
            <th>Status</th>
            <th>Akcje</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><?php echo $u['id_uzytkownika']; ?></td>
                <td>
                    <b><?php echo $u['imie'] . ' ' . $u['nazwisko']; ?></b><br>
                    <small><?php echo $u['email']; ?></small>
                </td>
                <td><span style="text-transform:uppercase; font-weight:bold; font-size:0.9em;"><?php echo $u['rola']; ?></span></td>
                
                <td>
                    <?php if($u['rola'] == 'manager'): ?>
                        <form method='POST' style='display:flex; gap:5px; align-items:center; margin:0;'>
                            <input type='hidden' name='user_id' value='<?php echo $u['id_uzytkownika']; ?>'>
                            <select name='hotel_id' style='padding:2px; margin:0; font-size:11px; width:120px;'>
                                <option value='NULL'>-- Brak --</option>
                                <?php foreach($hotele as $h): 
                                    $sel = ($u['manager_hotel_id'] == $h['hotel_id']) ? 'selected' : '';
                                    echo "<option value='{$h['hotel_id']}' $sel>{$h['nazwa']}</option>";
                                endforeach; ?>
                            </select>
                            <button name='assign_hotel' class='btn' style='padding:2px 5px; font-size:10px; margin:0;'>OK</button>
                        </form>
                    <?php else: echo "-"; endif; ?>
                </td>

                <td>
                    <?php if($u['czy_zablokowany']): ?>
                        <b style="color:red">ZABLOKOWANY</b>
                    <?php else: ?>
                        <span style="color:green">Aktywny</span>
                    <?php endif; ?>
                </td>
                
                <td style="vertical-align: middle;">
                    <div style="display:flex; gap:5px; align-items:center;">
                        <?php if ($u['rola'] != 'admin'): ?>
                            
                            <form method='POST' style="margin:0;">
                                <input type='hidden' name='toggle_block_id' value='<?php echo $u['id_uzytkownika']; ?>'>
                                <?php if($u['czy_zablokowany']): ?>
                                    <button class='btn' style='background:green; padding:5px 10px; font-size:11px;'>Odblokuj</button>
                                <?php else: ?>
                                    <button class='btn' style='background:orange; color:black; padding:5px 10px; font-size:11px;'>Zablokuj</button>
                                <?php endif; ?>
                            </form>

                            <form method='POST' onsubmit='return confirm("Usunąć trwale?");' style="margin:0;">
                                <input type='hidden' name='delete_user_id' value='<?php echo $u['id_uzytkownika']; ?>'>
                                <button class='btn btn-red' style='padding:5px 10px; font-size:11px;'>Usuń</button>
                            </form>

                        <?php else: ?>
                            <b>ADMIN</b>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>