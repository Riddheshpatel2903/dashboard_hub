<?php
/**
 * Run git commands from repository root.
 */
header('Content-Type: text/plain');

$repoDir = realpath(__DIR__ . '/../..');
$cmd = "git -C " . escapeshellarg($repoDir) . " show 096974f -- dashboard/pages/post_history.php";
echo "Running: $cmd\n\n";

$output = shell_exec($cmd . ' 2>&1');
echo $output;
