<?php
header('Content-Type: text/plain');
echo "=== GIT LOG LATEST STAT ===\n";
echo shell_exec('git log -n 1 --stat') ?: "No output.\n";
