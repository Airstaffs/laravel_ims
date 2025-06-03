<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = dbDatabase();
$creds = USPSCredentials($db);


if ($creds) {
    $clientId = $creds['client_id'];
    $clientSecret = $creds['client_secret'];

    $accessToken = getUSPSAccessToken($clientId, $clientSecret);

    $trackingNumber = '9334910571270204784002';

    $data = getUSPSTrackingInfo($trackingNumber, $accessToken);

    echo "<pre>";
    print_r($data);
    echo "</pre>";
    // echo "Access Token: $accessToken";
} else {
    echo "USPS API credentials not found.";
}
function dbDatabase()
{
    // Define server mode here
    $servertype = "laravel_ims";

    // Set credentials based on server type
    switch ($servertype) {
        case "ims":
            $hostname = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'ims';
            break;

        case "hostinger":
            $hostname = 'localhost';
            $username = 'u298641722_web_ims';
            $password = 'ImsHosting!11923';
            $database = 'u298641722_ims';
            break;

        case "test":
            $hostname = 'localhost';
            $username = 'u298641722_testing_user';
            $password = 'Watdahek1234!';
            $database = 'u298641722_test';
            break;

        case "laravel_ims":
            $hostname = 'localhost';
            $username = 'u298641722_dbims_user';
            $password = '?cIk=|zRk3T';
            $database = 'u298641722_dbims';
            break;

        default:
            die("❌ Invalid server type: Set \$servertype properly.");
    }

    echo "$hostname $username $password $database Rawr";

    // Create mysqli dbion
    $db = new mysqli($hostname, $username, $password, $database);

    return $db;
}

function USPSCredentials($db)
{
    $sql = "SELECT client_id, client_secret FROM tblapis WHERE api_name = 'USPS' LIMIT 1";
    $result = $db->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc(); // returns ['client_id' => '...', 'client_secret' => '...']
    } else {
        error_log("USPS credentials not found in tblapis.");
        return false;
    }
}

function getUSPSAccessToken($clientId, $clientSecret)
{
    $url = 'https://api.usps.com/oauth2/v3/token';

    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ];

    $postFields = http_build_query([
        'grant_type' => 'client_credentials',
        'scope' => 'tracking',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$clientSecret"); // Basic Auth
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);

    echo "<pre>";
    print_r($data);
    echo "</pre>";


    if ($status === 200 && isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        error_log("Token Error [$status]: $response");
        return false;
    }
}

function getUSPSTrackingInfo($trackingNumber, $accessToken)
{
    $url = "https://api.usps.com/tracking/v3/tracking/$trackingNumber";

    $headers = [
        "Authorization: Bearer $accessToken",
        "Accept: application/json",
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch);
    echo "<pre>";
    print_r($status);
    echo "</pre>";
    curl_close($ch);

    $data = json_decode($response, true);
    echo "<pre>";
    print_r($data);
    echo "</pre>";

    if ($status === 200) {
        return $data;
    } else {
        error_log("Tracking Error [$status]: $response");
        return false;
    }
}