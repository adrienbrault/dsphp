<?php

declare(strict_types=1);

namespace DSPy\Tests;

use Attribute;
use DSPy\OutputField;
use DSPy\Tests\Fixtures\BasicQA;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class OutputFieldTest extends TestCase
{
    #[Test]
    public function itIsAPropertyAttribute(): void
    {
        $ref = new ReflectionClass(OutputField::class);
        $attrs = $ref->getAttributes(Attribute::class);
        self::assertCount(1, $attrs);
        self::assertSame(Attribute::TARGET_PROPERTY, $attrs[0]->newInstance()->flags);
    }

    #[Test]
    public function itStoresDescription(): void
    {
        $field = new OutputField(desc: 'often between 1 and 5 words');
        self::assertSame('often between 1 and 5 words', $field->desc);
    }

    #[Test]
    public function itDefaultsToEmptyDescription(): void
    {
        $field = new OutputField();
        self::assertSame('', $field->desc);
    }

    #[Test]
    public function itCanBeReadFromPropertyReflection(): void
    {
        $ref = new ReflectionProperty(BasicQA::class, 'answer');
        $attrs = $ref->getAttributes(OutputField::class);
        self::assertCount(1, $attrs);

        $field = $attrs[0]->newInstance();
        self::assertSame('often between 1 and 5 words', $field->desc);
    }
}
