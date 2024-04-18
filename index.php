<?php
function isv($is = '', $d = false)
{
    if (array_key_exists($is, $_POST)) {
        return $_POST[$is];
    } elseif (array_key_exists($is, $_GET)) {
        return $_GET[$is];
    } elseif (array_key_exists($is, $_FILES)) {
        return $_FILES[$is];
    }
    return $d;
}
function _in_str($str, $arr = null, $lower = true, &$word = "")
{
    if (!$arr || !$str)
        return false;
    if (!is_array($arr))
        $arr = [$arr];
    if (!is_array($str))
        $strs = [$str];
    foreach ($strs as $str) {
        foreach ($arr as $key => $value) {
            if ($lower) {
                $str = strtolower($str);
                $value = strtolower($value);
            }
            if (strpos($str, $value) !== false) {
                $word = $value;
                return $value;
            }
        }
    }
    return false;
}

function _file_get_contents($url, $headers = [], $data = null, $timeout = 30, &$error = null)
{

    $agent = array_filter($headers, fn ($proxy) => (_in_str($proxy, "User-Agent")));
    if (!$agent) {
        $headers[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36";
    }
    $j = array_filter($headers, fn ($proxy) => (_in_str($proxy, "application/json") && _in_str($proxy, "Content-Type")));
    $f = array_filter($headers, fn ($proxy) => (_in_str($proxy, "form-urlencoded") && _in_str($proxy, "Content-Type")));
    $data = ($data && $j ? json_encode($data) : $data);
    $data = ($data && $f ? http_build_query($data) : $data);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => ($data ? "POST" : 'GET'),
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => $headers,
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        $error = $err;
        return false;
    }
    return $response;
}

function bard($d = [])
{
    if (!$d["prompt"]) {
        $d["prompt"] = isset($d["content"]) ? $d["content"] : null;
    }

    $safetySettings = [];
    $safetySettings[] = ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"];
    $safetySettings[] = ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"];
    $safetySettings[] = ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"];
    $safetySettings[] = ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"];
    $d += [
        "maxOutputTokens" => 2048,
        "temperature" => 0.9,
        "topK" => 1,
        "topP" => 1,
        "key" => null,
        "safetySettings" =>  $safetySettings
    ];
    extract($d);
    $j = [];
    $j["contents"] = [
        "parts" => [
            ["text" => $prompt]
        ]
    ];
    $j["safetySettings"] = $safetySettings;
    $j["generationConfig"] = [
        "temperature" => $temperature,
        "topK" => $topK,
        "topP" => $topP,
        "maxOutputTokens" => $maxOutputTokens,
        "stopSequences" => []
    ];
    $jsonData = _file_get_contents(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.0-pro:generateContent?key=' . $key,
        ['Content-Type: application/json'],
        $j,
        (3 * 60)
    );
    $json = json_decode($jsonData, true);
    return $json;
}

function send_request($d = [])
{
    $d += [
        "url" => null,
        "headers" => [],
        "data" => [],
        "timeout" => 60
    ];
    extract($d);
    $response = _file_get_contents($url, $headers, $data, $timeout, $err);
    if ($response) {
        return ["success" => true, "data" => $response];
    }
    return ["success" => false, "error" => $err];
}
$data = json_decode(file_get_contents('php://input'), true);
if ($data && isset($data["data"])) {
    $_POST += $data["data"];
} else if ($data) {
    $_POST += $data;
}
$_POST += $_GET;
$_POST = array_map(function ($v) {
    if (is_string($v)) {
        $vc = strtolower($v);
        if ($vc == "false" || $vc == "null" || $vc == "none" || $vc == "0" || $vc == "False")
            $v = false;
        else if ($v == "true")
            $v = true;
    }
    return $v;
}, $_POST);


$get = isv("get");
switch ($get) {
    case 'bard':
        $response = bard($_POST);
        echo json_encode($response);
        break;
    case 'request':
        $response = send_request($_POST);
        echo json_encode($response);
        break;
    default:
        header('HTTP/1.1 401 Unauthorized', true, 401);
        break;
}
