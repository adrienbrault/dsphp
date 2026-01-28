<?php

declare(strict_types=1);

namespace AdrienBrault\Dsphp;

final class Greeting
{
    public static function hello(string $name): string
    {
        return 'Hello, '.$name.'!';
    }
}
