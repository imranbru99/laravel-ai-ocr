# Threat model and deployment

## Trust boundaries

Untrusted inputs include bytes, uploaded filenames, URL hosts/responses, Office
ZIP/XML entries, MIME email attachments, PDF glyph mappings and model outputs.
Trusted inputs include application configuration, binary installations, queue
storage, APP_KEY, AI Hub provider configuration and authorized schema definitions.

## Input and process controls

The resolver detects MIME using fileinfo plus PDF/ZIP signatures, enforces an
input-size cap, rejects SVG and unknown formats, and checks common raster pixel
dimensions. Office archives are inspected before XML reads: expanded size, entry
count and unsafe paths are rejected. XML parsing rejects DTD/entity declarations
and uses LIBXML_NONET. External Office relationships are not fetched.

PDF page count is checked before rasterizing. Poppler raster output is scaled to
the configured pixel ceiling. Imagick receives memory/map limits in rasterization;
its OS policy should disable unused coders and restrict disk/memory resources.
PDF scripts/embedded files are not executed or exported. Up-to-date system tools
and a restricted worker account remain required: this is not an OS sandbox.

Symfony Process always receives an argument array and validated absolute binary.
Per-process timeout and output-size limits apply. On Linux, available `prlimit`
enforces the configured subprocess address-space ceiling. Other platforms should
use containers/cgroups or equivalent worker CPU/RAM/disk isolation. Temp directories are random, mode 0700, never use upload filenames,
and are removed by finally/destructors plus a shutdown safety net. SIGKILL and
host crashes require an OS temporary-directory cleanup policy.

URL downloads allow HTTP(S) on default ports, reject credentials and private/
reserved addresses, inspect all DNS answers and pin the validated address with
CURLOPT_RESOLVE. Proxies and redirects are disabled. Size/time limits apply.
Webhook delivery independently validates and pins its configured public target.
The Hub's configured provider base URLs are trusted operator configuration.

## AI and privacy

OCR never stores keys. Hub v1.6 settings are not encrypted by its model/file
backend. Review the Hub installation separately. Local-only policies restrict
providers to Ollama with a loopback/private literal host; public Ollama URLs are
not treated as private local inference. Hub automatic failover is disabled for
OCR calls; the OCR adapter iterates only policy-allowed providers.

Document prompts are untrusted data, no tools are provided, and returned JSON is
strictly validated locally. Unknown keys, invalid values and ungrounded evidence
are rejected. Models can still produce semantically incorrect answers.

Text redaction uses reversible random tokens. Card masks require Luhn; NID masks
are conservative digit patterns. Regex redaction cannot remove image PII, so
vision is blocked when a redaction policy is active. Text/transcription may still
contain sensitive facts not covered by those patterns; choose local-only/manual
processing for stronger privacy boundaries.

The PrivatePendingRequest adapter clears Hub content previews, errors that may
echo content, tool calls and unapproved metadata while retaining spend metrics.
Both OCR and Hub cache entries are encrypted. Database result/page text encryption
is enabled by default. Keep APP_KEY stable across web/queue workers and protect
backup keys. `store_text=false` removes page text, boxes, tables and source quotes
from persisted results and disables OCR result caching. Structured values can still
contain PII. Original sources are retained only when `store.retain_original` is enabled;
retained bytes are encrypted on the configured private disk and enable reruns.

## Web and queue controls

Studio is off outside local unless enabled. Authorization callback, Gate, role or
email allowlist is required outside local; web middleware supplies CSRF. API
middleware defaults to Sanctum and throttling and must be configured in the host
app. Stored-result reads are owner-scoped. Schemas also require an authenticated
owner. Do not remove authentication to expose a public extraction service without
adding tenant quotas and application-specific access control.

Queued jobs are encrypted and require durable shared private files. Webhooks
contain only extraction ID/time, HMAC SHA-256 over `timestamp.body`, an event ID
for idempotence and retries. WebhookVerifier checks age/signature and caches IDs
to reject replay. Receivers should acknowledge already-processed events without
repeating side effects; retries use the same extraction ID.

## Residual risks

OCR confidence is heuristic. Model prices/token counts can change, and budget
checks are not distributed atomic reservations. Local files are trusted paths
supplied by application code; never pass a raw request path parameter. Native
parsers and optional libraries need regular security updates. Prefer dedicated
worker containers with no unnecessary network/filesystem access.
