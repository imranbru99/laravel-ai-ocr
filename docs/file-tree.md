# Delivered file tree

Generated from the local workspace; dependencies, protected metadata, caches and
generated distribution artifacts are omitted.

```text
laravel-ai-ocr/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.md
│   │   └── feature_request.md
│   └── workflows/
│       └── tests.yml
├── config/
│   └── ai-ocr.php
├── database/
│   └── migrations/
│       └── 2026_01_01_000000_create_ai_ocr_tables.php
├── docker/
│   ├── Dockerfile
│   ├── compose.yml
│   ├── run-matrix.sh
│   └── smoke-install.sh
├── docs/
│   ├── images/
│   │   ├── README.md
│   │   ├── hero.svg
│   │   ├── studio-doctor.png
│   │   ├── studio-extraction.png
│   │   └── studio-schema-builder.png
│   ├── ai-hub-integration.md
│   ├── configuration.md
│   ├── custom-drivers.md
│   ├── custom-types.md
│   ├── file-tree.md
│   ├── implementation-status.md
│   ├── installation-binaries.md
│   ├── performance.md
│   ├── quality-gate.md
│   ├── recipes.md
│   ├── release.md
│   ├── schemas.md
│   ├── security.md
│   ├── studio-preview.png
│   └── validation.md
├── resources/
│   ├── lang/
│   │   ├── bn/
│   │   │   └── messages.php
│   │   └── en/
│   │       └── messages.php
│   └── views/
│       ├── filament-widget.blade.php
│       └── studio.blade.php
├── routes/
│   └── web.php
├── src/
│   ├── Concerns/
│   │   └── HasOcrDocuments.php
│   ├── Console/
│   │   ├── BenchmarkCommand.php
│   │   ├── DoctorCommand.php
│   │   ├── EstimateCommand.php
│   │   ├── InstallCommand.php
│   │   ├── InstallLangsCommand.php
│   │   ├── OcrCommand.php
│   │   ├── PruneCommand.php
│   │   └── TypesCommand.php
│   ├── Contracts/
│   │   ├── AiExtractor.php
│   │   ├── MediaSource.php
│   │   ├── NativeExtractor.php
│   │   ├── OcrEngine.php
│   │   ├── ProviderReadiness.php
│   │   └── Rasterizer.php
│   ├── DTO/
│   │   ├── ExtractionResult.php
│   │   ├── FieldValue.php
│   │   ├── Page.php
│   │   └── Table.php
│   ├── Drivers/
│   │   ├── Ai/
│   │   │   ├── AiHubDriver.php
│   │   │   └── PrivatePendingRequest.php
│   │   ├── Native/
│   │   │   ├── Extractor.php
│   │   │   ├── MimeMessage.php
│   │   │   ├── MinimalPdf.php
│   │   │   └── PdfMetadata.php
│   │   └── Ocr/
│   │       ├── Preprocessor.php
│   │       ├── Rasterizer.php
│   │       └── Tesseract.php
│   ├── Enums/
│   │   ├── Engine.php
│   │   ├── GateStatus.php
│   │   └── Strategy.php
│   ├── Events/
│   │   ├── AiFallbackTriggered.php
│   │   ├── BudgetBlocked.php
│   │   ├── ExtractionCompleted.php
│   │   ├── ExtractionFailed.php
│   │   ├── ExtractionStarted.php
│   │   ├── PageExtracted.php
│   │   └── QualityGateFailed.php
│   ├── Exceptions/
│   │   ├── AiOcrException.php
│   │   ├── AiUnavailableException.php
│   │   ├── BudgetExceededException.php
│   │   ├── ExtractionFailedException.php
│   │   ├── FileTooLargeException.php
│   │   ├── PrivacyPolicyViolationException.php
│   │   ├── SchemaValidationException.php
│   │   └── UnsupportedFileException.php
│   ├── Facades/
│   │   └── AiOcr.php
│   ├── Filament/
│   │   ├── Resources/
│   │   │   ├── ExtractionResource/
│   │   │   │   └── Pages/
│   │   │   │       └── ListExtractions.php
│   │   │   └── ExtractionResource.php
│   │   ├── AiOcrPlugin.php
│   │   └── ExtractionStats.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ExtractionController.php
│   │   │   ├── SchemaController.php
│   │   │   └── StudioController.php
│   │   ├── Middleware/
│   │   │   └── AuthorizeStudio.php
│   │   └── Requests/
│   │       └── ExtractRequest.php
│   ├── Input/
│   │   ├── Document.php
│   │   ├── Resolver.php
│   │   └── UrlFetcher.php
│   ├── Jobs/
│   │   ├── ExtractDocumentJob.php
│   │   ├── ExtractPageJob.php
│   │   ├── FinalizeExtractionJob.php
│   │   └── SendWebhookJob.php
│   ├── Models/
│   │   ├── Document.php
│   │   └── Extraction.php
│   ├── Normalizers/
│   │   ├── Money.php
│   │   ├── Normalizer.php
│   │   └── Passport.php
│   ├── Pipeline/
│   │   └── Pipeline.php
│   ├── Quality/
│   │   ├── FieldConsensus.php
│   │   ├── GarbledDetector.php
│   │   ├── Gate.php
│   │   ├── QualityScorer.php
│   │   ├── TextStatistics.php
│   │   └── Validity.php
│   ├── Schema/
│   │   ├── ManualMapper.php
│   │   └── Schema.php
│   ├── Support/
│   │   ├── Analytics.php
│   │   ├── Binaries.php
│   │   ├── Doctor.php
│   │   ├── Estimator.php
│   │   ├── JobOptions.php
│   │   ├── JobTracker.php
│   │   ├── Owner.php
│   │   ├── PageBatchDispatcher.php
│   │   ├── Pages.php
│   │   ├── Persistence.php
│   │   ├── ProcessPool.php
│   │   ├── ProviderReadiness.php
│   │   ├── QueuedCallbacks.php
│   │   ├── Redactor.php
│   │   ├── Sources.php
│   │   ├── Temp.php
│   │   └── WebhookVerifier.php
│   ├── Testing/
│   │   └── AiOcrFake.php
│   ├── Types/
│   │   ├── BuiltinType.php
│   │   ├── DocumentType.php
│   │   └── TypeRegistry.php
│   ├── AiOcrManager.php
│   ├── AiOcrServiceProvider.php
│   ├── BatchRequest.php
│   ├── ExtractionRequest.php
│   └── helpers.php
├── stubs/
│   └── filament.stub
├── tests/
│   ├── Feature/
│   │   ├── AiBoundaryTest.php
│   │   ├── AiReliabilityTest.php
│   │   ├── ApiTest.php
│   │   ├── FilamentTest.php
│   │   ├── FormatsAndOperationsTest.php
│   │   ├── HistoryAndQueueTest.php
│   │   ├── ImageFixturesTest.php
│   │   ├── ParallelAndConsensusTest.php
│   │   ├── PipelineTest.php
│   │   ├── RemainingPipelineTest.php
│   │   └── SecurityTest.php
│   ├── Fixtures/
│   │   ├── Fixtures.php
│   │   └── OcrOwner.php
│   ├── Live/
│   │   └── ProviderTest.php
│   ├── Recon/
│   │   ├── AiHubContractTest.php
│   │   ├── composer.json
│   │   └── composer.lock
│   ├── Unit/
│   │   ├── DocumentTypesTest.php
│   │   ├── QualityAndSchemaTest.php
│   │   └── ValidationBranchesTest.php
│   └── TestCase.php
├── tools/
│   ├── build-release.php
│   └── check-coverage.php
├── .dockerignore
├── .gitattributes
├── .gitignore
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
├── README.md
├── SECURITY.md
├── composer.json
├── composer.lock
├── phpstan.neon
├── phpunit-live.xml
├── phpunit.xml
└── pint.json
```
