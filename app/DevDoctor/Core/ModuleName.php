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
    case GIT = 'git';
    case HEALTH = 'health';
    case LARAVEL = 'laravel';
    case NODE = 'node';
    case PHP = 'php';
    case PORTS = 'ports';
    case PRESETS = 'presets';
    case QUEUE = 'queue';
    case SECURITY = 'security';
}
