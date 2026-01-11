<?php session_start(); include 'db.php'; 

if(!isset($_GET['pid'])) die("Brak ID pokoju");
$pid = $_GET['pid'];

$pokoj = $conn->query("SELECT p.*, h.nazwa FROM pokoje p JOIN hotele h ON p.hotel_id=h.hotel_id WHERE id_pokoj=$pid")->fetch();

// POPRAWKA: Zmieniono 'status != anulowana' na 'status NOT LIKE anulowana%'
// Dzięki temu 'anulowana_pozno' też jest traktowana jako wolny termin.
$sql = "SELECT rezerwacja_od, rezerwacja_do FROM rezerwacje 
        WHERE id_pokoj = ? 
        AND status NOT LIKE 'anulowana%' 
        AND rezerwacja_do >= CURRENT_DATE 
        ORDER BY rezerwacja_od ASC";
        
$stmt = $conn->prepare($sql);
$stmt->execute([$pid]);
$zajete = $stmt->fetchAll();
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="style.css"></head><body>
<div class="nav"><a href="index.php">Wróć do wyszukiwarki</a></div>

<div class="box" style="max-width:600px; text-align:center;">
    <h2>Dostępność Pokoju</h2>
    <h3><?php echo $pokoj['nazwa']; ?> - Pokój nr <?php echo $pokoj['nr_pokoj']; ?></h3>
    <p>Typ: <?php echo $pokoj['typ_pokoju']; ?></p>

    <hr>
    
    <h4 style="color:red">Terminy już zajęte (zarezerwowane):</h4>
    
    <?php if(count($zajete) > 0): ?>
        <table style="margin: 0 auto; width: 80%;">
            <tr><th>Od</th><th>Do</th><th>Status</th></tr>
            <?php foreach($zajete as $z): ?>
                <tr style="background-color: #ffe6e6;">
                    <td><?php echo $z['rezerwacja_od']; ?></td>
                    <td><?php echo $z['rezerwacja_do']; ?></td>
                    <td style="color:red; font-weight:bold;">ZAJĘTY</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="color:green; font-weight:bold; font-size:1.2em;">Ten pokój jest wolny we wszystkich nadchodzących terminach!</p>
    <?php endif; ?>

    <br>
    <a href="index.php" class="btn">Wróć i Rezerwuj</a>
</div>
</body></html>