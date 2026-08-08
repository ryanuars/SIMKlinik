<?php
$file = 'asesmen/kecantikan.php';
$content = file_get_contents($file);

// Replace common garbled patterns
$content = preg_replace('/[^\x00-\x7F]+/', '', $content);

file_put_contents($file, $content);
echo "Successfully stripped non-ASCII characters from $file.\n";
