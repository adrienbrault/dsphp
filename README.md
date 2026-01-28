# DSPy PHP

> **Experimental** — This library is under active development. The API may change.

A PHP 8.4+ port of [DSPy](https://github.com/stanfordnlp/dspy) — the framework for programming (not prompting) language models. Built on [Symfony AI Platform](https://symfony.com/blog/introducing-symfony-ai).

## Install

```bash
composer require adrienbrault/dsphp
```

## Core Idea

Define **signatures** (typed I/O contracts), compose **modules** (Predict, ChainOfThought), and let **optimizers** tune prompts automatically — no hand-written prompt engineering.

## Quick Example

```php
use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\OutputField;
use AdrienBrault\DsPhp\LM;
use AdrienBrault\DsPhp\ChainOfThought;

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
| **Evaluate** | Score a module against a dataset with any metric callable. |
| **Metric** | Plain callable `(object, object): float\|bool`. Built-in `Metrics::exactMatch()` and `Metrics::f1()`. |
| **PredictState** | Save/load optimized demos to/from JSON for persistence. |

## End-to-End Example

```php
use AdrienBrault\DsPhp\BootstrapFewShot;
use AdrienBrault\DsPhp\ChainOfThought;
use AdrienBrault\DsPhp\Evaluate;
use AdrienBrault\DsPhp\InputField;
use AdrienBrault\DsPhp\LM;
use AdrienBrault\DsPhp\Metrics;
use AdrienBrault\DsPhp\OutputField;
use AdrienBrault\DsPhp\PredictState;
use AdrienBrault\DsPhp\Reasoning;

// 1. Define a signature
/** Answer questions using retrieved context. */
class GenerateAnswer
{
    public function __construct(
        /** @var list<string> */
        #[InputField(desc: 'retrieved passages')]
        public readonly array $context,

        #[InputField]
        public readonly string $question,

        #[OutputField(desc: 'concise factual answer')]
        public readonly string $answer = '',
    ) {}
}

// 2. Build a module (plain PHP class)
class RAG
{
    private ChainOfThought $generate;

    public function __construct(private readonly LM $lm)
    {
        $this->generate = new ChainOfThought(GenerateAnswer::class, lm: $lm);
    }

    public function __invoke(BasicQA $input): Reasoning
    {
        $context = $this->retrieve($input->question);
        return ($this->generate)(new GenerateAnswer(
            context: $context,
            question: $input->question,
        ));
    }

    private function retrieve(string $question): array { return []; }
}

// 3. Prepare training data
$trainset = [
    new BasicQA(question: 'What castle did David Gregory inherit?', answer: 'Kinnairdy Castle'),
    new BasicQA(question: 'What is the capital of France?', answer: 'Paris'),
];

// 4. Evaluate & optimize
$metric = Metrics::exactMatch('answer');
$evaluator = new Evaluate(dataset: $trainset, metric: $metric);

$baseline = $evaluator(new RAG($lm));
echo "Baseline: {$baseline}\n";

$optimizer = new BootstrapFewShot(metric: $metric, maxBootstrappedDemos: 4);
$compiled = $optimizer->compile(student: new RAG($lm), trainset: $trainset);

$optimized = $evaluator($compiled);
echo "Optimized: {$optimized}\n";

// 5. Save & load state
$predictState = new PredictState();
$state = $predictState->dump($compiled);
file_put_contents('state.json', json_encode($state));

$fresh = new RAG($lm);
$predictState->load($fresh, json_decode(file_get_contents('state.json'), true));
```

## Symfony Integration

```yaml
# config/services.yaml
services:
    AdrienBrault\DsPhp\LM:
        arguments:
            $platform: '@ai.platform.openai'
            $model: 'gpt-4o-mini'

    AdrienBrault\DsPhp\PredictState: ~

    App\AI\RAG:
        arguments:
            $lm: '@AdrienBrault\DsPhp\LM'
```

## Design Principles

- **No base classes** — Signatures and modules are plain PHP classes
- **No global state** — LM is injected via constructor
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
