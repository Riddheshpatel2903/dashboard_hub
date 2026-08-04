<?php
header('Content-Type: text/plain');
echo "Real path of this file: " . __FILE__ . "\n";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
