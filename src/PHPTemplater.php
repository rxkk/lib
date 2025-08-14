<?php

namespace Rxkk\Lib;

use InvalidArgumentException;

class PHPTemplater {
    /**
     * Рендерит PHP-шаблон и возвращает результат как строку.
     *
     * @param string $pathToFile Путь до PHP-файла с шаблоном
     * @param array $vars Ассоциативный массив переменных для шаблона
     * @return string Сгенерированный текст
     * @throws InvalidArgumentException Если файл не найден или не читается
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