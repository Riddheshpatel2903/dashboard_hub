<?php
parse_str('include_platform=1&limit=5', $_GET);
require __DIR__ . '/hub/api/posts.php';
