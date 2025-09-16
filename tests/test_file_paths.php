<?php

$index_content = file_get_contents(__DIR__ . '/../index.php');
preg_match_all("/include '([^']+)';/", $index_content, $matches);

$included_files = $matches[1];
$all_files_exist = true;
$missing_files = [];

foreach ($included_files as $file) {
    if (!file_exists(__DIR__ . '/../' . $file)) {
        $all_files_exist = false;
        $missing_files[] = $file;
    }
}

if ($all_files_exist) {
    echo "Test Passed: All included files in index.php exist.\n";
    exit(0);
} else {
    echo "Test Failed: The following included files are missing:\n";
    echo implode("\n", $missing_files) . "\n";
    exit(1);
}
?>
