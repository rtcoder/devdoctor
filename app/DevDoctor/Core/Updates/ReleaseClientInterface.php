<?php

declare(strict_types=1);

namespace DevDoctor\Core\Updates;

interface ReleaseClientInterface
{
    public function latest(): ?ReleaseInfo;
}
