 <?php 
require_once('utils.php');


 //Crează o instanta a clasei rconConnect
$server = new RconConnect('ip', 30120); 
$server->setRconpassword('parola rcon'); // seteaza parola rcon (in cazul serverelor de fivem adaugati rcon_password = "parolarcon" in server.cfg)
// Trimite o comandă RCON

$response = $server->rcon('restart vrp');

if ($response === false) {
    echo "Nu s-a putut trimite comanda RCON.";
} else {
    echo "Comanda RCON a fost trimisă cu succes.";
}




// Deconectează de la server
$server->quit();

?>