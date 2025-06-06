<?php
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

function getLocation($ip) {
    $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,lat,lon,isp,query";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => 'Impossible de contacter l\'API'];
    }

    $data = json_decode($response, true);
    
    if ($data['status'] === 'success') {
        return [
            'ip' => $data['query'],
            'pays' => $data['country'],
            'region' => $data['regionName'],
            'ville' => $data['city'],
            'latitude' => $data['lat'],
            'longitude' => $data['lon'],
            'fournisseur' => $data['isp']
        ];
    } else {
        return ['error' => $data['message']];
    }
}

function logToFile($data, $filename = 'log.log') {
    $date = date('Y-m-d H:i:s');
    $logEntry = "[$date] " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($filename, $logEntry, FILE_APPEND);
}

// Exécution
$ip = getClientIP();
$location = getLocation($ip);

if (isset($location['error'])) {
    echo "Erreur : " . $location['error'];
    logToFile(['error' => $location['error'], 'ip' => $ip]);
} else {
    logToFile($location);
    echo "Vos informations ont été enregistrées avec succès.<br>";
    echo "📱 Répondez-moi sur WhatsApp ";
}
?>
