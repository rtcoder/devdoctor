<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Presets;

enum ProjectPreset: string
{
    case LARAVEL = 'laravel';

    case SYMFONY = 'symfony';

    case NODE = 'node';

    case VITE = 'vite';

    case NEXTJS = 'nextjs';

    case DOCKER_COMPOSE = 'docker-compose';

    public function label(): string
    {
        return match ($this) {
            self::LARAVEL => 'Laravel',
            self::SYMFONY => 'Symfony',
            self::NODE => 'Node.js',
            self::VITE => 'Vite',
            self::NEXTJS => 'Next.js',
            self::DOCKER_COMPOSE => 'Docker Compose',
        };
    }
}
