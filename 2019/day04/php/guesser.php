<?php

list($min, $max) = explode('-', trim($argv[1]), 2);
$min = intval($min);
$max = intval($max);

function isValid(int $pass): bool {
    $pass_str = (string)$pass;
    // It is a six-digit number
    if (strlen($pass_str) !== 6) {
        return false;
    }
    $parts = str_split($pass_str);
    sort($parts);
    // Going from left to right, the digits never decrease; they only ever 
    if (implode($parts) !== $pass_str) {
        return false;
    }
    // Two adjacent digits are the same (like 22 in 122345)
    if (count_chars($pass_str, 3) === $pass_str) { // this returns a string of unique chars
        return false;
    }
    return true;
}

//echo isValid(111111) ? "Y\n" : "N\n";
//echo isValid(223450) ? "Y\n" : "N\n";
//echo isValid(123789) ? "Y\n" : "N\n";

$possible = [];
for ($p = $min; $p <= $max; $p++) {
    if (isValid($p)) {
        $possible[] = $p;
    }
}

function actuallyValid(int $possible) {
    $possible_str = (string)$possible;
    foreach (count_chars($possible_str, 1) as $count) {
        if ($count === 2) {
            return true;
        }
    }
    return false;
}

//echo actuallyValid(112233) ? "Y\n" : "N\n";
//echo actuallyValid(123444) ? "Y\n" : "N\n";
//echo actuallyValid(111122) ? "Y\n" : "N\n";

$valid = array_filter($possible, 'actuallyValid');

$possible_count = count($possible);
$valid_count = count($valid);

echo
"
possible: {$possible_count}
valid: {$valid_count}
";
