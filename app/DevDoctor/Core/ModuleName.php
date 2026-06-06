<?php

declare(strict_types=1);

namespace DevDoctor\Core;

enum ModuleName: string
{
    case CI = 'ci';
    case CACHE = 'cache';
    case COMPOSER = 'composer';
    case DATABASE = 'db';
    case DOCKER = 'docker';
    case ENV = 'env';
    case FRONTEND = 'frontend';
    case FLUTTER = 'flutter';
    case GIT = 'git';
    case GO = 'go';
    case HEALTH = 'health';
    case HTTP = 'http';
    case IAC = 'iac';
    case JAVA = 'java';
    case KUBE = 'kube';
    case LARAVEL = 'laravel';
    case MOBILE = 'mobile';
    case NODE = 'node';
    case PHP = 'php';
    case PORTS = 'ports';
    case PRESETS = 'presets';
    case CPP = 'cpp';
    case DOTNET = 'dotnet';
    case PYTHON = 'python';
    case QUEUE = 'queue';
    case RUBY = 'ruby';
    case RUST = 'rust';
    case SECURITY = 'security';
    case SYMFONY = 'symfony';
    case WEB = 'web';
}
