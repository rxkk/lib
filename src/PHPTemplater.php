<?php

namespace Rxkk\Lib;

use InvalidArgumentException;

class PHPTemplater {
    /**
     * Render a PHP template and return the result as a string.
     *
     * @param string $pathToFile Path to the PHP template file
     * @param array $vars Associative array of variables for the template
     * @return string Generated text
     * @throws InvalidArgumentException If the file does not exist or is not readable
     */
    public static function render(string $pathToFile, array $vars = []): string
    {
        if (!is_file($pathToFile) || !is_readable($pathToFile)) {
            throw new InvalidArgumentException("Template file not found or not readable: {$pathToFile}");
        }

        extract($vars, EXTR_SKIP);

        ob_start();
        include $pathToFile;
        return ob_get_clean();
    }
}