<?php

/**
 * DSPy PHP — API Design Document (v3)
 *
 * A modern PHP 8.4+ port of DSPy (Declarative Self-improving Python).
 * Built on top of Symfony AI Platform for LLM communication.
 * Fully statically typed, targeting PHPStan level max.
 *
 * Principles:
 *   - Composition over inheritance — no base classes for user code
 *   - Constructor injection — no static singletons or global state
 *   - Plain PHP objects — signatures are just DTOs with attributes
 *   - Final framework classes — extend via interfaces, not subclassing
 *   - Native PHPStan — full type safety without custom extensions
 *
 * Namespace: DSPy
 * Package: adrienbrault/dsphp
 * Requires: PHP 8.4+, symfony/ai-platform ^0.3
 */

namespace DSPy;

use Symfony\AI\Platform\PlatformInterface;

// ============================================================================
// 1. SIGNATURES — Plain PHP classes with attributes
// ============================================================================
//
// A Signature is a plain PHP class. No base class to extend.
// Properties annotated with #[InputField] or #[OutputField] declare the
// I/O contract. The class docblock becomes the LLM task instruction.
//
// Constructor promotion + readonly gives us immutable, type-safe DTOs
// that work as both predictions (LLM output) and training examples.
//
// Convention: InputField parameters are required, OutputField parameters
// have defaults. This lets you construct a "partial" instance with only
// inputs — enabling full PHPStan validation at the call site.

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class InputField
{
    public function __construct(
        public readonly string $desc = '',
    ) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class OutputField
{
    public function __construct(
        public readonly string $desc = '',
    ) {}
}

// --- Signature examples ---

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

/** Classify the sentiment of a sentence. */
class SentimentClassification
{
    public function __construct(
        #[InputField]
        public readonly string $sentence,

        #[OutputField(desc: 'one of: positive, negative, neutral')]
        public readonly Sentiment $sentiment = Sentiment::Neutral,
    ) {}
}

enum Sentiment: string
{
    case Positive = 'positive';
    case Negative = 'negative';
    case Neutral = 'neutral';
}

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

// --- Creating signature instances ---
//
// Just use `new` — constructor promotion makes this natural:
//
// As a prediction / training example (all fields populated):
//
//   $qa = new BasicQA(question: 'What is PHP?', answer: 'A programming language');
//
// As an input (only InputField values — OutputField defaults kick in):
//
//   $input = new BasicQA(question: 'What is PHP?');
//
// For training data:
//
//   $trainset = [
//       new BasicQA(question: 'What castle did David Gregory inherit?', answer: 'Kinnairdy Castle'),
//       new BasicQA(question: 'What is the capital of France?', answer: 'Paris'),
//   ];
//
// No static factories, no base class methods. Just `new`.

// ============================================================================
// 2. LANGUAGE MODEL — Injectable service wrapping Symfony AI Platform
// ============================================================================
//
// No global configuration. Inject the LM where you need it.
// Symfony's autowiring handles the wiring in framework apps.

final class LM
{
    /** @var list<array{messages: list<array{role: string, content: string}>, response: string}> */
    private array $history = [];

    /**
     * @param array<string, mixed> $options Default options (temperature, max_tokens, etc.)
     */
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
        private readonly array $options = [],
    ) {}

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options Per-call overrides
     */
    public function chat(array $messages, array $options = []): string
    {
        // Converts messages to Symfony AI MessageBag, calls platform->invoke(),
        // records history, returns response text.
    }

    /**
     * @return list<array{messages: list<array{role: string, content: string}>, response: string}>
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}

// Usage:
//
//   use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
//
//   $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
//   $lm = new LM($platform, 'gpt-4o-mini');
//
//   // Or with Anthropic:
//   $platform = \Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory::create($_ENV['ANTHROPIC_API_KEY']);
//   $lm = new LM($platform, 'claude-sonnet-4-20250514');

// ============================================================================
// 3. ADAPTERS — Convert signatures into LLM messages and parse responses
// ============================================================================

interface Adapter
{
    /**
     * Build the message list for the LLM from a signature, demos, and inputs.
     *
     * @param class-string $signatureClass
     * @param list<array<string, mixed>> $demos Few-shot demo field values
     * @param array<string, mixed> $inputs Current input field values
     * @return list<array{role: 'system'|'user'|'assistant', content: string}>
     */
    public function formatMessages(string $signatureClass, array $demos, array $inputs): array;

    /**
     * Return adapter-specific LM options (e.g. response_format for JSON mode).
     *
     * @param class-string $signatureClass
     * @return array<string, mixed>
     */
    public function getOptions(string $signatureClass): array;

    /**
     * Parse the LLM response text into output field values.
     *
     * @param class-string $signatureClass
     * @return array<string, mixed>
     */
    public function parseResponse(string $signatureClass, string $response): array;
}

/**
 * Default adapter. Uses field markers [[ ## field_name ## ]] in prompts.
 */
final class ChatAdapter implements Adapter
{
    public function formatMessages(string $signatureClass, array $demos, array $inputs): array
    {
        // 1. Reflect on $signatureClass to discover fields, types, descriptions, docblock
        // 2. Build system message from docblock + field descriptions
        // 3. Format each demo as user/assistant turn pair
        // 4. Format current inputs as final user turn
    }

    public function getOptions(string $signatureClass): array
    {
        return []; // ChatAdapter doesn't need special LM options
    }

    public function parseResponse(string $signatureClass, string $response): array
    {
        // Parse [[ ## field_name ## ]] markers from response
        // Cast values to declared property types
    }
}

/**
 * Uses the model's native JSON mode for structured output.
 */
final class JsonAdapter implements Adapter
{
    public function formatMessages(string $signatureClass, array $demos, array $inputs): array {}

    public function getOptions(string $signatureClass): array
    {
        // Returns ['response_format' => [...json schema from signature OutputFields...]]
    }

    public function parseResponse(string $signatureClass, string $response): array {}
}

// ============================================================================
// 4. PREDICT — The fundamental LLM-calling component
// ============================================================================
//
// Predict is final. Not a base class. Compose it inside your own classes.
//
// Generic over T: the signature class. Returns T directly — no wrapper.
//
// __invoke takes T (a signature instance with inputs populated).
// PHPStan natively validates the constructor call at every call site —
// no custom extension needed.

/**
 * @template T of object
 */
final class Predict
{
    /** @var list<array<string, mixed>> Few-shot demos (set by optimizers) */
    public array $demos = [];

    /**
     * @param class-string<T> $signature
     */
    public function __construct(
        private readonly string $signature,
        private readonly LM $lm,
        private readonly Adapter $adapter = new ChatAdapter(),
        private readonly int $maxRetries = 3,
    ) {}

    /**
     * Call the LM with the signature's input fields and return a fully populated instance.
     *
     * Pass a signature instance with InputField values set. OutputField defaults are ignored —
     * the LLM produces those. Returns a new instance with all fields populated.
     *
     * @param T $input
     * @return T
     */
    public function __invoke(object $input): object
    {
        // 1. Extract InputField values from $input via reflection
        // 2. $messages = $this->adapter->formatMessages($this->signature, $this->demos, $inputValues)
        // 3. $options = $this->adapter->getOptions($this->signature)
        // 4. $response = $this->lm->chat($messages, $options)
        // 5. $outputValues = $this->adapter->parseResponse($this->signature, $response)
        //    — on parse failure, retry up to $this->maxRetries times
        // 6. return new $this->signature(...$inputValues, ...$outputValues)
    }

    /** @return class-string<T> */
    public function getSignature(): string
    {
        return $this->signature;
    }
}

// Usage:
//
//   $predict = new Predict(BasicQA::class, lm: $lm);
//   $qa = ($predict)(new BasicQA(question: 'What is the capital of France?'));
//   echo $qa->answer; // "Paris"
//
// PHPStan validates natively:
//   - new BasicQA(question: '...') checks constructor args ✓
//   - ($predict)(...) returns BasicQA (inferred from class-string<T>) ✓
//   - $qa->answer is string ✓

// ============================================================================
// 5. CHAIN OF THOUGHT — Step-by-step reasoning via Predict
// ============================================================================
//
// ChainOfThought wraps Predict. It modifies the prompt to encourage
// step-by-step reasoning before producing output fields.
//
// Returns Reasoning<T> — a lightweight wrapper that pairs the LLM's
// step-by-step reasoning with the typed output T.

/**
 * Pairs the LLM's chain-of-thought reasoning with the typed output.
 *
 * @template T of object
 * @mixin T
 */
final class Reasoning
{
    /**
     * @param T $output
     */
    public function __construct(
        public readonly string $reasoning,
        public readonly object $output,
    ) {}

    public function __get(string $name): mixed
    {
        return $this->output->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->output->$name);
    }
}

/**
 * @template T of object
 */
final class ChainOfThought
{
    /** @var Predict<T> */
    public readonly Predict $predict;

    /**
     * @param class-string<T> $signature
     */
    public function __construct(
        private readonly string $signature,
        private readonly LM $lm,
        private readonly Adapter $adapter = new ChatAdapter(),
        private readonly int $maxRetries = 3,
    ) {
        $this->predict = new Predict($signature, $lm, $adapter, $maxRetries);
    }

    /**
     * @param T $input
     * @return Reasoning<T>
     */
    public function __invoke(object $input): Reasoning
    {
        // 1. Extends the prompt to include "think step by step" instruction
        // 2. Calls the LM expecting a reasoning section followed by output fields
        // 3. Parses reasoning + output fields from response
        // 4. Stores full trace (with reasoning) in demos for optimization
        // 5. Returns Reasoning<T> with both the reasoning and the populated signature
    }
}

// Usage:
//
//   $cot = new ChainOfThought(BasicQA::class, lm: $lm);
//   $result = ($cot)(new BasicQA(question: 'What is 2 + 2?'));
//   echo $result->reasoning; // "Let me add 2 and 2..."
//   echo $result->answer;    // "4" — forwarded via @mixin + __get()
//
// PHPStan knows all of these natively:
//   - new BasicQA(question: '...') validates constructor args ✓
//   - $result is Reasoning<BasicQA> ✓
//   - $result->reasoning is string (real property on Reasoning) ✓
//   - $result->answer is string (via @mixin T → BasicQA) ✓
//   - $result->output is BasicQA ✓

// ============================================================================
// 6. CUSTOM MODULES — Just PHP classes that compose Predict/ChainOfThought
// ============================================================================
//
// No Module base class. Your module is a plain PHP class.
// Compose Predict/ChainOfThought via constructor injection.
// The optimizer discovers Predict instances via reflection.
//
// Convention: __invoke takes a signature instance (the "outer" type that
// matches your training data) and returns the result. This keeps the
// evaluator simple — it passes trainset examples directly.

class RAG
{
    /** @var ChainOfThought<GenerateAnswer> */
    private ChainOfThought $generate;

    public function __construct(
        private readonly LM $lm,
        private readonly int $numDocs = 5,
    ) {
        $this->generate = new ChainOfThought(GenerateAnswer::class, lm: $lm);
    }

    /** @return Reasoning<GenerateAnswer> */
    public function __invoke(BasicQA $input): Reasoning
    {
        $context = $this->retrieve($input->question);

        return ($this->generate)(new GenerateAnswer(
            context: $context,
            question: $input->question,
        ));
    }

    /** @return list<string> */
    private function retrieve(string $question): array
    {
        // Your retrieval logic here.
        return [];
    }
}

class MultiHopQA
{
    /** @var ChainOfThought<BasicQA> */
    private ChainOfThought $firstHop;

    /** @var ChainOfThought<BasicQA> */
    private ChainOfThought $secondHop;

    public function __construct(LM $lm)
    {
        $this->firstHop = new ChainOfThought(BasicQA::class, lm: $lm);
        $this->secondHop = new ChainOfThought(BasicQA::class, lm: $lm);
    }

    /** @return Reasoning<BasicQA> */
    public function __invoke(BasicQA $input): Reasoning
    {
        $hop1 = ($this->firstHop)(new BasicQA(question: $input->question));
        $refinedQuestion = $input->question . ' ' . $hop1->answer;

        return ($this->secondHop)(new BasicQA(question: $refinedQuestion));
    }
}

// ============================================================================
// 7. METRICS — Plain callables
// ============================================================================
//
// A metric is just a callable. No interface, no base class.
// Write it as a closure, a function, or an invokable class.
//
// Custom closures get full PHPStan typing. Built-in helpers use
// dynamic property access for field-name flexibility — that's an
// acceptable tradeoff for generic helpers.

// Built-in metric helpers:
final class Metrics
{
    /**
     * Exact string match (case-insensitive) on a field.
     *
     * Uses reflection for PHPStan-safe property access.
     *
     * @return \Closure(object, object): bool
     */
    public static function exactMatch(string $field = 'answer'): \Closure
    {
        return static function (object $example, object $prediction) use ($field): bool {
            $expected = (new \ReflectionProperty($example, $field))->getValue($example);
            $actual = (new \ReflectionProperty($prediction, $field))->getValue($prediction);

            return strtolower((string) $expected) === strtolower((string) $actual);
        };
    }

    /**
     * F1 token overlap on a field.
     *
     * @return \Closure(object, object): float
     */
    public static function f1(string $field = 'answer'): \Closure {}
}

// Custom metrics are fully typed closures — PHPStan validates everything:
//
//   $metric = static function (BasicQA $example, Reasoning $prediction): bool {
//       return str_contains(
//           strtolower($prediction->answer),   // @mixin resolves to string
//           strtolower($example->answer),
//       );
//   };

// ============================================================================
// 8. EVALUATION — Score a callable against a dataset
// ============================================================================

final class Evaluate
{
    /**
     * @param list<object> $dataset Signature instances with ground-truth values
     * @param \Closure(object, object): (float|bool) $metric
     */
    public function __construct(
        private readonly array $dataset,
        private readonly \Closure $metric,
    ) {}

    /**
     * Run the module on each example and return the average metric score.
     *
     * Passes each dataset example directly to the module.
     * The module receives the full signature (including OutputField values),
     * but should only use InputField values — OutputField values are ground truth.
     */
    public function __invoke(callable $module): float
    {
        // 1. For each example in dataset:
        //    a. Call $module($example) — pass the signature instance directly
        //    b. Score with $this->metric($example, $prediction)
        // 2. Return average score
    }
}

// Usage:
//
//   $evaluator = new Evaluate(
//       dataset: $devset,
//       metric: Metrics::exactMatch('answer'),
//   );
//   $score = $evaluator($rag);
//   echo "Accuracy: {$score}\n";

// ============================================================================
// 9. OPTIMIZATION — Automatic prompt tuning
// ============================================================================
//
// Optimizers find all Predict instances in a module via reflection,
// run the module on training data, and populate Predict::$demos
// with successful examples.
//
// Cloning: the optimizer deep-copies the student module. It traverses
// the object graph via reflection, cloning all Predict and ChainOfThought
// instances so the original module is never mutated.

interface Optimizer
{
    /**
     * @template T of object
     * @param T $student Any callable object with Predict properties
     * @param list<object> $trainset Signature instances with ground-truth values
     * @return T Optimized clone with populated demos
     */
    public function compile(object $student, array $trainset): object;
}

/**
 * Bootstrap few-shot demonstrations from a teacher model.
 *
 * 1. Deep-clones the student (all Predict instances are fresh copies)
 * 2. Runs the teacher (or student copy) on training examples
 * 3. Filters traces by metric score
 * 4. Sets passing traces as demos on each Predict instance
 */
final class BootstrapFewShot implements Optimizer
{
    /**
     * @param \Closure(object, object): (float|bool) $metric
     */
    public function __construct(
        private readonly \Closure $metric,
        private readonly int $maxBootstrappedDemos = 4,
        private readonly int $maxLabeledDemos = 16,
        private readonly int $maxRounds = 1,
        private readonly ?object $teacher = null,
    ) {}

    public function compile(object $student, array $trainset): object
    {
        // 1. Deep-clone student (traverse properties, clone Predict/ChainOfThought instances)
        // 2. Find all Predict instances via reflection (including nested in ChainOfThought)
        // 3. Run teacher on each training example, collect traces
        // 4. Filter traces by metric
        // 5. Assign passing traces as demos on each Predict
        // 6. Return optimized clone
    }
}

final class BootstrapFewShotWithRandomSearch implements Optimizer
{
    /**
     * @param \Closure(object, object): (float|bool) $metric
     */
    public function __construct(
        private readonly \Closure $metric,
        private readonly int $maxBootstrappedDemos = 4,
        private readonly int $maxLabeledDemos = 16,
        private readonly int $numCandidatePrograms = 10,
    ) {}

    public function compile(object $student, array $trainset): object {}
}

final class MIPROv2 implements Optimizer
{
    /**
     * @param \Closure(object, object): (float|bool) $metric
     * @param 'light'|'medium'|'heavy' $auto Search budget preset
     */
    public function __construct(
        private readonly \Closure $metric,
        private readonly string $auto = 'medium',
    ) {}

    public function compile(object $student, array $trainset): object {}
}

// ============================================================================
// 10. ERROR HANDLING — Retries and parse failures
// ============================================================================
//
// When the LLM returns output that can't be parsed into the signature's
// output fields, Predict retries up to `maxRetries` times. Each retry
// appends the failed response and a hint to the message history, giving
// the LLM a chance to self-correct.
//
// The retry loop:
//   1. Call LM
//   2. Try to parse response via adapter
//   3. On parse failure: append the response + error hint as messages, retry
//   4. On type coercion failure: same retry loop
//   5. After maxRetries exhausted: throw PredictException with the last
//      raw response attached for debugging
//
// Adapter fallback is NOT automatic — the user chooses their adapter.
// If ChatAdapter fails consistently, switch to JsonAdapter explicitly.
//
//   try {
//       $qa = ($predict)(new BasicQA(question: 'What?'));
//   } catch (PredictException $e) {
//       $e->rawResponse;   // string — the LLM's unparseable output
//       $e->attempts;      // int — number of attempts made
//       $e->signatureClass; // class-string — which signature failed
//   }

final class PredictException extends \RuntimeException
{
    public function __construct(
        public readonly string $rawResponse,
        public readonly int $attempts,
        /** @var class-string */
        public readonly string $signatureClass,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

// ============================================================================
// 11. STATE PERSISTENCE — Save and load optimized Predict demos
// ============================================================================
//
// Injectable service — not static methods.
// Extracts/restores Predict::$demos from any object graph.

final class PredictState
{
    /**
     * Extract the learnable state (demos) from all Predict instances in an object.
     *
     * @return array<string, mixed>
     */
    public function dump(object $module): array
    {
        // Reflects over $module, finds all Predict instances, serializes their demos.
        // Keys are property paths (e.g. "generate.predict") for stable identification.
    }

    /**
     * Restore demos onto Predict instances in a module.
     *
     * @param array<string, mixed> $state
     */
    public function load(object $module, array $state): void
    {
        // Reflects over $module, finds all Predict instances, restores their demos
    }
}

// Usage:
//
//   $predictState = new PredictState();
//
//   // Save
//   $state = $predictState->dump($optimizedRag);
//   file_put_contents('state.json', json_encode($state));
//
//   // Load
//   $rag = new RAG($lm);
//   $predictState->load($rag, json_decode(file_get_contents('state.json'), true));

// ============================================================================
// 12. PUTTING IT ALL TOGETHER — End-to-end example
// ============================================================================

// use Symfony\AI\Platform\Bridge\OpenAi\PlatformFactory;
//
// // --- 1. Create LM ---
// $platform = PlatformFactory::create($_ENV['OPENAI_API_KEY']);
// $lm = new LM($platform, 'gpt-4o-mini');
//
// // --- 2. Define signature ---
// /** Answer questions using retrieved context. */
// class GenerateAnswer
// {
//     public function __construct(
//         /** @var list<string> */
//         #[InputField(desc: 'retrieved passages')]
//         public readonly array $context,
//         #[InputField]
//         public readonly string $question,
//         #[OutputField(desc: 'concise factual answer')]
//         public readonly string $answer = '',
//     ) {}
// }
//
// // --- 3. Build module (plain PHP class) ---
// class RAG
// {
//     private ChainOfThought $generate;
//
//     public function __construct(private readonly LM $lm)
//     {
//         $this->generate = new ChainOfThought(GenerateAnswer::class, lm: $lm);
//     }
//
//     /** @return Reasoning<GenerateAnswer> */
//     public function __invoke(BasicQA $input): Reasoning
//     {
//         $context = $this->retrieve($input->question);
//         return ($this->generate)(new GenerateAnswer(
//             context: $context,
//             question: $input->question,
//         ));
//     }
//
//     private function retrieve(string $question): array { return []; }
// }
//
// // --- 4. Prepare training data (just `new` your signatures) ---
// $trainset = [
//     new BasicQA(question: 'What castle did David Gregory inherit?', answer: 'Kinnairdy Castle'),
//     new BasicQA(question: 'What is the capital of France?', answer: 'Paris'),
//     new BasicQA(question: 'Who wrote Hamlet?', answer: 'William Shakespeare'),
// ];
//
// // --- 5. Evaluate baseline ---
// $metric = Metrics::exactMatch('answer');
// $evaluator = new Evaluate(dataset: $trainset, metric: $metric);
// $baseline = $evaluator(new RAG($lm));
// echo "Baseline: {$baseline}\n";
//
// // --- 6. Optimize ---
// $optimizer = new BootstrapFewShot(metric: $metric, maxBootstrappedDemos: 4);
// $compiled = $optimizer->compile(student: new RAG($lm), trainset: $trainset);
//
// // --- 7. Evaluate optimized ---
// $optimized = $evaluator($compiled);
// echo "Optimized: {$optimized}\n";
//
// // --- 8. Save & load ---
// $predictState = new PredictState();
// $state = $predictState->dump($compiled);
// file_put_contents('state.json', json_encode($state));
//
// $fresh = new RAG($lm);
// $predictState->load($fresh, json_decode(file_get_contents('state.json'), true));
//
// // --- 9. Use ---
// $result = $compiled(new BasicQA(question: 'What castle did David Gregory inherit?'));
// echo $result->reasoning;  // step-by-step thinking
// echo $result->answer;     // "Kinnairdy Castle" (via @mixin)

// ============================================================================
// 13. SYMFONY INTEGRATION — autowiring and services.yaml
// ============================================================================
//
// In a Symfony app, wire everything via DI. No global state needed.
//
//   # config/services.yaml
//   services:
//       DSPy\LM:
//           arguments:
//               $platform: '@ai.platform.openai'
//               $model: 'gpt-4o-mini'
//
//       DSPy\PredictState: ~
//
//       App\AI\RAG:
//           arguments:
//               $lm: '@DSPy\LM'
//
// Then inject your module wherever you need it:
//
//   class MyController
//   {
//       public function __construct(private readonly RAG $rag) {}
//
//       public function ask(string $question): Response
//       {
//           $result = ($this->rag)(new BasicQA(question: $question));
//           return new JsonResponse(['answer' => $result->answer]);
//       }
//   }
//
// Use different LMs for different modules:
//
//   services:
//       app.lm.fast:
//           class: DSPy\LM
//           arguments: ['@ai.platform.openai', 'gpt-4o-mini']
//
//       app.lm.smart:
//           class: DSPy\LM
//           arguments: ['@ai.platform.anthropic', 'claude-sonnet-4-20250514']
//
//       App\AI\Classifier:
//           arguments:
//               $lm: '@app.lm.fast'
//
//       App\AI\Reasoner:
//           arguments:
//               $lm: '@app.lm.smart'

// ============================================================================
// 14. DESIGN DECISIONS
// ============================================================================

/**
 * NO BASE CLASSES FOR USER CODE
 * ─────────────────────────────
 * Signatures are plain PHP classes — no `extends Signature`.
 * Modules are plain PHP classes — no `extends Module`.
 * The framework discovers structure via reflection on attributes
 * and property types. This follows Symfony's pattern where entities,
 * DTOs, and controllers are plain PHP classes with attributes.
 *
 *
 * NO GLOBAL STATE / STATIC SINGLETONS
 * ────────────────────────────────────
 * No `DSPY::configure()`. The LM is a constructor parameter.
 * Symfony's autowiring handles the wiring. For scripts, just
 * pass `$lm` to `new Predict(...)`. This is testable, explicit,
 * and follows PHP/Symfony conventions.
 *
 *
 * SIGNATURES = PREDICTIONS = TRAINING DATA
 * ─────────────────────────────────────────
 * One class serves three purposes:
 *   1. I/O contract (via #[InputField] / #[OutputField] attributes)
 *   2. LLM result (Predict returns an instance with outputs populated)
 *   3. Training example (new BasicQA(question: '...', answer: '...'))
 *
 * Convention: InputField params are required, OutputField params have
 * defaults. This allows constructing input-only instances for type-safe calls.
 *
 *
 * __invoke TAKES T FOR NATIVE PHPSTAN VALIDATION
 * ───────────────────────────────────────────────
 * Predict<T>::__invoke(T $input) accepts a signature instance, not
 * variadic named args. This gives full PHPStan validation at call sites
 * without any custom extension:
 *
 *   ($predict)(new BasicQA(question: '...'))
 *     └── PHPStan validates BasicQA's constructor args
 *     └── PHPStan knows the return type is BasicQA
 *     └── PHPStan knows $result->answer is string
 *
 * The tradeoff vs named args:
 *   ($predict)(question: '...')            // concise, but PHPStan can't validate
 *   ($predict)(new BasicQA(question: '...'))  // explicit, fully validated
 *
 *
 * CHAIN OF THOUGHT RETURNS Reasoning<T> WITH @mixin
 * ──────────────────────────────────────────────────
 * ChainOfThought returns Reasoning<T> — a wrapper that pairs
 * the LLM's step-by-step reasoning (string) with the typed output (T).
 * Thanks to @mixin T + __get(), all output fields are directly accessible:
 *
 *   $result->reasoning  // string (real property on Reasoning)
 *   $result->answer     // string (forwarded to T via @mixin)
 *   $result->output     // T (explicit access if needed)
 *
 * PHPStan understands @mixin with template parameters, so all
 * forwarded properties are fully statically typed.
 *
 *
 * ADAPTER CONTROLS LM OPTIONS
 * ───────────────────────────
 * The Adapter interface includes getOptions() so adapters can influence
 * the LM call. JsonAdapter uses this to set response_format for native
 * structured output. ChatAdapter returns empty options. This keeps the
 * adapter in full control of the LM interaction strategy.
 *
 *
 * DEMOS ARE ARRAYS, NOT TYPED OBJECTS
 * ───────────────────────────────────
 * Predict::$demos is list<array<string, mixed>>, not list<T>.
 * This allows ChainOfThought to store reasoning in demos (for
 * the optimizer) even when the user's signature doesn't have a
 * reasoning field. The adapter formats demos into prompt messages
 * regardless of their shape.
 *
 *
 * REFLECTION-BASED PREDICTOR DISCOVERY
 * ────────────────────────────────────
 * The optimizer finds Predict instances by reflecting over the
 * module's properties. No interface to implement, no registration
 * method to call. This mirrors how Symfony discovers services,
 * event listeners, and route controllers — via reflection.
 *
 * Deep-cloning traverses the same property graph: Predict and
 * ChainOfThought instances are cloned, everything else is shared.
 *
 *
 * RETRY ON PARSE FAILURE
 * ──────────────────────
 * Predict retries up to maxRetries times when the adapter can't parse
 * the LLM response. Each retry appends the failed output and error
 * context to the message history, giving the LLM a chance to self-correct.
 * After exhausting retries, a PredictException is thrown with the raw
 * response for debugging.
 *
 *
 * FINAL FRAMEWORK CLASSES
 * ───────────────────────
 * Predict, ChainOfThought, LM, ChatAdapter are all final.
 * Extend behavior via the Adapter interface or by composing
 * Predict inside your own classes. This prevents fragile
 * inheritance hierarchies.
 *
 *
 * PHP 8.4+ FEATURES USED
 * ──────────────────────
 * - Constructor promotion with readonly (signatures, framework classes)
 * - Attributes (#[InputField], #[OutputField])
 * - Backed enums for constrained outputs (Sentiment, etc.)
 * - Named arguments for construction
 * - First-class callables (\Closure for metrics)
 * - `new` in initializer expressions (default Adapter in constructors)
 */
