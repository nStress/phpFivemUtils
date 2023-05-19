<?php


// Configurarea conexiunii la baza de date
$servername = "localhost";
$username = "root";
$password = "parola";
$dbname = "vrp2";


class SecurityUtils {

    public static function escape_sql($value) {
        if (function_exists('mysqli_real_escape_string')) {
            global $connection; 
            return mysqli_real_escape_string($connection, $value);
        } else {
            return addslashes($value);
        }
    }

    public static function sanitize_input($input) {
        $sanitized = htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        return $sanitized;
    }

    public static function validate_file_path($path) {
        if (strpos($path, '../') === false && file_exists($path)) {
            return true;
        } else {
            return false;
        }
    }


    public static function evaluate_code($code) {
        return $code;
    }
}


class DatabaseConnection {
    private $servername;
    private $username;
    private $password;
    private $dbname;

    public function __construct($servername, $username, $password, $dbname = null) {
        $this->servername = $servername;
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
    }

    public function executeQuery($query, $dbname = null) {
        if ($dbname === null) {
            $dbname = $this->dbname;
        }

        $conn = new mysqli($this->servername, $this->username, $this->password, $dbname);

        if ($conn->connect_error) {
            die("Conexiunea la baza de date a esuat: " . $conn->connect_error);
        }

        $sanitized_query = SecurityUtils::sanitize_input($query);
        $result = $conn->query($sanitized_query);

        if (!$result) {
            die("Query-ul a esuat: " . $conn->error);
        }

        $data = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        $conn->close();

        return $data;
    }
}

class DatabaseUtility { // executa automat interogarea pentru crearea tabelului account_panel si adaugarea de coloane noi in vrp_users
    private $dbConnection;

    public function __construct(DatabaseConnection $dbConnection) {
        $this->dbConnection = $dbConnection;
    }

    public function createVrpUsersTable() {
        $query = "CREATE TABLE account_panel (
            id INT PRIMARY KEY,
            username VARCHAR(255),
            last_login VARCHAR(255),
            panelLevel INT
          );
          
          ALTER TABLE vrp_users
          ADD COLUMN codGenerat INT,
          ADD COLUMN token VARCHAR(255);";
        
        $this->dbConnection->executeQuery($query);
    }
}

// UTILIZARE: 
//$dbConnection = new DatabaseConnection("localhost", "username", "password", "database_name");
//$databaseUtility = new DatabaseUtility($dbConnection);
//$databaseUtility->createVrpUsersTable();

class LoginManager {
    private $db;

    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    public function createSession($username, $password) {
        $encryptedPassword = $this->encryptPassword($password);

        $query = "SELECT * FROM account_panel WHERE username = '$username' AND password = '$encryptedPassword'";
        $result = $this->db->executeQuery($query);

        if (!empty($result)) {
            // Autentificarea a reușit, se creeaza sesiunea utilizatorului
            session_start();
            $_SESSION['username'] = $username;
            $_SESSION['panelLevel'] = $result[0]['panelLevel'];
            $_SESSION['last_login'] = $result[0]['last_login'];

            // Se actualizeaza data ultimei autentificari în baza de date
            $this->updateLastLogin($username);

            return true;
        } else {
            // Autentificarea a eșuat
            return false;
        }
    }


    public function deleteAccount($accountId) {
        // Verifica daca utilizatorul curent are permisiunea de a șterge contul
        if ($_SESSION['panelLevel'] > $this->getAccountPanelLevel($accountId)) {
            $query = "DELETE FROM account_panel WHERE id = $accountId";
            $this->db->executeQuery($query);

            return true;
        } else {
            return false;
        }
    }

    public function associateUsernames() {
        $query = "SELECT account_panel.id, account_panel.username, vrp_users_ids.username AS vrp_username 
                  FROM account_panel
                  INNER JOIN vrp_users_ids ON account_panel.id = vrp_users_ids.user_id";
        $result = $this->db->executeQuery($query);

        return $result;
    }

    private function encryptPassword($password) {
        return md5($password);
    }

    private function updateLastLogin($username) {
        $currentDate = date('d/m/Y');
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        $query = "UPDATE account_panel SET last_login = '$ipAddress/$currentDate' WHERE username = '$username'";
        $this->db->executeQuery($query);
    }

    private function getAccountPanelLevel($accountId) {
        $query = "SELECT panelLevel FROM account_panel WHERE id = $accountId";
        $result = $this->db->executeQuery($query);

        if (!empty($result)) {
            return $result[0]['panelLevel'];
        } else {
            return 0;
        }
    }
}


class RegistrationManager {
    private $db;

    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    public function registerUser($loginName, $password, $generatedCode) {
        // Verifica daca exista codGenerat în tabela vrp_users
        $query = "SELECT * FROM vrp_users WHERE codGenerat = $generatedCode";
        $result = $this->db->executeQuery($query);

        if (!empty($result)) {
            $id = $result[0]['id'];
            $username = $result[0]['username'];

            // Genereaza un token unic pentru cont
            $token = $this->generateToken();

            // Insereaza datele în tabela account_panel
            $query = "INSERT INTO account_panel (id, username, token) VALUES ('$id', '$username', '$token')";
            $this->db->executeQuery($query);

            // Actualizeaza coloana codGenerat în tabela vrp_users
            $query = "UPDATE vrp_users SET codGenerat = 0 WHERE id = '$id'";
            $this->db->executeQuery($query);

            return true;
        } else {
            // Codul generat nu exista în tabela vrp_users
            return false;
        }
    }

    private function generateToken() {
        // TODO: 
        // Implementeaza logica de generare a token-ului
        // Exemplu simplu: return md5(uniqid());
        return uniqid();
    }
}


// clasa pentru mongodb 
class MongoDBConnection {
    private $client;
    private $database;

    public function __construct($uri, $database) {
        $this->client = new MongoDB\Client($uri);
        $this->database = $this->client->selectDatabase($database);
    }

    public function executeQuery($collection, $filter = [], $options = []) {
        $cursor = $this->database->selectCollection($collection)->find($filter, $options);

        $data = [];
        foreach ($cursor as $document) {
            $data[] = $document;
        }

        return $data;
    }
}
// UTILIZARE: 
// $mongo = new MongoDBConnection('mongodb://localhost:27017', 'mydatabase');
// $result = $mongo->executeQuery('mycollection', ['field' => 'value']);



class IPAddressValidator
{
    public static function verificaIP($ipFormat)
    {
        preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $ipFormat, $match);
        $ipExtrasa = $match[0] ?? null;

        return $ipExtrasa == $_SERVER['REMOTE_ADDR'];
    }
}

class DataExtractor {  // compatibil vrp in formatul 127.0.0.1 HH:MM:SS DD/MM/YYY (vrp_users -> last_login)
    private $dataString;

    public function __construct($dataString) {
        $this->dataString = $dataString;
    }

    public function extractIPAddress() {
        preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $this->dataString, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return false;
    }

    public function extractLastLogin() {
        preg_match('/(\d{2}\/\d{2}\/\d{4})/', $this->dataString, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return false;
    }

    public function extractDateTime() {
        preg_match('/(\d{2}:\d{2}:\d{2})/', $this->dataString, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return false;
    }
}

class DaysCalculator {  // FORMAT acceptat 127.0.0.1 HH:MM:SS DD/MM/YYY (incompatibil cu extractLastLogin)
    private $dateString;

    public function __construct($dateString) {
        $this->dateString = $dateString;
    }

    public function calculateDaysPassed() {
        $currentDate = new DateTime();
        $specifiedDate = DateTime::createFromFormat('d/m/Y', $this->dateString);

        if (!$specifiedDate) {
            return false;
        }

        $interval = $currentDate->diff($specifiedDate);
        $daysPassed = $interval->format('%a');

        // Verificati daca exista litere în data specificata
        if (preg_match('/[a-zA-Z]/', $this->dateString)) {
            return 0;
        }

        return $daysPassed;
    }

    public function calculateMonthsPassed() {
        $currentDate = new DateTime();
        $specifiedDate = DateTime::createFromFormat('d/m/Y', $this->dateString);

        if (!$specifiedDate) {
            return false;
        }

        $interval = $currentDate->diff($specifiedDate);
        $monthsPassed = ($interval->format('%y') * 12) + $interval->format('%m');

        // Verificati daca exista litere in data specificata
        if (preg_match('/[a-zA-Z]/', $this->dateString)) {
            return 0;
        }

        return $monthsPassed;
    }
}

class OnlinePlayers
{
    private $serverIP;
    private $serverPort;

    public function __construct($serverIP, $serverPort)
    {
        $this->serverIP = $serverIP;
        $this->serverPort = $serverPort;
    }

    public function getPlayers()
    {
        $url = "http://{$this->serverIP}:{$this->serverPort}/players.json";
        $playersData = file_get_contents($url);
        $players = json_decode($playersData, true);

        return $players ? $players : false;
    }

    public function getUsernames()
    {
        $players = $this->getPlayers();

        if ($players === false) {
            return false;
        }

        $usernames = array();

        foreach ($players as $player) {
            $usernames[] = $player['name'];
        }

        return $usernames;
    }

    public function getIdentifiers()
    {
        $players = $this->getPlayers();

        if ($players === false) {
            return false;
        }

        $identifiers = array();

        foreach ($players as $player) {
            $identifiers[] = $player['identifiers'][0];
        }

        return $identifiers;
    }
}


class StringManipulator
{
    public static function removeString($stringToFind, $inputString)
    {
        $escapedStringToFind = preg_quote($stringToFind, '/');
        $pattern = '/(?:' . $escapedStringToFind . ')/i';
        $outputString = preg_replace($pattern, '', $inputString);

        return $outputString;
    }

    public static function reverseString($inputString)
    {
        return strrev($inputString);
    }

    public static function capitalizeString($inputString)
    {
        return ucwords(strtolower($inputString));
    }

    public static function countOccurrences($needle, $haystack)
    {
        return substr_count($haystack, $needle);
    }



}

// UTILIZARE: 

//$inputString = "Hello, world! This is a sample string.";
//$stringToFind = "sample";
//$removedString = StringManipulator::removeString($stringToFind, $inputString);
//$reversedString = StringManipulator::reverseString($inputString);
//$capitalizedString = StringManipulator::capitalizeString($inputString);
//$occurrenceCount = StringManipulator::countOccurrences('is', $inputString);


class RconConnect
{
    private $serverAddress;
    private $serverPort;
    private $rconPassword = false;
    private $socket;
    private $lastPing = false;

    public function __construct($address, $port, &$success = null, &$errorCode = null, &$errorMessage = null)
    {
        $this->serverAddress = $address;
        $this->serverPort = $port;

        $this->socket = fsockopen("udp://$address", $port, $errorCode, $errorMessage, 5);
        if (!$this->socket) {
            $success = false;
        } else {
            $success = true;
        }
    }

    public function setRconPassword($password)
    {
        $this->rconPassword = $password;
    }

    public function rconCommand($command)
    {
        if (!$this->rconPassword) {
            return false;
        }
        $this->sendCommand("rcon " . $this->rconPassword . " $command");
        return $this->getResponse();
    }

    private function sendCommand($command)
    {
        fwrite($this->socket, "\xFF\xFF\xFF\xFF$command\x00");
    }

    private function getResponse()
    {
        stream_set_timeout($this->socket, 0, 700000);
        $response = '';
        $start = microtime(true);
        do {
            $read = fread($this->socket, 9999);
            $response .= substr($read, strpos($read, "\n") + 1);
            if (!isset($end)) {
                $end = microtime(true);
            }
            $info = stream_get_meta_data($this->socket);
        } while (!$info["timed_out"]);

        $this->lastPing = round(($end - $start) * 1000);

        return $response;
    }

    public function quit()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            return true;
        }
        return false;
    }

    public function reconnect()
    {
        $this->quit();
        $this->__construct($this->serverAddress, $this->serverPort);
    }

    public function getGameStatus()
    {
        $this->sendCommand("getstatus");
        $response = $this->getResponse();

        list($dvarsList, $playerList) = explode("\n", $response, 2);

        $dvarsList = explode("\\", $dvarsList);
        $dvars = array();
        for ($i = 1; $i < count($dvarsList); $i += 2) {
            $dvars[$dvarsList[$i]] = $dvarsList[$i + 1];
        }

        $playerList = explode("\n", $playerList);
        array_pop($playerList);
        $players = array();
        foreach ($playerList as $value) {
            list($score, $ping, $name) = explode(" ", $value, 3);
            $players[] = array(
                "name" => substr($name, 1, -1),
                "score" => $score,
                "ping" => $ping
            );
        }

        return array($dvars, $players);
    }

    public function getGameInfo()
    {
        $this->sendCommand("getinfo");
        $response = $this->getResponse();

        $dvarsList = explode("\\", $response);
        $dvars = array();
        for ($i = 1; $i < count($dvarsList); $i += 2) {
            $dvars[$dvarsList[$i]] = $dvarsList[$i + 1];
        }

        return $dvars;
    }

    public function getLastPing()
    {
        return $this->lastPing;
    }
}
?>