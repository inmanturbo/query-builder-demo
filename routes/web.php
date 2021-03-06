<?php

use App\Models\SushiBase;
use App\Models\User;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/{table}', function ($table) {
    if (!in_array($table, DB::connection()->getDoctrineSchemaManager()->listTableNames())) {
        abort(404);
    }

    define('SUSHI_ROWS', [
        [
            'id' => 1,
            'name' => 'John',
            'email' => 'someone@somewhere.com',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]
    ]);

    $model = (new class() extends SushiBase {
        protected function sushiConnectionConfig()
        {
            return array_merge(config('database.connections.mysql'), ['prefix' => 'sushi_', 'name' => 'mysql']);
        }

        public function getRows()
        {
            return SUSHI_ROWS;
        }
    });
    
    $model = QueryBuilder::for($model)->allowedFilters($model->getTableColumns())->paginate();
    return $model;
});
