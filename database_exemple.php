<?php 
require_once('utils.php');
$databaseConnection = new DatabaseConnection($servername, $username, $password, $dbname);

// Execută un query pentru a obtine toate datele dintr-o tabelt
$query = "SELECT * FROM vrp_users";  
$results = $databaseConnection->executeQuery($query); // executeQuery poate primi un al doilea argument ce indica alta baza de date in cazul in care este nevoie
// exemplu: $databaseConnection->executeQuery($query,"serverTeste") in asa fel poti executa query-ul de mai sus in baza de date a serverului de teste

if (!empty($results)) {
    foreach ($results as $row) {
        $extractor = new DataExtractor($row['last_login']);
        echo "ID: " . $row['id'] . "</br>";
        echo "username: " . $row['username'] . "</br>";
        echo "last_login: " .$extractor->extractLastLogin(). "</br>";
        echo "-----------------</br>";
    }
} else {
    echo "Nu s-au găsit înregistrări.</br>";
}

// Executa un query de inserare
$query = "INSERT INTO vrp_userss (username, id) VALUES ('nStress', '14444')";
$result = $databaseConnection->executeQuery($query);

if ($result) {
    echo "Inserarea a fost realizată cu succes.</br>";
} else {
    echo "Inserarea a eșuat.</br>";
}


?>