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

Route::get('/impressao', function () {
	return view('impressao');
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
	$sanitizedData = array_map('htmlspecialchars', $data);
	$connection = DB::connection('relatorio');
	$connection2 = DB::connection('alarme');
	$result_datas = $connection->table('StringTable')
	->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
		->when($sanitizedData['dateStart'], function ($query, $DateAndTime) {
			return $query->where('DateAndTime', '>', $DateAndTime);
		})
		->when($sanitizedData['dateEnd'], function ($query, $DateAndTime) {
			return $query->where('DateAndTime', '<', $DateAndTime);
		})
		->when($sanitizedData['lote'], function ($query, $lote) {
			return $query->where('Val', 'LIKE', $lote)->where('TagIndex', 8);
		})
		->when($sanitizedData['operator'], function ($query, $operator) {
			return $query->where('Val', $operator)->where('TagIndex', 7);
		})
		->get();
	if ($result_datas[0]->MinDate == null) {
		return [];
	}

	$result_lotes = $connection->table('StringTable')
	->selectRaw('distinct Val')
		->when($sanitizedData['lote'], function ($query, $lote) {
			return $query->where('Val', 'LIKE', $lote)->where('TagIndex', 8);
		})
		->when($sanitizedData['operator'], function ($query, $operator) {
			return $query->where('Val', $operator)->where('TagIndex', 7);
		})
		->where('TagIndex', 8)
		->where('DateAndTime', '>', $result_datas[0]->MinDate)
		->where('DateAndTime', '<', $result_datas[0]->MaxDate)
		->get();
	if (count($result_lotes) > 1) {
		foreach($result_lotes as $result_lote) {
			if ($result_lote->Val == null || empty(trim($result_lote->Val))) {
				continue;
			}
			$resultMultipleLotesData = $connection->table('StringTable')
			->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
			->where('Val', 'LIKE', $result_lote->Val)
			->get();
			$result[$result_lote->Val]['StringTable'] = $connection->table('StringTable')
			->select('*')
			->where('DateAndTime', '>', $resultMultipleLotesData[0]->MinDate)
			->where('DateAndTime', '<', $resultMultipleLotesData[0]->MaxDate)
			->get();

			$result[$result_lote->Val]['FloatTable'] = $connection->table('FloatTable')
				->select('*')
				->where('DateAndTime', '>', $resultMultipleLotesData[0]->MinDate)
				->where('DateAndTime', '<', $resultMultipleLotesData[0]->MaxDate)
				->get();
			// $resultAlarme = $connection2->table('Alarme')
			// 	->select('*')
			// 	->where('DateAndTime', '>', $result_datas[0]->MinDate)
			// 	->where('DateAndTime', '<', $result_datas[0]->MaxDate)
			// 	->get();


			$result[$result_lote->Val]['dataMin'] = $resultMultipleLotesData[0]->MinDate;
			$result[$result_lote->Val]['dataMax'] = $resultMultipleLotesData[0]->MaxDate;
			foreach($result as $lote) {
				foreach($lote['StringTable'] as $stringTable) {
					$result[$result_lote->Val]['DataIndex'][$stringTable->DateAndTime][] = $stringTable;
				}
				foreach($lote['FloatTable'] as $floatTable) {
					$result[$result_lote->Val]['DataIndex'][$floatTable->DateAndTime][] = $floatTable;
				}
			}

			foreach($result[$result_lote->Val]['DataIndex'] as $dataIndex) {
				foreach($dataIndex as $data) {
					if ($data->TagIndex == 0) {
						$result[$result_lote->Val]['Ciclo_ok_nok'] = $data->Val;
					} else if ($data->TagIndex == 1) {
						$result[$result_lote->Val]['ID_Dorna'] = $data->Val;
					} else if ($data->TagIndex == 13) {
						$result[$result_lote->Val]['Nome_receita'] = $data->Val;
					} else if ($data->TagIndex == 7) {
						$result[$result_lote->Val]['NomeUsuario'] = $data->Val;
					} else if ($data->TagIndex == 8) {
						$result[$result_lote->Val]['Num_Lote'] = $data->Val;
					} else if ($data->TagIndex == 9) {
						$result[$result_lote->Val]['Fase'][$data->Val][] = $data->DateAndTime;
					} else if ($data->TagIndex == 2) {
						$result[$result_lote->Val]['PH_receita'] = $data->Val;
					} else if ($data->TagIndex == 3) {
						$result[$result_lote->Val]['Temperatura_receita'] = $data->Val;
					} else if ($data->TagIndex == 4) {
						$result[$result_lote->Val]['Tempo_execucao'][$data->DateAndTime] = $data->Val;
					} else if ($data->TagIndex == 5) {
						$result[$result_lote->Val]['Tempo_inativo'][$data->DateAndTime] = $data->Val;
					} else if ($data->TagIndex == 6) {
						$result[$result_lote->Val]['Veloc_receita'] = $data->Val;
					} else if ($data->TagIndex == 10) {
						$result[$result_lote->Val]['PH'][$data->DateAndTime] = $data->Val;
					} else if ($data->TagIndex == 11) {
						$result[$result_lote->Val]['Temperatura'][$data->DateAndTime] = $data->Val;
					} else if ($data->TagIndex == 12) {
						$result[$result_lote->Val]['Velocidade'][$data->DateAndTime] = $data->Val;
					}
				}
			}
			unset($result[$result_lote->Val]['DataIndex']);
		}
		return $result;
	} else if (count($result_lotes) == 1){
		$result[$result_lotes[0]->Val]['StringTable'] = $connection->table('StringTable')
		->select('*')
		->where('DateAndTime', '>', $result_datas[0]->MinDate)
		->where('DateAndTime', '<', $result_datas[0]->MaxDate)
		->get();

		$result[$result_lotes[0]->Val]['FloatTable'] = $connection->table('FloatTable')
		->select('*')
		->where('DateAndTime', '>', $result_datas[0]->MinDate)
		->where('DateAndTime', '<', $result_datas[0]->MaxDate)
		->get();

		$result[$result_lotes[0]->Val]['dataMin'] = $result_datas[0]->MinDate;
		$result[$result_lotes[0]->Val]['dataMax'] = $result_datas[0]->MaxDate;
		foreach($result as $lote) {
			foreach($lote['StringTable'] as $stringTable) {
				$result[$result_lotes[0]->Val]['DataIndex'][$stringTable->DateAndTime][] = $stringTable;
			}
			foreach($lote['FloatTable'] as $floatTable) {
				$result[$result_lotes[0]->Val]['DataIndex'][$floatTable->DateAndTime][] = $floatTable;
			}
		}

		unset($result[$result_lotes[0]->Val]['StringTable']);
		unset($result[$result_lotes[0]->Val]['FloatTable']);

		foreach($result[$result_lotes[0]->Val]['DataIndex'] as $dataIndex) {
			foreach($dataIndex as $data) {
				if ($data->TagIndex == 0) {
					$result[$result_lotes[0]->Val]['Ciclo_ok_nok'] = $data->Val;
				} else if ($data->TagIndex == 1) {
					$result[$result_lotes[0]->Val]['ID_Dorna'] = $data->Val;
				} else if ($data->TagIndex == 13) {
					$result[$result_lotes[0]->Val]['Nome_receita'] = $data->Val;
				} else if ($data->TagIndex == 7) {
					$result[$result_lotes[0]->Val]['NomeUsuario'] = $data->Val;
				} else if ($data->TagIndex == 8) {
					$result[$result_lotes[0]->Val]['Num_Lote'] = $data->Val;
				} else if ($data->TagIndex == 9) {
					$result[$result_lotes[0]->Val]['Fase'][$data->Val][] = $data->DateAndTime;
				} else if ($data->TagIndex == 2) {
					$result[$result_lotes[0]->Val]['PH_receita'] = $data->Val;
				} else if ($data->TagIndex == 3) {
					$result[$result_lotes[0]->Val]['Temperatura_receita'] = $data->Val;
				} else if ($data->TagIndex == 4) {
					$result[$result_lotes[0]->Val]['Tempo_execucao'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 5) {
					$result[$result_lotes[0]->Val]['Tempo_inativo'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 6) {
					$result[$result_lotes[0]->Val]['Veloc_receita'] = $data->Val;
				} else if ($data->TagIndex == 10) {
					$result[$result_lotes[0]->Val]['PH'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 11) {
					$result[$result_lotes[0]->Val]['Temperatura'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 12) {
					$result[$result_lotes[0]->Val]['Velocidade'][$data->DateAndTime] = $data->Val;
				}
			}
		}
		unset($result[$result_lotes[0]->Val]['DataIndex']);
		// $resultAlarme = $connection2->table('Alarme')
		// 	->select('*')
		// 	->where('DateAndTime', '>', $result_datas[0]->MinDate)
		// 	->where('DateAndTime', '<', $result_datas[0]->MaxDate)
		// 	->get();
	}

	return $result;

});
