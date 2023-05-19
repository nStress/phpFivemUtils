<?php
require_once('utils.php');

$serverIP = 'ip';
$serverPort = 30120;

$onlinePlayers = new OnlinePlayers($serverIP, $serverPort);

// Obtine lista de jucatori
$players = $onlinePlayers->getPlayers();

if ($players !== false) {
    echo "Lista de jucatori:</br>";
    foreach ($players as $player) {
        echo "Nume: " . $player['name'] . "</br>";
        echo "Identificator: " . $player['identifiers'][0] . "</br>";
        echo "-----------------</br>";
    }
} else {
    echo "Nu s-au gasit jucatori online.</br>";
}

// Obtine lista de nume de utilizator
$usernames = $onlinePlayers->getUsernames();

if ($usernames !== false) {
    echo "Lista de nume de utilizator:</br>";
    foreach ($usernames as $username) {
        echo $username . "</br>";
    }
} else {
    echo "Nu s-au gasit jucatori online.</br>";
}

// Obtine lista de identificatori
$identifiers = $onlinePlayers->getIdentifiers();

if ($identifiers !== false) {
    echo "Lista de identificatori:</br>";
    foreach ($identifiers as $identifier) {
        echo $identifier . "</br>";
    }
} else {
    echo "Nu s-au gasit jucatori online.</br>";
}

?>