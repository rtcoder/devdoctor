<?php

declare(strict_types=1);

namespace App\DevDoctor\Core\Output;

enum OutputFormat: string
{
    case TABLE = 'table';

    case JSON = 'json';

    case SARIF = 'sarif';
}
