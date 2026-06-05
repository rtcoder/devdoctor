<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Http;

final readonly class HttpOptions
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        public string $path,
        public array $urls = [],
        public bool $strict = false,
    ) {}
}
