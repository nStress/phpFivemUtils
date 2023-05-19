<?php 
require_once("utils.php");


// Creare obiect DatabaseConnection
$db = new DatabaseConnection('localhost', 'username', 'password', 'database');

// Creare obiect LoginManager
$loginManager = new LoginManager($db);

// Autentificare utilizator
if ($loginManager->createSession('username', 'password')) {
    echo "Autentificare reusita!";
} else {
    echo "Autentificare esuata!";
}

// Creare cont nou
if ($loginManager->createAccount('newusername', 'newpassword', 'identifier')) {
    echo "Cont creat cu succes!";
} else {
    echo "Eroare la crearea contului!";
}

// stergere cont
if ($loginManager->deleteAccount(1)) {
    echo "Cont sters cu succes!";
} else {
    echo "Nu ai permisiunea de a sterge acest cont!";
}

// Asociere usernames
$usernames = $loginManager->associateUsernames();
foreach ($usernames as $username) {
    echo "ID: " . $username['id'] . ", Username (account_panel): " . $username['username'] . ", Username (vrp_users_ids): " . $username['vrp_username'] . "<br>";
}


?>