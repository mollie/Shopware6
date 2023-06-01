<?php

echo PHP_EOL;

$content = file_get_contents(__DIR__ . '/../../src/Resources/config/config.xml');


checkValue('name', $content);
checkValue('defaultValue', $content);
checkValue('title', $content);
checkValue('label', $content);
checkValue('helpText', $content);


echo 'Plugin Configuration XML is valid!' . PHP_EOL;
echo PHP_EOL;
exit(0);


# ----------------------------------------------------------------------------------------------------


function checkValue($tag, $content)
{
    if (stringContains('></' . $tag . '>', $content)) {
        echo '** ERROR: Plugin Configuration XML contains empty ' . $tag . PHP_EOL;
        echo PHP_EOL;
        exit(1);
    }
}

function stringContains($search, $text)
{
    if (strpos($text, $search) !== false) {
        return true;
    } else {
        return false;
    }
}
