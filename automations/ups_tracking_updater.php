<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$servertype1 = "ims";
$imsv1_connect = dbDatabase($servertype1);

$servertype2 = "laravel_ims";
$imsv2_connect = dbDatabase($servertype2);

$creds = getUPSCredentials($imsv1_connect);

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

function dbDatabase($servertype)
{
    // Define server mode here

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

function getUPSCredentials($Connect)
{
    $id = 4;
    $sql = "SELECT client_id, client_secret, access_token, refresh_token, expires_in FROM aws_key WHERE id = $id";
    $result = $Connect->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        die("No keys found for the given client ID.");
    }

    return $row;
}

function UPS_fetchDetails($trackingnumber, $credentials)
{
    $inquiry = $trackingnumber;
    $query = array(
        "locale" => "en_US",
        "returnSignature" => "false",
        "returnMilestones" => "false"
    );

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $credentials['access_token'],
            "transId: asjfdklasdjfaslkjsdfasslkdjfas",
            "transactionSrc: CustomerServicePortal"
        ],
        CURLOPT_URL => "https://onlinetools.ups.com/api/track/v1/details/" . $inquiry . "?" . http_build_query($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
    ]);


    $response = curl_exec($curl);

    $response = json_decode($response, true);

    $data = curl_getinfo($curl);
    echo "<pre>";
    print_r($response);
    echo "</pre>";


    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        echo "cURL Error #:" . $error;
    } else {
        return $response;
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