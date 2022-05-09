<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\QueryBuilder\QueryBuilder;

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

    if(!in_array($table, DB::connection()->getDoctrineSchemaManager()->listTableNames())) {
        return abort(404);
    }

    $model = eval("return new class() extends \Illuminate\Database\Eloquent\Model {

        protected \$table = '$table';

        public function getTableColumns() {
            return \$this->getConnection()->getSchemaBuilder()->getColumnListing(\$this->getTable());
        }
    
    };");

    
    $response = QueryBuilder::for($model)->allowedFilters($model->getTableColumns())->paginate(15);
    return $response;
});
 