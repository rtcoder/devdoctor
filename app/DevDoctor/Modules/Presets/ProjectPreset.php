<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Presets;

enum ProjectPreset: string
{
    case LARAVEL = 'laravel';

    case MCP = 'mcp';

    case SYMFONY = 'symfony';

    case NODE = 'node';

    case FRONTEND = 'frontend';

    case VITE = 'vite';

    case NEXTJS = 'nextjs';

    case NUXT = 'nuxt';

    case ASTRO = 'astro';

    case FLUTTER = 'flutter';

    case DART = 'dart';

    case MOBILE = 'mobile';

    case ANDROID = 'android';

    case IOS = 'ios';

    case MONOREPO = 'monorepo';

    case PYTHON = 'python';

    case PIP = 'pip';

    case POETRY = 'poetry';

    case PIPENV = 'pipenv';

    case UV = 'uv';

    case CONDA = 'conda';

    case RUBY = 'ruby';

    case RAILS = 'rails';

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

    case IAC = 'iac';

    case TERRAFORM = 'terraform';

    case KUBERNETES = 'kubernetes';

    case HELM = 'helm';

    case DOCKER_COMPOSE = 'docker-compose';

    public function label(): string
    {
        return match ($this) {
            self::LARAVEL => 'Laravel',
            self::MCP => 'MCP',
            self::SYMFONY => 'Symfony',
            self::NODE => 'Node.js',
            self::FRONTEND => 'Frontend',
            self::VITE => 'Vite',
            self::NEXTJS => 'Next.js',
            self::NUXT => 'Nuxt',
            self::ASTRO => 'Astro',
            self::FLUTTER => 'Flutter',
            self::DART => 'Dart',
            self::MOBILE => 'Mobile native',
            self::ANDROID => 'Android',
            self::IOS => 'iOS',
            self::MONOREPO => 'Monorepo',
            self::PYTHON => 'Python',
            self::PIP => 'pip',
            self::POETRY => 'Poetry',
            self::PIPENV => 'Pipenv',
            self::UV => 'uv',
            self::CONDA => 'Conda',
            self::RUBY => 'Ruby',
            self::RAILS => 'Rails',
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
            self::IAC => 'Infrastructure as Code',
            self::TERRAFORM => 'Terraform/OpenTofu',
            self::KUBERNETES => 'Kubernetes',
            self::HELM => 'Helm',
            self::DOCKER_COMPOSE => 'Docker Compose',
        };
    }
}
