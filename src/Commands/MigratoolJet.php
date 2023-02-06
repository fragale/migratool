<?php

namespace Fragale\Migratool\Commands;

use DB;
use Illuminate\Support\Str;


class MigratoolJet extends BaseBuilder
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'migratool:jet
                                        {--auto : asks no questions and runs in auto mode}
                                        {--remove : delete the migration file (does not work yet)}
                                        {--list : displays a list of migrations in the DB}
                                        {--find= : displays a list of matching migrations in the DB}
                                        {--purge : remove migrations found with --find}
                                        {--down= : makes a down of the migration according to the indicated #id}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Migrations Jet Tools';
  protected $table;


  protected $crud_template_path;
  public $currentMigrationIds = [];

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
    $this->auto   = $this->option('auto');
    $this->list   = $this->option('list');
    $this->find   = $this->option('find');
    $this->down   = $this->option('down');
    $this->purge  = $this->option('purge');


    /*list migrations in order and exit*/
    if ($this->list or $this->find) {
      $this->listMigrations();
      if ($this->purge and $this->find) {
        $this->down = implode(',',$this->currentMigrationIds);
      } else {
        exit();
      }
    }

    if ($this->down) {
      $this->downMigration();
    }

  }


  protected function downMigration()
  {

    //ordena los id en forma reversa para ejecutarlos en orden inverso al que fueron migradas
    $migrations = array_map('trim', explode(',', $this->down));
    rsort($migrations);

    foreach ($migrations as $order => $migration_id) {

      /*si se especifico el nombre trata de encontrar el id de la migracion */
      if (!is_numeric($migration_id)) {
        $migration_id = $this->getMigrationId($migration_id);
      }

      if ($migration_id) {
        if ($this->auto or $this->confirm('you want to delete migration #id ' . $migration_id . '? [yes|no]')) {

          $maximus = DB::table('migrations')->max('batch');
          $this->info("MAX batch #id in migrations is $maximus");

          $this->info("moving migration #id $migration_id to batch $maximus+1");

          DB::table('migrations')
            ->where('id', $migration_id)
            ->update(['batch' => $maximus + 1]);

          $this->info("rolling back migration #id $migration_id");
          \Artisan::call('migrate:rollback', ['--step' => 1]);
          $this->info("done.");
          //$this->listMigrations();
        } else {
          $this->info("I did nothing");
        }
      } else {
        $this->comment('can\'t identify this migration, nothing to do.');
      }
    }
  }

  protected function listMigrations()
  {
    if ($this->find) {
      $migrations = DB::table('migrations')->where('migration', 'like', '%' . $this->find . '%')
        ->orderBy('batch', 'asc')->get();
    } else {
      $migrations = DB::table('migrations')->orderBy('batch', 'asc')->get();
    }
    $this->info('Current migration list in DB ' . env('DB_DATABASE'));
    $this->info(sprintf("%5s %5s %-64s %-64s", '#id', 'batch', 'Migration', 'ClassName'));
    $this->comment(str_repeat('-', 128));
    foreach ($migrations as $migration) {
      $className = sprintf("%5d %5d %-64s %-64s", $migration->id, $migration->batch, $migration->migration, Str::studly(substr($migration->migration, 18)));
      $this->info($className);
      $this->currentMigrationIds[] = $migration->id;
    }
    $this->comment(str_repeat('-', 128));
    $this->info(count($migrations) . ' migrations');
    $this->comment(str_repeat('-', 128));
  }


  protected function getMigrationId($fileName)
  {
    $name = pathinfo($fileName, PATHINFO_FILENAME);
    $migration = DB::table('migrations')->where('migration', $name)->max('id');
    if ($migration) {
      return $migration;
    } else {
      return false;
    }
  }


}
