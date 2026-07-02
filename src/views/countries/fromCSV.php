<?php
/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

function fromCsv()
{
    $sourcePath = __DIR__ . '/translations.csv';
    if (!file_exists($sourcePath)) {
        echo "no source file\n";
        exit();
    }

    $f = fopen($sourcePath, 'rb+');
    // skip header
    fgetcsv($f);

    $en = $es = $de = $fr = $it = array();

    while ($line = fgetcsv($f)) {
        $en[$line[0]][$line[1]] = $line[2];
        $es[$line[0]][$line[1]] = $line[3];
        $de[$line[0]][$line[1]] = $line[4];
        $fr[$line[0]][$line[1]] = $line[5];
        $it[$line[0]][$line[1]] = $line[6];
    }

    fclose($f);

    exportJson('en', $en);
    exportJson('es', $es);
    exportJson('de', $de);
    exportJson('fr', $fr);
    exportJson('it', $it);
}

function exportJson($lang, $data)
{
    file_put_contents(
        __DIR__ . "/fromCSV.php",
        str_replace(
            '    ',
            '  ',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        )
    );
}

fromCsv();