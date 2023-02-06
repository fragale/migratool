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
                                        {module? : nombre del modulo [opcional]}
                                        {table? : nombre de la tabla [opcional]}
                                        {--auto : no hace preguntas y ejecuta en modo auto}
                                        {--remove : elimina el file de migracion (no funciona todavia)}
                                        {--list : muestra un listado de las migraciones en la DB}
                                        {--find= : muestra un listado de las migraciones que coincidan en la DB}
                                        {--purge : elimina las migraciones encontradas con --find}
                                        {--down= : hace un down de la migracion segun el #id indicado}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Genera migracion';
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


    // dump($this->argument('module'));
    // dump($this->argument('table'));

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
      //exit();
    }

    if ($this->argument('module') and $this->argument('table')) { /*Seguir aqui*/


      if (!$this->down) {

        $this->setPaths($this->argument('module'));

        $this->build();

        if ($this->hasPivots()) {
          \Artisan::call('builder:migration_pivot',  ['module' => $this->argument('module'), 'table' => $this->argument('table'), '--auto' => 'true']);
        }
      } else {
        $this->info("Nothing to do.");
      }
    }
  }


  protected function hasPivots()
  {
    if (array_key_exists('relations', $this->crud)) {

      foreach ($this->crud['relations'] as $relationName => $field) {
        if ($field['relation_type'] == 'belongsToMany') {

          return true;
        }
      }
    }

    return false;
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


  protected function build()
  {
    $this->loadCRUDdefinitions();
    $this->info("Building the migration $this->migrationFileName ...");
    // $this->setMigrationName();

    if (!array_key_exists('migration', $this->crud)) {
      $this->info("nothing to do!!, the property migration is missing on this CRUD.");
      return false;
    }

    if (is_string($this->crud['migration'])) {

      $this->migrationTemplateFileName = $this->crud_template_path . '/' . $this->crud['migration'] . '.php';
    } else {

      $this->migrationTemplateFileName = $this->builder_templates_path . '/Migrations/create_table.php';
    }

    $filename = $this->migrations_path . '/' . $this->migrationFileName;

    $this->showInfoHeader($filename, $this->migrationFileName);

    $this->tryToCopyFile($this->file, $this->migrationTemplateFileName, $this->migrations_path, $this->migrationFileName);

    $template = $this->getTemplate($filename);

    $translations = [
      'author' => $this->projectOwner,
      'project' => $this->projectName,       
      'model' => $this->model,
      'Model' => $this->Model,
      'models' => $this->models,
      'Models' => $this->Models,
      'Module' => $this->Module,
      'module' => $this->module,
      'foreigns' => $this->makeFieldTail('foreigns', $this->crud),
      'index' => $this->makeFieldTail('indexes', $this->crud),
      'migrationFields' => $this->makeMigrationFields($this->crud['fields']) . PHP_EOL . "\t\t\t\t\t\t" . $this->makeFieldTail('fieldsExtra', $this->crud),
      'className' => $this->migrationClassName,
      'primary' => $this->valueOrFail($this->migrationExtras, 'primary', "bigIncrements('id')"),  // por ejemplo se puede usar increments('id');

    ];

    $this->saveTemplate($filename, $this->translateTemplate($template, $translations));


    $this->info("done.");
  }


  /**
   * Make de static rules section for the model
   *
   * @return string
   */

  public function makeMigrationFields($fields)
  {

    $this->info("Making migration fields ...");

    $migrationFields = [];

    foreach ($fields as $field) {
      $name = $field['name'];

      if (array_key_exists('dbType', $field)) {
        $type = $field['dbType'];
      } else {
        $type = $this->translateType($field['type']);
      }

      $prec = '';
      if (array_key_exists('prec', $field)) {
        $prec = ', ' . $field['prec'];
      }

      $modifier = '';
      if (array_key_exists('modifier', $field)) {
        $modifier = '->' . $field['modifier'];
      }

      $migrationFields[] = "\$table->$type('$name'$prec)$modifier;";
    }

    /*si hay relaciones polimorficas agrega los morphs*/
    $morphs = $this->makeMorphFields();
    if ($morphs != '') {

      $migrationFields[] = $morphs;
    }

    return implode(PHP_EOL . "\t\t\t", $migrationFields);
  }



  public function makeMorphFields()
  {

    $migrationFields = [];

    if (array_key_exists('relations', $this->crud)) {

      foreach ($this->crud['relations'] as $name => $relation) {

        if ($relation['relation_type'] == 'morphTo' and $this->valueOrFail($relation, 'migrate', false)) {

          $migrationFields[] = "\$table->morphs('$name');";
        }
      }
    }

    return (empty($migrationFields)) ? '' : implode(PHP_EOL . "\t\t\t\t\t\t", $migrationFields);
  }
}
