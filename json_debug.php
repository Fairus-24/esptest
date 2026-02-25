<?php
// Debug helper for CLI JSON encoding/decoding
if ($argc > 1) {
    $payload = $argv[1];
    echo "Raw arg: $payload\n";
    $decoded = json_decode($payload, true);
    if ($decoded === null) {
        echo "json_decode error: " . json_last_error_msg() . "\n";
    } else {
        echo "json_decode success: ";
        print_r($decoded);
    }
    $reencoded = json_encode($decoded);
    echo "json_encode: $reencoded\n";
}
