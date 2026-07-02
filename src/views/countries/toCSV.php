<?php
/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

function toCsv()
{
    $en = json_decode(file_get_contents(__DIR__ . '/en.json'), true);
    $es = json_decode(file_get_contents(__DIR__ . '/es.json'), true);
    $de = json_decode(file_get_contents(__DIR__ . '/de.json'), true);
    $fr = json_decode(file_get_contents(__DIR__ . '/fr.json'), true);
    $it = json_decode(file_get_contents(__DIR__ . '/it.json'), true);

    $resultPath = __DIR__ . '/translations.csv';
    if (file_exists($resultPath)) {
        unlink($resultPath);
    }

    $f = fopen($resultPath, 'cb+');
    fputcsv($f, array('Group', 'key', 'English', 'Spanish', 'German', 'French', 'Italian'));

    foreach ($en as $group => $translations) {
        foreach ($translations as $key => $value) {
            $line = array();
            $line[] = $group;
            $line[] = $key;
            $line[] = $value;
            $line[] = isset($es[$group][$key]) ? $es[$group][$key] : '';
            $line[] = isset($de[$group][$key]) ? $de[$group][$key] : '';
            $line[] = isset($fr[$group][$key]) ? $fr[$group][$key] : '';
            $line[] = isset($it[$group][$key]) ? $it[$group][$key] : '';

            fputcsv($f, $line);
        }
    }

    fclose($f);
}

toCsv();