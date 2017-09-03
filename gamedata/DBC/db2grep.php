<?php

// strings WoW.exe | php db2grep.php > possible-db2s.txt

while (!feof(STDIN)) {
    $chunk = trim(stream_get_line(STDIN, 131072, "\n"));
    if (preg_match('/^[A-Z][A-Za-z_-]+$/', $chunk) && preg_match('/[a-z]/', $chunk)) {
        echo "DBFilesClient\\$chunk.db2\n";
    }
}
