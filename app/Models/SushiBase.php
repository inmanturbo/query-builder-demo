<?php

namespace App\Models;

use Closure;
use Sushi\Sushi;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

abstract class SushiBase extends Model
{
    use HasFactory;
    use Sushi;

    public $timestamps = true;
  
    public function getTableColumns()
    {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        if (! isset($this->table)) {
            $needles = ['/', "\00", '\\', '.', '@', ':', '$', '-'];
            return str_replace($needles, '_', Str::snake(class_basename((new static)), '_'));
        }

        return $this->table;
    }

    protected function sushiShouldCache()
    {
        return true;
    }

    protected function cacheFileName()
    {
        return config('sushi.cache-prefix', 'sushi').'-'.Str::kebab(str_replace(['/', "\00", '\\'], ' ', static::class)).'.sqlite';
    }

    public static function bootSushi()
    {
        $instance = (new static);

        $cacheFileName = $instance->cacheFileName();
        $cacheDirectory = realpath(config('sushi.cache-path', storage_path('framework/cache')));
        $cachePath = $cacheDirectory.'/'.$cacheFileName;
        $dataPath = $instance->sushiCacheReferencePath();

        $databaseConfig = method_exists($instance, 'sushiConnectionConfig') ? $instance->sushiConnectionConfig() : [
            'driver' => 'sqlite',
            'database' => $cachePath,
        ];

        $states = [
            'cache-file-found-and-up-to-date' => function () use ($databaseConfig, $instance) {
                static::setDatabaseConnection($databaseConfig);
                if (!static::resolveConnection()->getSchemaBuilder()->hasTable($instance->getTable())) {
                    $instance->migrate();
                }       
            },
            'cache-file-not-found-or-stale' => function () use ($cachePath, $databaseConfig, $dataPath, $instance) {
                file_put_contents($cachePath, '');

                static::setDatabaseConnection($databaseConfig);

                $instance->migrate();

                touch($cachePath, filemtime($dataPath));
            },
            'no-caching-capabilities' => function () use ($instance) {
                static::setDatabaseConnection([
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ]);

                $instance->migrate();
            },
        ];

        switch (true) {
            case ! $instance->sushiShouldCache():
                $states['no-caching-capabilities']();
                break;

            case file_exists($cachePath) && filemtime($dataPath) <= filemtime($cachePath):
                $states['cache-file-found-and-up-to-date']();
                break;

            case file_exists($cacheDirectory) && is_writable($cacheDirectory):
                $states['cache-file-not-found-or-stale']();
                break;

            default:
                $states['no-caching-capabilities']();
                break;
        }
    }

    protected static function setDatabaseConnection($config)
    {
        static::$sushiConnection = app(ConnectionFactory::class)->make($config);

        app('config')->set('database.connections.'.(new static)->getConnectionName(), $config);
    }

    protected function createTableSafely(string $tableName, Closure $callback)
    {
        /** @var \Illuminate\Database\Schema\SQLiteBuilder $schemaBuilder */
        $schemaBuilder = static::resolveConnection()->getSchemaBuilder();

        try {
            $schemaBuilder->dropIfExists($tableName);
            $schemaBuilder->create($tableName, $callback);
        } catch (QueryException $e) {
            if (Str::contains($e->getMessage(), 'already exists (SQL: create table')) {
                // This error can happen in rare circumstances due to a race condition.
                // Concurrent requests may both see the necessary preconditions for
                // the table creation, but only one can actually succeed.
                return;
            }

            throw $e;
        }
    }

    public function getConnectionName()
    {
        return str_replace('.', '-', (new static)->cacheFileName());
    }
}
