<?php

declare(strict_types=1);

namespace DevDoctor\Core;

final class HintAndFixIssue
{
    public function __construct(
        public ?string        $hint = null,
        public ?FixSuggestion $fix = null
    )
    {
    }
}
