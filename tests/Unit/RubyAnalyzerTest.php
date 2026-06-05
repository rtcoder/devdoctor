<?php

use DevDoctor\Modules\Ruby\RubyAnalyzer;
use DevDoctor\Modules\Ruby\RubyOptions;

function rubyFixture(array $files): string
{
    $path = sys_get_temp_dir().'/devdoctor-ruby-'.bin2hex(random_bytes(4));
    mkdir($path);

    foreach ($files as $file => $contents) {
        $target = $path.'/'.$file;
        $directory = dirname($target);

        if (! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        file_put_contents($target, $contents);
    }

    return $path;
}

it('reports non ruby projects as info', function () {
    $issues = (new RubyAnalyzer)->analyze(new RubyOptions(path: rubyFixture([])));

    expect($issues->all()[0]->code->value)->toBe('DD_RUBY_NOT_PROJECT');
});

it('reports ready ruby projects', function () {
    $issues = (new RubyAnalyzer)->analyze(new RubyOptions(path: rubyFixture([
        'Gemfile' => "source 'https://rubygems.org'\nruby '3.4.0'\ngem 'rake'\n",
        'Gemfile.lock' => "GEM\n",
        '.ruby-version' => "3.4.0\n",
    ])));

    expect($issues->all()[0]->code->value)->toBe('DD_RUBY_READY');
});

it('reports missing lockfiles and version conflicts', function () {
    $issues = (new RubyAnalyzer)->analyze(new RubyOptions(path: rubyFixture([
        'Gemfile' => "source 'https://rubygems.org'\nruby '3.3.0'\n",
        '.ruby-version' => "3.4.0\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUBY_LOCK_MISSING')
        ->and($codes)->toContain('DD_RUBY_VERSION_CONFLICT');
});

it('reports risky gem sources and rails credential risks', function () {
    $issues = (new RubyAnalyzer)->analyze(new RubyOptions(path: rubyFixture([
        'Gemfile' => "source 'http://rubygems.org'\ngem 'rails'\ngem 'local', path: '../local'\n",
        'Gemfile.lock' => "GEM\n",
        'config/application.rb' => "module Demo\nend\n",
        'config/credentials.yml.enc' => "encrypted\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUBY_RISKY_GEM_SOURCE')
        ->and($codes)->toContain('DD_RUBY_RAILS_MASTER_KEY_MISSING');
});

it('reports literal database credentials', function () {
    $issues = (new RubyAnalyzer)->analyze(new RubyOptions(path: rubyFixture([
        'Gemfile' => "source 'https://rubygems.org'\ngem 'rails'\n",
        'Gemfile.lock' => "GEM\n",
        'config/application.rb' => "module Demo\nend\n",
        'config/master.key' => "key\n",
        'config/database.yml' => "production:\n  username: root\n  password: secret\n",
    ])));
    $codes = array_map(static fn ($issue): string => $issue->code->value, $issues->all());

    expect($codes)->toContain('DD_RUBY_DATABASE_SECRET');
});
