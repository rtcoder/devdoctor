<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final readonly class Issue
{
    public ?string $hint;

    public ?FixSuggestion $fix;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        IssueCode|string $code,
        public Severity $severity,
        public string $message,
        ModuleName|string|null $module = null,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $key = null,
        public array $context = [],
        ?string $hint = null,
        ?FixSuggestion $fix = null,
        public bool $suppressed = false,
    ) {
        $this->code = $code instanceof IssueCode ? $code : IssueCode::from($code);
        $this->module = $module instanceof ModuleName || $module === null ? $module : ModuleName::from($module);

        $suggestion = IssueSuggestionCatalog::for($this->code, $context);
        $this->hint = $hint ?? $suggestion['hint'];
        $this->fix = $fix ?? $suggestion['fix'];
    }

    public IssueCode $code;

    public ?ModuleName $module;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $redactor = new Redactor;

        return array_filter([
            'code' => $this->code->value,
            'severity' => $this->severity->value,
            'message' => $redactor->redactText($this->message),
            'module' => $this->module?->value,
            'file' => $this->file,
            'line' => $this->line,
            'key' => $this->key,
            'context' => $redactor->redactContext($this->context),
            'hint' => $this->hint === null ? null : $redactor->redactText($this->hint),
            'fix' => $this->fix?->toArray($redactor),
            'suppressed' => $this->suppressed,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function withSuppressed(): self
    {
        return new self(
            code: $this->code,
            severity: $this->severity,
            message: $this->message,
            module: $this->module,
            file: $this->file,
            line: $this->line,
            key: $this->key,
            context: $this->context,
            hint: $this->hint,
            fix: $this->fix,
            suppressed: true,
        );
    }
}
