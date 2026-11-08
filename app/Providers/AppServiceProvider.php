<?php

namespace App\Providers;

use App\Domain\Extraction\CatalogueRepository;
use App\Domain\Extraction\Clock;
use App\Domain\Extraction\ExtractionGateway;
use App\Domain\Extraction\SystemClock;
use App\Infrastructure\Catalogue\EloquentCatalogueRepository;
use App\Infrastructure\Fake\FakeExtractionGateway;
use App\Infrastructure\Prism\PrismExtractionGateway;
use App\Support\IntakeConfigValidator;
use App\Support\IntakeExceptionHandler;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, IntakeExceptionHandler::class);
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(CatalogueRepository::class, EloquentCatalogueRepository::class);
        $this->app->bind(ExtractionGateway::class, fn ($app) => $app->make(
            config('intake.extraction.gateway') === 'prism'
                ? PrismExtractionGateway::class
                : FakeExtractionGateway::class
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        (new IntakeConfigValidator)->validate(config('intake.extraction'), $this->app->environment());
    }
}
