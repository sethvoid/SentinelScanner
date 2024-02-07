<?php
//types of value
/**
 * single-key-value = for example strict-tansportType: key=value
 * value = strictsomething: value
 * multivalue = something: value1, value2, value3
 * matching-type: contain should-not-contain, should-not-be-set
 */

$target = $argv[1];
$configFile = $argv[2];
$red = "\033[0;31m";
$green = "\033[0;32m";
$reset = "\033[0m"; // Reset to default color

// Initialize cURL session
$ch = curl_init();

// Set the URL
curl_setopt($ch, CURLOPT_URL, $target);

// Set to retrieve headers only
curl_setopt($ch, CURLOPT_HEADER, true);

// Set to not include the body in the output
curl_setopt($ch, CURLOPT_NOBODY, true);

// Set to return the transfer as a string
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute cURL session
$response = curl_exec($ch);

// Check for errors
if($response === false) {
    echo 'cURL error: ' . curl_error($ch);
    exit();
}
curl_close($ch);
$headerContentArray = [];
foreach(preg_split("/((\r?\n)|(\r\n?))/", $response) as $line){
    if (str_contains($line, ':')) {
        $headerArray = explode(':', $line);
        if (isset($headerArray[0]) && isset($headerArray[1])) {
            $headerArray[0] = strtolower($headerArray[0]);
            $headerArray[1] = strtolower($headerArray[1]);
            $multiValues = null;
            $singleValueKeyPair = null;
            if (str_contains($headerArray[1], ',')) {
                $headerArray[1] = preg_replace('/\s+/', '', $headerArray[1]);
                $multiValues = explode(',', $headerArray[1]);
            }

            if (str_contains($headerArray[1], '=')) {
                $a = explode('=', $headerArray[1]);
                if (isset($a[0]) && $a[1]) {
                    $abrevValue = explode(';', $a[1]);
                    if (isset($abrevValue[0])) {
                        $singleValueKeyPair[preg_replace('/\s+/', '', $a[0])] = preg_replace('/\s+/', '',$abrevValue[0]);
                    }
                }
            }

            if (!empty($multiValues)) {
                foreach ($multiValues as $value) {
                    $headerContentArray[$headerArray[0]][] = $value;
                }
            } else if (!empty($singleValueKeyPair)) {
                $headerContentArray[$headerArray[0]] = $singleValueKeyPair;
            } else {
                $headerContentArray[$headerArray[0]] = preg_replace('/\s+/', '', $headerArray[1]);
            }
        }
    }
}

$configArray = json_decode(file_get_contents($configFile), true);
$pass  = [];
$fail = [];
foreach ($configArray as $testName => $test) {
    $test['key'] = strtolower($test['key']);
    if (!isset($headerContentArray[$test['key']])) {
        $fail[] = [
            'name' => $test['key'],
            'result' => 'Header is not set.'
        ];
        continue;
    }

    if ($test['type'] == 'single-key-value') {
        $singleValueKey = '';
        $singleValueValue = '';

        foreach ($test['value'] as $key => $val) {
            $singleValueKey = strtolower($key);
            $singleValueValue = strtolower($val);
        }

        $isRange = false;
        if (str_contains('-', $singleValueValue)) {
            // This is a range!
            $rangeArray = explode('-', $singleValueValue);
            if (isset($rangeArray[0]) && isset($rangeArray[1])) {
                $lowest = $rangeArray[0];
                $highest = $rangeArray[1];

                $isRange = is_numeric($lowest) && is_numeric($highest);
            }
        }

        if (!isset($headerContentArray[$test['key']][$singleValueKey])) {
            $fail[] = [
                'name' => $test['key'],
                'result' => 'Header ' . $test['key'] . ' does not contain the key value ' .$singleValueKey
            ];
            continue;
        }
        if ($test['matching-type'] == 'should-not-contain') {
            $failFlag = false;

            if ($isRange) {
                $failFlag = ($headerContentArray[$test['key']][$singleValueKey] <= $highest
                    && $headerContentArray[$test['key']][$singleValueKey] >= $lowest
                );
            } else {
                $failFlag = $headerContentArray[$test['key']][$singleValueKey] == $singleValueValue;
            }

            if ($failFlag) {
                $fail[] = [
                    'name' => $test['key'],
                    'result' => 'Header ' . $test['key'] . ' contains invalid value ' . $singleValueValue
                ];
                continue;
            }
        } else {
            $passFlag = false;
            if ($isRange) {
                $passFlag = ($headerContentArray[$test['key']][$singleValueKey] <= $highest
                    && $headerContentArray[$test['key']][$singleValueKey] >= $lowest
                );
            } else {
                $passFlag = $headerContentArray[$test['key']][$singleValueKey] == $singleValueValue;
            }

            if ($passFlag) {
                $pass[] = [
                    'name' => $test['key'],
                    'result' => 'Header ' . $test['key'] . ' contains correct value ' . $singleValueValue
                ];
                continue;
            }
        }

        $fail[] = [
            'name' => $test['key'],
            'result' => 'Header ' . $test['key'] . ' contains invalid value ' .$singleValueValue
        ];
    } else if ($test['type'] == 'value') {
        if ($test['matching-type'] == 'should-not-be-set') {
            if (isset($headerContentArray[$test['key']])) {
                $fail[] = [
                    'name' => $test['key'],
                    'result' => 'Prohibited header set ' . $test['key']
                ];
                continue;
            }
        }

        if (!isset($headerContentArray[$test['key']])) {
            $fail[] = [
                'name' => $test['key'],
                'result' => 'Header is not set or is missing.'
            ];
            continue;
        }

        $isRange = false;
        if (str_contains('-', $test['value'])) {
            // This is a range!
            $rangeArray = explode('-', $test['value']);
            if (isset($rangeArray[0]) && isset($rangeArray[1])) {
                $lowest = $rangeArray[0];
                $highest = $rangeArray[1];

                $isRange = is_numeric($lowest) && is_numeric($highest);
            }
        }

        if ($test['matching-type'] == 'should-not-contain') {
            $failFlag = false;
            if ($isRange) {
                $failFlag = ($headerContentArray[$test['key']] <= $highest
                    && $headerContentArray[$test['key']] >= $lowest
                );
            } else {
                $failFlag = str_contains($headerContentArray[$test['key']], strtolower($test['value']));
            }

            if ($failFlag) {
                $fail[] = [
                    'name' => $test['key'],
                    'result' => 'Header ' . $test['key'] . ' contains invalid value ' .$test['value']
                ];
                continue;
            }
        } else {
            $passFlag = false;

            if ($isRange) {
                $passFlag = ($headerContentArray[$test['key']] <= $highest
                    && $headerContentArray[$test['key']] >= $lowest
                );
            } else {
                $passFlag = $headerContentArray[$test['key']] == strtolower($test['value']);
            }
            if ($passFlag) {
                $pass[] = [
                    'name' => $test['key'],
                    'result' => 'Header ' . $test['key'] . ' contains correct value ' .$test['value']
                ];
                continue;
            }
        }

        $fail[] = [
            'name' => $test['key'],
            'result' => 'Header ' . $test['key'] . ' contains invalid value ' .$test['value']
        ];
    } else if ($test['type'] == 'multivalue') {
        if (!isset($headerContentArray[$test['key']])) {
            $fail[] = [
                'name' => $test['key'],
                'result' => 'Header is not set or is missing.'
            ];
            continue;
        }

        $passCount = 0;
        $count = count($test['value']);
        foreach($test['value'] as $val) {
            $val = strtolower($val);
            if ($test['matching-type'] == 'should-not-contain') {
                if (in_array($val, $headerContentArray[$test['key']])) {
                    $fail[] = [
                        'name' => $test['key'],
                        'result' => 'Header ' . $test['key'] . ' contains invalid value ' .$val
                    ];
                    continue;
                }
                $passCount ++;
            } else {
                if (in_array($val, $headerContentArray[$test['key']])) {
                    $pass[] = [
                        'name' => $test['key'],
                        'result' => 'Header ' . $test['key'] . ' contains correct value ' .$val
                    ];
                    $passCount++;
                }
            }
        }

        if ($passCount < $count) {
            $fail[] = [
                'name' => $test['key'],
                'result' => 'Header ' . $test['key'] . ' is missing some values '
            ];
        }
    }
}

echo $green . ' ------------------------------------ PASS --------------------------------------- ' . $reset . PHP_EOL;
foreach ($pass as $p) {
    echo  $green .  $p['name'] . ' ' . $p['result'] . $reset . PHP_EOL;
}
echo PHP_EOL;

if(!empty($fail)) {
    echo $red . ' ------------------------------------ FAIL --------------------------------------- ' . $reset . PHP_EOL;
    foreach ($fail as $f) {
        echo $red . $f['name'] . ' ' . $f['result'] . $reset . PHP_EOL;
    }
}

if (in_array('-vvv', $argv)) {
    print_r($headerContentArray);
}