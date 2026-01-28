<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests;

use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\Tests\Fixtures\BasicQA;
use Attribute;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class InputFieldTest extends TestCase
{
    #[Test]
    public function itIsAPropertyAttribute(): void
    {
        $ref = new ReflectionClass(InputField::class);
        $attrs = $ref->getAttributes(Attribute::class);
        self::assertCount(1, $attrs);
        self::assertSame(Attribute::TARGET_PROPERTY, $attrs[0]->newInstance()->flags);
    }

    #[Test]
    public function itStoresDescription(): void
    {
        $field = new InputField(desc: 'question to answer');
        self::assertSame('question to answer', $field->desc);
    }

    #[Test]
    public function itDefaultsToEmptyDescription(): void
    {
        $field = new InputField();
        self::assertSame('', $field->desc);
    }

    #[Test]
    public function itCanBeReadFromPropertyReflection(): void
    {
        $ref = new ReflectionProperty(BasicQA::class, 'question');
        $attrs = $ref->getAttributes(InputField::class);
        self::assertCount(1, $attrs);

        $field = $attrs[0]->newInstance();
        self::assertSame('question to answer', $field->desc);
    }
}
