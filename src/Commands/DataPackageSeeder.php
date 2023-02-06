<?php
namespace Fragale\Migratool\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DataPackageSeeder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migratool:seed
                                    {package : name of the data package to seed}
                                    {--rollback : empty all the tables that are in the package}
                                    {--v : Verbose}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a seeder with a data package';

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
        $package = $this->argument('package');
        $packageName = Str::studly($package);
        $packagePath = database_path('seeders/packages/' . $packageName);
        $configPath = $packagePath . '/config.php';

        if (!file_exists($packagePath))
            return $this->packageNotFound($packagePath);
        if (!file_exists($configPath))
            return $this->configNotFound($packageName, $configPath);

        $this->line("Seeding package <fg=magenta;>". Str::studly($package)."</>");

        $packageSeeder = require $configPath;

        if (!isset($packageSeeder['seeds']))
            return $this->noSeedsIndexDefined();
        
        \DB::beginTransaction();
        Schema::disableForeignKeyConstraints();
        foreach ($packageSeeder['seeds'] as $seeder) {
            $seederPath = $packagePath . '/' . $seeder . '.php';

            $this->line("\nRunning   <fg=yellow;>$seeder</>");
            if (!file_exists($seederPath)) {
                $this->seederNotFound($seeder, $seederPath);
                continue;
            }
            try {
                include_once $seederPath;
                if($this->option('v')){
                    $this->line("\nFile   <fg=green;>$seederPath</>");
                    $this->line("\nClass   <fg=green;>$seeder</>");
                }
                $class = new $seeder();
                $this->line("Executing {$seeder}");
                if ($this->option('rollback')) {
                    if (method_exists($class, 'rollback')) {
                        $class->rollback();
                        $this->line("Rolling back   <fg=yellou;>$seeder</>");
                    }
                } else {
                    $class->run();
                    $this->line("Seeding   <fg=green;>$seeder</>");
                }
                $this->line("Success   <fg=green;>$seeder</>");
            } catch (\Exception $e) {
                $this->line("Failed    <fg=red;>$seeder</>");
                $this->error($e->getMessage());
                $this->line('');
            }
        }
        Schema::enableForeignKeyConstraints();
        \DB::commit();
    }

    protected function packageNotFound ($path)
    {
        $this->error("Data package folder not found @ $path");
        return false;
    }

    protected function configNotFound ($package, $path)
    {
        $this->error("Config file for package $package (config.php) not found at $path, polease read the documentation.");
        return false;
    }

    protected function seederNotFound ($seeder, $path)
    {
        $this->line("Skipping <fg=red;>$seeder (not found)</>");
        return false;
    }

    protected function noSeedsIndexDefined ()
    {
        $this->error("There's no 'seeds' entry defined in config.php.");
        return false;
    }
}
