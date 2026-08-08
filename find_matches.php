<?php
$lines = file('c:/xampp/htdocs/SIMKlinik/java/DlgRawatJalan.java');
$out = "";
foreach ($lines as $num => $line) {
    if (stripos($line, 'jns_perawatan') !== false && stripos($line, 'select') !== false) {
        $out .= ($num + 1) . ": " . trim($line) . "\n";
    }
}
file_put_contents('c:/xampp/htdocs/SIMKlinik/query_matches.txt', $out);
echo "Written matches to query_matches.txt\n";
