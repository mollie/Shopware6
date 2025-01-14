<?php
/**
 * @param string $key
 * @return string|bool|null
 */
function getArgument(string $argument, string $default = null)
{
    $argv = $_SERVER['argv'];
//remove command from args
    array_shift($argv);

    $argumentsWithEqualSign = [];
    foreach ($argv as $index => $value) {
        if (str_starts_with($value, '--')) {
            $argumentsWithEqualSign[$index] = $value;
            continue;
        }
        $argumentsWithEqualSign[$index - 1] .= "=" . $value;
    }
    $parsedArguments = [];
    foreach ($argumentsWithEqualSign as $argumentWithEqual) {
        $argumentWithEqual = trim(str_replace("-", "", $argumentWithEqual));
        $argumentParts = explode("=", $argumentWithEqual);
        $argumentName = $argumentParts[0];
        $argumentValue = true;
        if (isset($argumentParts[1])) {
            $argumentValue = $argumentParts[1];
        }

        $parsedArguments[$argumentName] = $argumentValue;
    }

    if (! isset($parsedArguments[$argument])) {
        return $default;
    }
    return $parsedArguments[$argument];
}
