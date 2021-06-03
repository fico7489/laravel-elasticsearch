<?php

namespace Fico7489\Es;

use Fico7489\Es\Commands\RecreateIndexImportData;
use Illuminate\Support\ServiceProvider;

class EsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->registerCommands();
    }

    protected function registerCommands(): void
    {
        $this->commands([
            RecreateIndexImportData::class,
        ]);
    }
}
