<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
	return view('relatorio');
});

Route::get('/test-sqlsrv', function () {
	$results = DB::connection('sqlsrv2')->select('SELECT * FROM StringTable');

	return response()->json($results);
});


Route::get('/relatorio', function (\Illuminate\Http\Request $request) {
	$data = [
		'dateStart' => $request->input('dateStart'),
		'dateEnd' => $request->input('dateEnd'),
		'operator' => $request->input('operator'),
		'lote' => $request->input('lote')
	];

    $results = DB::connection('sqlsrv')->select(DB::raw("SELECT * FROM StringTable WHERE Val LIKE '" . $request->input('lot') . "'"));



	return $results;
});
