<?php

declare(strict_types=1);

namespace AdrienBrault\DsPhp\Tests\Fixtures;

use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\OutputField;

/** Verify that the text is based on the provided context. */
class CheckCitationFaithfulness
{
    public function __construct(
        #[InputField(desc: 'facts here are assumed to be true')]
        public readonly string $context,
        #[InputField]
        public readonly string $text,
        #[OutputField]
        public readonly bool $faithful = false,

        /** @var list<string> */
        #[OutputField(desc: 'verbatim supporting excerpts')]
        public readonly array $evidence = [],
    ) {}
}
