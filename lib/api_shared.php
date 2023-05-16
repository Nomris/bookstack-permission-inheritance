<?php
require_once('config/config.php');

global $lastApiCall, $callDiff;
$lastApiCall = 0;
$callDiff = (60 / $GLOBALS['config']['bookstack']['apiRateLimit']) * 1000000;

function sendRequest(string $path, string $method, mixed $body)
{
    global $lastApiCall, $callDiff;

    if (($lastApiCall + $callDiff) > microtime(true)) usleep(max(array(1, ($lastApiCall + $callDiff) - microtime(true))));

    $session = curl_init();
    curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($session, CURLOPT_URL, $GLOBALS['config']['bookstack']['baseUri'] . $path);
    $headers = array();
    $headers[0] = 'Authorization: Token ' . $GLOBALS['config']['bookstack']['api']['id'] . ':' . $GLOBALS['config']['bookstack']['api']['secret'];
    if ($body !== null)
    {
        $bodyStr = json_encode($body);
        $headers[1] = 'Content-Type: application/json';
        $headers[2] = 'Content-Length: ' . strlen($bodyStr);
        curl_setopt($session, CURLOPT_POSTFIELDS, $bodyStr);
    }

    if ($GLOBALS['config']['bookstack']['ignorCertificate'] === true)
    {
        curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
    }

    curl_setopt($session, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);

    $result = curl_exec($session);
    $lastApiCall = microtime(true);

    $error = curl_error($session);
    if ($error !== '')
    {
        error_log($error . '::' . json_encode($result));
    }

    #file_put_contents('curl.log', "[$method]" . $path . ' >> ' . $result . "\n", FILE_APPEND);

    curl_close($session);

    return json_decode($result, true);
}

?>