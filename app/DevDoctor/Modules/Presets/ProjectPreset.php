<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Presets;

enum ProjectPreset: string
{
    case LARAVEL = 'laravel';

    case SYMFONY = 'symfony';

    case NODE = 'node';

    case FRONTEND = 'frontend';

    case VITE = 'vite';

    case NEXTJS = 'nextjs';

    case NUXT = 'nuxt';

    case ASTRO = 'astro';

    case PYTHON = 'python';

    case PIP = 'pip';

    case POETRY = 'poetry';

    case PIPENV = 'pipenv';

    case UV = 'uv';

    case CONDA = 'conda';

    case GO = 'go';

    case RUST = 'rust';

    case JAVA = 'java';

    case MAVEN = 'maven';

    case GRADLE = 'gradle';

    case ANT = 'ant';

    case SPRING = 'spring';

    case CPP = 'cpp';

    case CMAKE = 'cmake';

    case DOTNET = 'dotnet';

    case WEB = 'web';

    case DOCKER_COMPOSE = 'docker-compose';

    public function label(): string
    {
        return match ($this) {
            self::LARAVEL => 'Laravel',
            self::SYMFONY => 'Symfony',
            self::NODE => 'Node.js',
            self::FRONTEND => 'Frontend',
            self::VITE => 'Vite',
            self::NEXTJS => 'Next.js',
            self::NUXT => 'Nuxt',
            self::ASTRO => 'Astro',
            self::PYTHON => 'Python',
            self::PIP => 'pip',
            self::POETRY => 'Poetry',
            self::PIPENV => 'Pipenv',
            self::UV => 'uv',
            self::CONDA => 'Conda',
            self::GO => 'Go',
            self::RUST => 'Rust',
            self::JAVA => 'Java/JVM',
            self::MAVEN => 'Maven',
            self::GRADLE => 'Gradle',
            self::ANT => 'Ant',
            self::SPRING => 'Spring',
            self::CPP => 'C/C++',
            self::CMAKE => 'CMake',
            self::DOTNET => '.NET',
            self::WEB => 'Generic web',
            self::DOCKER_COMPOSE => 'Docker Compose',
        };
    }
}
