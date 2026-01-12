<?php
session_start(); include 'db.php';
if(isset($_POST['start'])) $_SESSION['temp'] = $_POST;
if(!isset($_SESSION['uid'])) { header("Location: login.php"); exit; }

$d = isset($_SESSION['temp']) ? $_SESSION['temp'] : null; $msg=""; $wycena="";

// 1. SYMULACJA CENY
if($d) {
    try {
        $stmt = $conn->prepare("SELECT symuluj_cene_rezerwacji(:uid, :pid, :od, :do)");
        $stmt->execute([':uid'=>$_SESSION['uid'], ':pid'=>$d['pid'], ':od'=>$d['od'], ':do'=>$d['do']]);
        $wycena = $stmt->fetchColumn();
    } catch(Exception $e) { $wycena = "Błąd: ".$e->getMessage(); }
}

// 2. FINALIZACJA REZERWACJI
if(isset($_POST['final']) && $d){
    try {
        // Przekazujemy liczbę dorosłych (:adults) do bazy
        $stmt = $conn->prepare("SELECT dokonaj_rezerwacji(:uid, :pid, :od, :do, :adults, :kids)");
        $stmt->execute([
            ':uid' => $_SESSION['uid'], 
            ':pid' => $d['pid'], 
            ':od' => $d['od'], 
            ':do' => $d['do'], 
            ':adults' => $d['dorosli'], //  przekazujemy wartość z formularza
            ':kids' => $d['dzieci']
        ]);
        $msg = $stmt->fetchColumn();
        
        // Jeśli sukces, czyścimy sesję
        if(strpos($msg, 'Sukces')!==false) { 
            unset($_SESSION['temp']); 
            $d=null; 
        }
        
    } catch(Exception $e) { 
        // Obsługa błędów triggerów (np. BLOKADA KONTA)
        $rawError = $e->getMessage();
        if (strpos($rawError, 'BLOKADA') !== false) {
            $msg = "NIE MOŻNA ZAREZERWOWAĆ: Twoje konto jest zablokowane!";
        } else {
            $msg = "Błąd bazy: ".$rawError; 
        }
    }
}
?>
<!DOCTYPE html><html><head><link rel="stylesheet" href="style.css"></head><body>
<div class="nav"><a href="index.php">Anuluj i Wróć</a></div>
<div class="box box-small">
    
    <?php if($msg): ?> 
        <?php if(strpos($msg, 'Sukces')!==false): ?>
            <div class="msg-success" style="padding:20px; text-align:center;">
                <h3><?php echo $msg; ?></h3>
                <p>Twoja rezerwacja została przyjęta.</p>
                <p>Możesz ją opłacić online w swoim profilu lub na miejscu.</p>
                <br>
                <a href="profile.php" class="btn btn-green btn-full">Przejdź do Płatności</a>
            </div>
        <?php else: ?>
            <div class="msg-error">
                <h3>Wystąpił problem</h3>
                <p><?php echo $msg; ?></p>
                <br>
                <a href="index.php" class="btn btn-full">Wróć</a>
            </div>
        <?php endif; ?>
        
    <?php elseif($d): ?>
        <h3>Potwierdzenie Rezerwacji</h3>
        <p><strong>Termin:</strong> <?php echo $d['od']; ?> - <?php echo $d['do']; ?></p>
        <p><strong>Osoby:</strong> <?php echo $d['dorosli']; ?> dorosłych + <?php echo $d['dzieci']; ?> dzieci</p>
        <div class="admin-card">
            Koszt całkowity: <div style="font-size:1.3em; color:#003580; margin-top:5px;"><?php echo $wycena; ?><br><br></div>
        </div>
        <form method="POST">
            <button name="final" class="btn btn-full">REZERWUJĘ (Płatność w kolejnym kroku)</button>
        </form>
    <?php else: ?>
        <p>Brak danych. Wróć do wyszukiwarki.</p>
    <?php endif; ?>
</div></body></html>