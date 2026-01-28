<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp;

final class Greeting
{
    public static function hello(string $name): string
    {
        return 'Hello, '.$name.'!';
    }
}
