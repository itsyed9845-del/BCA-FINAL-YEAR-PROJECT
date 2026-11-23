<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user = require_role('customer');
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Use current location.']);
    exit;
}
verify_csrf();
$lat = filter_var($_POST['latitude'] ?? '',FILTER_VALIDATE_FLOAT);
$lng = filter_var($_POST['longitude'] ?? '',FILTER_VALIDATE_FLOAT);
if ($lat===false || $lng===false || !is_finite($lat) || !is_finite($lng) || abs($lat)>90 || abs($lng)>180) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'A valid location is required.']);
    exit;
}
$key = number_format($lat,5,'.','') . ',' . number_format($lng,5,'.','');
$cache = $_SESSION['simple_address_cache'][$key] ?? null;
if ($cache && time() - $cache['time'] < 86400) {
    echo json_encode($cache['result']);
    exit;
}
if (microtime(true) - (float)($_SESSION['simple_lookup_time'] ?? 0) < 1.1) {
    http_response_code(429);
    echo json_encode(['success'=>false,'message'=>'Please wait a moment, then click Use current location again.']);
    exit;
}
$_SESSION['simple_lookup_time'] = microtime(true);
try {
    $config = require __DIR__ . '/../config/geocoding.php';
    $endpoint = $config['endpoint'];
    $url = $endpoint . '?' . http_build_query(['lat'=>$lat,'lon'=>$lng,'limit'=>1,'lang'=>'en']);
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>10,
            CURLOPT_USERAGENT=>'SmartFuel-Local-Project/1.0',
            CURLOPT_HTTPHEADER=>['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ]);
        $raw = curl_exec($curl);
        $code = (int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($raw===false || $code!==200) {
            throw new RuntimeException('Address lookup is temporarily unavailable.');
        }
    } else {
        $context = stream_context_create(['http'=>[
            'timeout'=>10,
            'header'=>"User-Agent: SmartFuel-Local-Project/1.0\r\nAccept: application/json\r\n",
        ]]);
        $raw = @file_get_contents($url,false,$context);
        if ($raw===false) {
            throw new RuntimeException('Address lookup could not connect. Check internet and PHP HTTPS support.');
        }
    }
    $data = json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    $properties = $data['features'][0]['properties'] ?? [];
    $parts = [];
    $street = trim(($properties['housenumber'] ?? '') . ' ' . ($properties['street'] ?? ''));
    foreach ([$properties['name'] ?? '',$street,$properties['district'] ?? '',$properties['city'] ?? ($properties['county'] ?? ''),$properties['state'] ?? '',$properties['postcode'] ?? '',$properties['country'] ?? ''] as $part) {
        if (is_string($part) && trim($part)!=='' && !in_array(trim($part),$parts,true)) {
            $parts[] = trim($part);
        }
    }
    if (!$parts) {
        throw new RuntimeException('No address found here. Please type the address and pincode below.');
    }
    $postcode = preg_replace('/\s+/','',(string)($properties['postcode'] ?? ''));
    $result = ['success'=>true,'address'=>mb_substr(implode(', ',$parts),0,500),
        'pincode'=>preg_match('/^[0-9]{6}$/',$postcode) ? $postcode : ''];
    $_SESSION['simple_address_cache'][$key] = ['time'=>time(),'result'=>$result];
    if (count($_SESSION['simple_address_cache'])>30) {
        array_shift($_SESSION['simple_address_cache']);
    }
    echo json_encode($result);
} catch (Throwable $error) {
    error_log('SmartFuel address lookup: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success'=>false,'message'=>'Automatic address lookup is unavailable. Type the address and pincode, or try Use current location again.']);
}
