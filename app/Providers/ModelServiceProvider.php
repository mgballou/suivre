<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::shouldBeStrict();

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $key): void {
            if (! $this->reportModel($model)) {
                return;
            }

            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            $exception = new LazyLoadingViolationException($model, $key);

            if (app()->environment('local', 'testing')) {
                throw $exception;
            }

            report($exception);
        });
    }

    private function reportModel(Model $model): bool
    {
        return Str::contains($model::class, 'App\\Models');
    }
}
