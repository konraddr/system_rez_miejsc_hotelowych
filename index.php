<?php session_start(); include 'db.php'; 

// DANE DO FILTRÓW
$hotele = $conn->query("SELECT * FROM hotele")->fetchAll();
$typy = $conn->query("SELECT DISTINCT typ_pokoju FROM pokoje")->fetchAll();

// LOGIKA WYSZUKIWANIA
$where = "1=1"; 
$params = [];

if(!empty($_GET['h'])) { $where .= " AND p.hotel_id = ?"; $params[] = $_GET['h']; }
if(!empty($_GET['t'])) { $where .= " AND p.typ_pokoju = ?"; $params[] = $_GET['t']; }
if(!empty($_GET['os'])) { $where .= " AND p.pojemnosc >= ?"; $params[] = $_GET['os']; }
if(!empty($_GET['dz'])) { $where .= " AND p.max_dzieci >= ?"; $params[] = $_GET['dz']; }

// 1. POBIERAMY LISTĘ POKOI
$sql = "SELECT p.*, h.nazwa as hotel_nazwa, h.miasto 
        FROM pokoje p 
        JOIN hotele h ON p.hotel_id = h.hotel_id 
        WHERE $where 
        ORDER BY h.nazwa, p.cena_doba";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$pokoje = $stmt->fetchAll();

// 2. POBIERAMY ZAJĘTE TERMINY (Dla JS)
$pokoje_ids = array_column($pokoje, 'id_pokoj');
$zajete_terminy = [];

if(!empty($pokoje_ids)) {
    $ids_str = implode(',', $pokoje_ids);
    // Pobieramy tylko rezerwacje aktualne i przyszłe, nie anulowane
    $sql_dates = "SELECT id_pokoj, rezerwacja_od, rezerwacja_do FROM rezerwacje 
                  WHERE id_pokoj IN ($ids_str) 
                  AND status NOT LIKE 'anulowana%' 
                  AND rezerwacja_do >= CURRENT_DATE";
    $dates_res = $conn->query($sql_dates)->fetchAll();

    foreach($dates_res as $d) {
        $zajete_terminy[$d['id_pokoj']][] = [
            'from' => $d['rezerwacja_od'],   
            'to' => $d['rezerwacja_do']
        ];
    }
}
?>

<!DOCTYPE html><html><head>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Stylizacja zajętych i wolnych dat */
        .flatpickr-day.disabled { 
            background: #ffcccc !important; 
            color: #990000 !important; 
            border-color: #ffcccc !important; 
        }
        .flatpickr-day { background: #e6fffa; } /* Domyślnie zielonkawe (wolne) */
        .flatpickr-day.selected { background: #003580 !important; border-color: #003580 !important; }
        
        /* Ukrywamy standardowy kalendarz przeglądarki dla pewności */
        input[type="date"]::-webkit-calendar-picker-indicator {
            display: none;
        }
    </style>
</head><body>
<div class="nav">
    <strong>System Rezerwacji</strong>
    <div>
        <?php if(isset($_SESSION['uid'])): ?>
            Witaj, <?php echo $_SESSION['email']; ?> | <a href="profile.php">Mój Profil</a>
            <?php if(in_array($_SESSION['rola'], ['admin','manager'])) echo ' | <a href="admin.php">PANEL</a>'; ?>
            | <a href="logout.php">Wyloguj</a>
        <?php else: ?>
            <a href="login.php">Zaloguj</a> | <a href="register.php">Rejestracja</a>
        <?php endif; ?>
    </div>
</div>

<div class="box box-wide">
    <form method="GET" class="search-bar">
        <div style="flex:1"><label>Hotel:</label><select name="h"><option value="">Wszystkie hotele</option><?php foreach($hotele as $h) echo "<option value='{$h['hotel_id']}'>{$h['nazwa']} ({$h['miasto']})</option>"; ?></select></div>
        <div style="width:100px"><label>Osób:</label><select name="os"><option value="">Min</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4+</option></select></div>
        <div style="width:100px"><label>Dzieci:</label><select name="dz"><option value="">Min</option><option value="0">0</option><option value="1">1</option><option value="2">2+</option></select></div>
        <div style="width:150px"><label>Typ:</label><select name="t"><option value="">Wszystkie</option><?php foreach($typy as $t) echo "<option value='{$t['typ_pokoju']}'>{$t['typ_pokoju']}</option>"; ?></select></div>
        <button class="btn btn-green">SZUKAJ</button>
    </form>

    <hr>
    <h3>Dostępne Pokoje (<?php echo count($pokoje); ?>)</h3>
    
    <table>
        <tr><th>Hotel</th><th>Pokój</th><th>Szczegóły</th><th>Cena/noc</th><th>Rezerwacja</th></tr>
        <?php foreach($pokoje as $row): 
            // Pobieramy zablokowane daty dla konkretnego pokoju i kodujemy do JSON
            $blocked_json = isset($zajete_terminy[$row['id_pokoj']]) ? json_encode($zajete_terminy[$row['id_pokoj']]) : '[]';
        ?>
        <tr>
            <td><b><?php echo $row['hotel_nazwa']; ?></b><br><small><?php echo $row['miasto']; ?></small></td>
            <td>Nr <b><?php echo $row['nr_pokoj']; ?></b></td>
            <td>Typ: <?php echo $row['typ_pokoju']; ?><br>Max: <?php echo $row['pojemnosc']; ?> os. + <?php echo $row['max_dzieci']; ?> dz.</td>
            <td><b class="money"><?php echo $row['cena_doba']; ?> PLN</b></td>
            
            <td style="min-width: 350px;">
                <form action="rezerwacja.php" method="POST">
                    <div style="display:flex; gap:5px; margin-bottom:5px;">
                        <div style="flex:1">
                            <small>Od:</small>
                            <input type="text" name="od" class="date-picker" data-blocked='<?php echo $blocked_json; ?>' placeholder="Wybierz datę" required style="background:white; cursor:pointer;">
                        </div>
                        <div style="flex:1">
                            <small>Do:</small>
                            <input type="text" name="do" class="date-picker" data-blocked='<?php echo $blocked_json; ?>' placeholder="Wybierz datę" required style="background:white; cursor:pointer;">
                        </div>
                    </div>
                    <div style="display:flex; gap:5px; align-items:center;">
                        <small>Os:</small>
                        <input type="number" name="dorosli" value="2"<?php echo $_GET['os'] ?? 1; ?>" min="1" max="<?php echo $row['pojemnosc']; ?>" style="width:50px">
                        <small>Dz:</small>
                        <input type="number" name="dzieci" value="0" min="0" max="<?php echo $row['max_dzieci']; ?>" style="width:50px">
                        <input type="hidden" name="pid" value="<?php echo $row['id_pokoj']; ?>">
                        <button name="start" class="btn" style="flex:1">Rezerwuj</button>
                    </div>
                </form>
                <a href="terminy.php?pid=<?php echo $row['id_pokoj']; ?>" class="btn btn-gray btn-small" style="display:block; text-align:center; margin-top:5px;">📅 Pełny Grafik</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/pl.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.date-picker').forEach(function(input) {
        // Odczytujemy zablokowane daty z atrybutu HTML
        let blockedDates = JSON.parse(input.getAttribute('data-blocked'));
        
        flatpickr(input, {
            locale: "pl", 
            minDate: "today", 
            dateFormat: "Y-m-d", 
            disable: blockedDates, // Tu przekazujemy zajęte terminy (będą czerwone dzięki CSS wyżej)
            onChange: function(selectedDates, dateStr, instance) {
                // Automatyczne ustawienie daty "Do" na dzień po dacie "Od"
                if(input.name === 'od') {
                    let form = input.closest('form');
                    let doInput = form.querySelector('input[name="do"]');
                    if(doInput && doInput._flatpickr) {
                        doInput._flatpickr.set('minDate', dateStr);
                        // Opcjonalnie: otwórz od razu kalendarz "Do"
                        // doInput._flatpickr.open(); 
                    }
                }
            }
        });
    });
});
</script>
</body></html>