<?php

namespace Fragale\Migratool;

use Illuminate\Support\ServiceProvider;

class MigratoolServiceProvider extends ServiceProvider
{


  protected $owncommands = [
      'Fragale\Migratool\Commands\MigratoolJet',
  ];

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

      $this->registerConstants();

      $this->registerConfig();

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands($this->owncommands);

    }


    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {

    }

    /**
     * Register constants (call it before registerConfig).
     *
     * @return void
     */
    public function registerConstants()
    {

    }


}
