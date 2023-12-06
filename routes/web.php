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
		'dateStart' => $request->input('startDate'),
		'dateEnd' => $request->input('endDate'),
		'operator' => $request->input('operator'),
		'lote' => $request->input('lote'),
		'ciclo' => $request->input('ciclo')
	];

	// Sanitize inputs
	$sanitizedData = array_map('htmlspecialchars', $data);

	$connection = DB::connection('sqlsrv');
	$connection2 = DB::connection('sqlsrv2');
	$resultsNormalized = [];

	$millitm_values = $connection->table('StringTable')
		->select('Millitm')
		// ->where('DateAndTime', '>', $sanitizedData['dateStart'])
		// ->where('DateAndTime', '<', $sanitizedData['dateEnd'])
		->when($sanitizedData['lote'], function ($query, $lote) {
			return $query->where('Val', 'LIKE', $lote)->where('TagIndex', 4);
		})
		->when($sanitizedData['operator'], function ($query, $operator) {
			return $query->where('Val', $operator)->where('TagIndex', 3);
		})
		->when($sanitizedData['ciclo'], function ($query, $ciclo) {
			return $query->where('Val', $ciclo)->where('TagIndex', 5);
		})
		->pluck('Millitm')
		->unique()
		->toArray();

	$resultDatas = $connection->table('StringTable')
		->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
		->whereIn('Millitm', $millitm_values)
		// ->where('DateAndTime', '>', $sanitizedData['dateStart'])
		// ->where('DateAndTime', '<', $sanitizedData['dateEnd'])
		->when($sanitizedData['lote'], function ($query, $lote) {
			return $query->where('Val', 'LIKE', $lote)->where('TagIndex', 4);
		})
		->when($sanitizedData['operator'], function ($query, $operator) {
			return $query->where('Val', $operator)->where('TagIndex', 3);
		})
		->when($sanitizedData['ciclo'], function ($query, $ciclo) {
			return $query->where('Val', $ciclo)->where('TagIndex', 5);
		})
		->get();

	if (!empty($millitm_values)) {
		$resultsFill = $connection->table('StringTable')
			->whereIn('Millitm', $millitm_values)
			->whereBetween('DateAndTime', [$resultDatas[0]->MinDate, $resultDatas[0]->MaxDate])
			->get();

		foreach ($resultsFill as $result) {
			$resultsNormalized[$result->Millitm][$result->TagIndex] = $result;
		}

		$resultsFloat = $connection->table('FloatTable')
			->whereIn('Millitm', $millitm_values)
			->whereBetween('DateAndTime', [$resultDatas[0]->MinDate, $resultDatas[0]->MaxDate])
			->get();

		foreach ($resultsFloat as $resultFloat) {
			$resultsNormalized[$resultFloat->Millitm][$resultFloat->TagIndex] = $resultFloat;
		}

		// Mydb2 relatórios
		$resultsDbRelatorios = $connection2->table('StringTable')
			->whereIn('Millitm', $millitm_values)
			->get();

		foreach ($resultsDbRelatorios as $resultDbRelatorios) {
			$resultsNormalized[$resultDbRelatorios->Millitm]['relatorios'][$resultDbRelatorios->TagIndex] = $resultDbRelatorios;
		}

		$resultsDbRelatoriosFloat = $connection2->table('FloatTable')
			->whereIn('Millitm', $millitm_values)
			->get();

		foreach ($resultsDbRelatoriosFloat as $resultDbRelatoriosFloat) {
			$resultsNormalized[$resultDbRelatoriosFloat->Millitm]['relatorios'][$resultDbRelatoriosFloat->TagIndex] = $resultDbRelatoriosFloat;
		}

		return [
			'results' => $resultsNormalized,
			'datas' => $resultDatas
		];
	}

	return []; // Or handle empty case as needed
});
