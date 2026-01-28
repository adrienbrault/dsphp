# DSPy PHP

> **Experimental** — This library is in early design/development. The API will change.

A PHP 8.4+ port of [DSPy](https://github.com/stanfordnlp/dspy) — the framework for programming (not prompting) language models. Built on [Symfony AI Platform](https://symfony.com/blog/introducing-symfony-ai).

## Core Idea

Define **signatures** (typed I/O contracts), compose **modules** (Predict, ChainOfThought), and let **optimizers** tune prompts automatically — no hand-written prompt engineering.

## Quick Example

```php
use DSPy\{InputField, OutputField, LM, ChainOfThought, Reasoning};

/** Answer questions with short factoid answers. */
class BasicQA
{
    public function __construct(
        #[InputField(desc: 'question to answer')]
        public readonly string $question,

        #[OutputField(desc: 'often between 1 and 5 words')]
        public readonly string $answer = '',
    ) {}
}

$lm = new LM($platform, 'gpt-4o-mini');
$cot = new ChainOfThought(BasicQA::class, lm: $lm);

$result = ($cot)(new BasicQA(question: 'What is the capital of France?'));
echo $result->reasoning; // step-by-step thinking
echo $result->answer;    // "Paris"
```

## Key Concepts

| Concept | What it is |
|---|---|
| **Signature** | Plain PHP class with `#[InputField]` / `#[OutputField]` attributes. Serves as I/O contract, LLM result, and training example. |
| **Predict** | Generic `Predict<T>` component — calls the LLM and returns a typed signature instance. |
| **ChainOfThought** | Wraps Predict, encourages step-by-step reasoning, returns `Reasoning<T>`. |
| **Adapter** | Controls how signatures become LLM messages. `ChatAdapter` (field markers) or `JsonAdapter` (native JSON mode). |
| **Module** | Any PHP class composing Predict/ChainOfThought — no base class required. |
| **Optimizer** | `BootstrapFewShot`, `BootstrapFewShotWithRandomSearch`, `MIPROv2` — automatically find good few-shot demos. |
| **Metric** | Plain callable `(object, object): float\|bool`. Built-in `Metrics::exactMatch()` and `Metrics::f1()`. |

## Design Principles

- **No base classes** — Signatures and modules are plain PHP classes
- **No global state** — LM is injected via constructor, no `DSPy::configure()`
- **Full PHPStan support** — Generic templates, `@mixin`, native validation at level max
- **Composition over inheritance** — All framework classes are `final`
- **Symfony-native** — Works with autowiring, DI container, AI Platform bridges

## Requirements

- PHP 8.4+
- `symfony/ai-platform` ^0.3

## Development

```bash
composer test       # PHPUnit
composer phpstan    # Static analysis (level max)
composer cs-fix     # Code style (PSR-12)
composer ci         # All of the above
```

## License

MIT
