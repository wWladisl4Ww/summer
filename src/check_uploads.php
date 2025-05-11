<?php
$uploadDir = __DIR__ . '/uploads/';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo '<h1>Список файлов в папке uploads:</h1>';
    echo '<ul>';
    foreach (array_diff($files, ['.', '..']) as $file) {
        echo "<li><a href='/uploads/$file' target='_blank'>$file</a></li>";
    }
    echo '</ul>';
} else {
    echo 'Папка uploads не существует.';
}
?>
