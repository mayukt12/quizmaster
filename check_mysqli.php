<?php
echo "<pre>";

if (function_exists('mysqli_connect')) {
    echo "mysqli is ENABLED ✅\n";
} else {
    echo "mysqli is NOT enabled ❌\n";
}

phpinfo(INFO_MODULES);