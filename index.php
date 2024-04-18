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

    if (_in_str($url, ["app.restoviebelle", "everysimply"]))
        $headers[] = 'Authorization: Basic bW9odGFzbTptb2h0YXNtMTBRQEA=';
    $agent = array_filter($headers, fn ($proxy) => (_in_str($proxy, "User-Agent")));
    if (!$agent) {
        $headers[] = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36";
    }
    $j = array_filter($headers, fn ($proxy) => (_in_str($proxy, "application/json") && _in_str($proxy, "Content-Type")));
    $f = array_filter($headers, fn ($proxy) => (_in_str($proxy, "form-urlencoded") && _in_str($proxy, "Content-Type")));
    $data = ($data && $j ? json_encode($data) : $data);
    $data = ($data && $f ? http_build_query($data) : $data);
    #print_r($data);
    // echo $url;
    // print_r($headers);
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

function bard($d)
{
    $keys = [
        "AIzaSyDbEKAYJICTvG45GQyYHh97LbJmAMJ4dEk",
        "AIzaSyDn_j28y4bfXyulcxpIIUYjcZi7d3Bidz8",
    ];
    if (!$d["prompt"]) {
        $d["prompt"] = $d["content"];
    }
    $d += [
        "temperature" => 0.7,
        "topP" => 1,
    ];
    $j = json_decode('{ 
      "contents": [
            {
              "parts": []
            }
       ],
      "generationConfig": {
        "temperature": 0.9,
        "topK": 1,
        "topP": 1,
        "maxOutputTokens": 2048,
        "stopSequences": []
      }}', true);
    $j["contents"][0]["parts"][] = ["text" => $d["prompt"]];
    $j["safetySettings"][] = ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_NONE"];
    $j["safetySettings"][] = ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_NONE"];
    $j["safetySettings"][] = ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_NONE"];
    $j["safetySettings"][] = ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_NONE"];
    $j["generationConfig"]["temperature"] = $d["temperature"];
    $j["generationConfig"]["topP"] = $d["topP"];
    //return $j;
    $index = 0;
    $key = $keys[$index];
    $jsonData = _file_get_contents(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.0-pro:generateContent?key=' . $key,
        ['Content-Type: application/json'],
        $j,
        (3 * 60)
    );
    $json = json_decode($jsonData, true);
    unset($d["content"]);
    unset($d["prompt"]);
    $return = ["success" => true];
    #return $json;
    if (!isset($json["candidates"]) || !$json || !isset($json["candidates"][0]) || !isset($json["candidates"][0]["content"])) {
        $finish_reason = isset($json["candidates"][0]["finishReason"]) ? $json["candidates"][0]["finishReason"] : null;
        $error = ($json && isset($json["error"]) ? $json["error"] : ($json ? $json : $jsonData));
        $error = $finish_reason ? $finish_reason : $error;
        $return = ["success" => false, "error" => $error];
        $return["error"] = (isset($return["error"]["message"]) ? $return["error"]["message"] : $return["error"]);
        if (is_array($return["error"])) {
            $return["error"] = json_encode($return["error"]);
        }
        return $return;
    } else if (isset($json["candidates"])) {
        $return["content"] = $json["candidates"][0]["content"]["parts"][0]["text"];
        //$return["safetyRatings"] = $json["candidates"][0]["safetyRatings"];
        $return["finish_reason"] = $json["candidates"][0]["finishReason"];
        $return["account"] = ["index" => $index, "key" => $key];
        unset($json["candidates"]);
        unset($json["promptFeedback"]);
    }
    $return = array_merge($return, $json);
    return $return;
}

if (isv("ask")) {
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
    $response = bard($_POST);
    echo json_encode($response);
}
