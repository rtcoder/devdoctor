<?php

namespace DevDoctor\Providers;

use DevDoctor\Core\Updates\GitHubReleaseClient;
use DevDoctor\Core\Updates\ReleaseClientInterface;
use DevDoctor\Core\Updates\UpdateNotifier;
use DevDoctor\Core\VersionResolver;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            app(UpdateNotifier::class)->notifyIfAvailable(
                $event->input,
                $event->output,
                app(VersionResolver::class)->current(),
            );
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReleaseClientInterface::class, GitHubReleaseClient::class);
    }
}
