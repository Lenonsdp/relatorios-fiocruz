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
		'lote' => $request->input('lote'),
		'ciclo' => $request->input('ciclo')
	];

	$connection = DB::connection('sqlsrv');
	$connection2 = DB::connection('sqlsrv2');
	$condicao = false;
	if (!empty($data['lote'])) {
		$condicao .=  "Val LIKE '" . $request->input('lote') . "' AND TagIndex = 4 ";
	} else if(!empty($data['operator'])) {
		$condicao .= !$condicao ? " Val = '" . $request->input('operator') . "' AND TagIndex = 3" : "OR (Val LIKE '" . $request->input('lote') . "' AND TagIndex = 3)";
	} else if (!empty($data['ciclo'])) {
		$condicao .= !$condicao ? " Val = '" . $request->input('ciclo') . "' AND TagIndex = 5" : "OR (Val LIKE '" . $request->input('ciclo') . "' AND TagIndex = 5)";
	}

	$results = $connection->select(DB::raw("SELECT * FROM StringTable WHERE " . $condicao));
	$millitm_values = array_unique(array_map(function($results) {
		return (int) $results->Millitm;
	}, $results));

	$resultsFill = $connection->select(DB::raw("SELECT * FROM StringTable WHERE Millitm in (".implode(',', $millitm_values).")"));
	$resultsNormalized = [];

	foreach ($resultsFill as $result) {
		$resultsNormalized[$result->Millitm][$result->TagIndex] = $result;
	}

	$resultsFloat = $connection->select(DB::raw("SELECT * FROM FloatTable WHERE Millitm in (".implode(',', $millitm_values).")"));

	foreach ($resultsFloat as $resultFloat) {
		$resultsNormalized[$resultFloat->Millitm][$resultFloat->TagIndex] = $resultFloat;
	}

	//* Mydb2 relatórios//

	$resultsDbRelatorios = $connection2->select(DB::raw("SELECT * FROM StringTable WHERE Millitm in (".implode(',', $millitm_values).")"));

	foreach ($resultsDbRelatorios as $resultDbRelatorios) {
		$resultsNormalized[$resultDbRelatorios->Millitm]['relatorios'][$resultDbRelatorios->TagIndex] = $resultDbRelatorios;
	}
	$resultsDbRelatoriosFloat = $connection2->select(DB::raw("SELECT * FROM FloatTable WHERE Millitm in (".implode(',', $millitm_values).")"));

	foreach ($resultsDbRelatoriosFloat as $resultDbRelatoriosFloat) {
		$resultsNormalized[$resultDbRelatoriosFloat->Millitm]['relatorios'][$resultDbRelatoriosFloat->TagIndex] = $resultDbRelatoriosFloat;
	}

	$resultDatas = $connection->select(DB::raw("SELECT MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate FROM StringTable WHERE Millitm in (".implode(',', $millitm_values).")"));

	return [
		'results' => $resultsNormalized,
		'datas' => $resultDatas
	];
});
