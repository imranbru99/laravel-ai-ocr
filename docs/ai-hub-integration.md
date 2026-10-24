# AI Hub integration: verified contract

Verified on 2026-10-24 by Imran Ahmed ([imrandev.bd](https://imrandev.bd)).

## Scope and reproducibility

Installed `imrandevbd/laravel-ai-hub` **v1.6.0**, then installed Orchestra
Testbench **v11.3.0** in `/tmp/ai-ocr-recon`. The resolved framework is Laravel
**v13.38.0**; the available CLI is PHP **8.5.4**. This verifies one environment,
not the requested PHP/Laravel compatibility matrix. Composer reported no known
security advisories at installation time.

Read the actual installed `src` and `config/ai-hub.php`. The standalone
`tests/Recon/composer.lock` records the dependency graph; `AiHubContractTest.php`
contains offline executable checks. No real provider requests were made.

From the repository root:

```bash
composer install --working-dir=tests/Recon
tests/Recon/vendor/bin/phpunit --bootstrap tests/Recon/vendor/autoload.php tests/Recon/AiHubContractTest.php
```

The recon dependencies intentionally target this verification environment and
are not the future package's PHP/Laravel support constraints. In the original
sandbox the equivalent command used `/tmp/ai-ocr-recon/vendor` and passed with
**5 tests, 14 assertions**.

## Provider readiness and settings

Sources: `src/Support/SettingsStore.php`, `src/AiHubServiceProvider.php`,
`src/Providers/AbstractProvider.php`, `src/Providers/OpenAICompatibleProvider.php`,
`src/Support/ProviderCatalog.php`, `src/AIHubManager.php`.

There is no dedicated public readiness probe. The service provider registers
`SettingsStore` and calls `applyToConfig()` during boot, catching failures when
storage/database is unavailable. The settings store merges saved provider keys,
enabled flags, default models, default provider, priority and budgets into
`ai-hub.*`. Saved nonempty keys override environment-derived keys. Empty saved
keys do not clear environment keys.

Settings use `ai_hub_settings.payload` or a JSON file, depending on the configured
backend. **The v1.6.0 model casts `payload` to `array`, not `encrypted:array`;
the file backend writes JSON directly.** The prompt's assertion of encrypted
database keys does not match this release. OCR must never copy or persist keys,
and must not promise encryption that the hub does not provide.

`AbstractProvider::apiKey()` rejects an empty string with `AiHubException`.
Ollama's compatible provider permits its default `ollama` token. A configured
key establishes configuration readiness, not key validity, model capability,
server reachability, or entitlement.

Planned boundary: `Contracts\ProviderReadiness` with a
`Support\ProviderReadiness` implementation that reads effective Laravel config
after Hub boot, validates catalog membership, requires enabled providers and a
nonblank cloud key, and treats Ollama as keyless local service configuration.
It returns provider names/reasons only. Runtime credential validation must not
make a paid request. Storage failures must produce a safe diagnostic, never
leak settings payloads.

The manager caches resolved provider objects. After changing hub credentials
within a running process, call the hub's `forget($provider)`; a previously
resolved driver's configuration may otherwise remain stale.

## Images and PDF input

Sources: `PendingRequest::image`, `attachImages`, `imagePart`;
`Providers/GeminiProvider::imagePart`;
`Providers/ClaudeProvider::imageBlock`;
`Providers/OpenAIProvider::chatBody`.

`image(string $urlOrBase64)` accepts a string. Image data URIs survive the
OpenAI message body unchanged. Gemini transforms `data:image/...;base64,...`
into `inline_data`; Claude transforms it into a base64 image source. The
recon suite verifies the OpenAI outgoing HTTP body using Laravel's HTTP fake
and directly checks the Gemini/Claude conversion methods. These are payload
contract checks; they do not establish acceptance by a live model.

Bare base64 matching the hub regex (at least 80 characters) is automatically
labeled JPEG. Do not depend on that heuristic: OCR should always construct an
explicit MIME-correct data URI from a validated raster image. Local paths are
not read by `image()` and would be transmitted as strings. No native PDF upload
method was found in this request interface. Gemini/Claude's conversion regex
accepts image MIME types, not application/pdf. Rasterize selected PDF pages
locally and send images, never a local path or PDF disguised as an image.

Other providers use OpenAI-compatible payloads (Azure has its own subclass);
provider/model image capability and live data-URI acceptance remain unverified.
No thin transport adapter is needed for the verified image payload paths.

## Request inspection and fake behavior

Sources: `PendingRequest` inspection getters; `AIHubManager::fake`;
`Facades/AIHub`; `Testing/AIHubFake`.

Public getters include `getProvider()`, `getModel()`, `getPrompt()`,
`getMessages()`, `getImages()`, `getMeta()`, `getResponseSchema()`,
`isRecoverJson()`, `isForceJsonObject()` and `hasJobTrace()`. Despite its name,
`hasJobTrace()` returns the nullable job tag string.

**Fake caveat reproduced:** `AIHub::fake()` replaces container bindings but does
not clear Laravel's resolved facade root. A subsequent facade assertion can
therefore target the original manager and fail with an undefined-method error.
Use this compatibility setup in OCR's integration tests:

```php
$fake = AIHub::fake('{"text":"hello"}');
AIHub::clearResolvedInstance(\ImranDevBd\AiHub\AIHubManager::class);
// Requests and AIHub::assertSent(...) now resolve the fake.
```

Alternatively retain `$fake` for assertions. The test suite clears the facade
root and asserts request count, schema, provider, model, prompt and job tag.
HTTP payload tests use `Http::fake()` plus `preventStrayRequests()`; production
AI behavior must never rely on a fake being installed.

## Failover, retries and exceptions

Sources: `PendingRequest::send`, `resolveChain`, `resolveProvider`;
`Support/RetryHandler.php`; `Support/BudgetGuard.php`;
`Exceptions/AiHubException.php`.

Only one hub exception class exists: `ImranDevBd\AiHub\Exceptions\AiHubException`.
There are no distinct hub budget, no-provider or rate-limit exception classes.
The OCR package must define its own domain exceptions without importing
nonexistent hub classes.

The chain normally uses configured priority, filtering disabled providers.
An explicit provider is placed first, but **does not disable failover**. Use
`->failover(false)` for every privacy-constrained provider call; select and
iterate allowed providers in the OCR adapter. Otherwise local-only documents
can fall through to cloud providers. API-key overrides also disable failover.
Fake mode runs only the selected provider.

`send()` catches request failures and tries the next provider, then wraps the
last failure in `AiHubException`, preserving its previous exception. Provider
resolution and payload construction happen before that per-attempt try block;
errors there may escape the normal failover loop. The integration boundary
must account for that without masking OCR programming errors.

Retry defaults are three attempts, exponential delay with jitter, HTTP status
429/500/502/503/504, plus message matching for timeout/rate-limit/temporary
failures. Transport errors can be `Illuminate\Http\Client\RequestException`;
inspect the previous-exception chain and HTTP response status for classification.
Do not infer a new typed hub exception from a message alone.

`BudgetGuard::evaluate($provider, $job)` returns structured `level`, `key`,
`message` entries; `assert()` throws the shared `AiHubException` for a breach
in block mode. The actual config is **`ai-hub.budget`** (singular).
Hub `send()` specially rethrows an exception whose message contains `cap of`.
OCR should preflight structured budget evaluations, map breaches to its own
`BudgetExceededException`, and gracefully report later hub failures. Budgets
are retrospective monthly-spend checks, not atomic per-request reservations.

## Structured output and semantic validation

`asJsonSchema($schema, $name, $description)->generate()` is real;
`generate()` aliases `send()`. `recoverJson()` applies local `JsonRecovery`
decoding and re-encoding; it does not make a repair-model request or validate
the complete output against JSON Schema.

OpenAI payloads request strict JSON Schema output; Gemini uses
`generationConfig.responseSchema`. Claude inserts a schema instruction into
the system prompt rather than enforcing server-side strict structured output.
Provider behavior is therefore not uniformly strict. OCR must independently
validate types, required fields, nested extra keys, source quotes and cross-checks.
Prompt wording alone cannot guarantee document instructions are ignored.

`system()`, `withoutThinking()`, `thinking()`, `reasoningEffort()`, `cache()`
and `forJob()` exist. Use a system instruction defining document content as
untrusted data, plus deterministic output checks. A semantic repair/escalation
policy belongs in OCR rather than relying on `recoverJson()` as validation.

## Pricing, caching and logging

Sources: `Support/CostCalculator.php`, `PendingRequest::send`, `cacheKey`.

`CostCalculator::ratesFor()` returns nullable input/output prices per million
tokens, first exact-match then prefix-match. `calculate()` returns **0.0 when
pricing is unknown**. OCR estimates must distinguish unknown pricing from a
free model, and refuse a strict cost-cap preflight if no safe bound exists.
Provider image tokenization is not supplied as a reliable image estimator.
Post-response cost accounting cannot guarantee a hard pre-request dollar cap.

Hub responses expose content, provider, model, prompt/completion/total tokens,
costUsd, latencyMs, attempts, success, raw data and tool calls. Cost is recomputed
from configured pricing by `send()`. Cached responses and hub analytics do not
replace OCR's document/schema/prompt-version cache key or per-page accounting.

Hub logging is independently configured. OCR's no-content-logging setting must
also account for hub request/response logging; never assume a wrapper makes
downstream logging private. Review that boundary before sending PII.

## Implemented adapter and remaining boundaries

The runtime adapter implements readiness, scoped providers, disabled Hub failover,
strict schema validation, grounded fields, semantic retry, strong-model escalation,
cost accounting, encrypted caches and scrubbed Hub content logging. Offline tests
exercise the installed v1.6 API. No billable live provider calls were made.

Hub settings encryption and transactional provider billing reservations are outside
this package. Provider-side vision behavior still requires deployment-specific
live testing. Historical compatibility is distinct from a security endorsement.
