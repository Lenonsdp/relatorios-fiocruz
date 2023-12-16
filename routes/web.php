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
		'lote' => $request->input('lote')
	];
	$sanitizedData = array_map('htmlspecialchars', $data);
	$connection = DB::connection('relatorio');
	$connection2 = DB::connection('alarme');
	$result = [];
	$result_datas = $connection->table('StringTable')
	->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
		->when($sanitizedData['dateStart'] && $sanitizedData['dateEnd'], function ($query) use ($sanitizedData) {
			return $query->whereBetween('DateAndTime', [formatDataBD($sanitizedData['dateStart']) . ' 00:00:00', formatDataBD($sanitizedData['dateEnd']) . ' 23:59:59']);
		})
		->when($sanitizedData['lote'] || $sanitizedData['operator'], function ($query) use ($sanitizedData) {
			return $query->where(function ($query) use ($sanitizedData) {
				if ($sanitizedData['lote']) {
					$query->orWhere('Val', 'LIKE', $sanitizedData['lote'])->where('TagIndex', 8);
				} else if ($sanitizedData['operator']) {
					$query->orWhere('Val', $sanitizedData['operator'])->where('TagIndex', 7);
				}
			});
		})
		->get();
	if ($result_datas[0]->MinDate == null) {
		return [];
	}

	$result_lotes = $connection->table('StringTable')
	->selectRaw('distinct Val')
		// ->when($sanitizedData['lote'] || $sanitizedData['operator'], function ($query) use ($sanitizedData) {
		// 	return $query->where(function ($query) use ($sanitizedData) {
		// 		if ($sanitizedData['lote']) {
		// 			$query->orWhere('Val', 'LIKE', $sanitizedData['lote'])->where('TagIndex', 8);
		// 		} else if ($sanitizedData['operator']) {
		// 			$query->orWhere('Val', $sanitizedData['operator'])->where('TagIndex', 7);
		// 		}
		// 	});
		// })
		->where('TagIndex', 8)
		->where('DateAndTime', '>', $result_datas[0]->MinDate)
		->where('DateAndTime', '<', $result_datas[0]->MaxDate)
		->get();
	if (count($result_lotes) > 1) {
		$i = 0;
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

			$result[$result_lote->Val]['Alarme'] = $connection2->table('AllEvent')
				->select(
					'ServerName',
					'EventTimeStamp',
					'EventCategory',
					'severity',
					'Message',
					'Priority',
					'Active',
					'Acked',
					'ConditionName'
				)
				->whereBetween('EventTimeStamp', [$result_datas[0]->MinDate, $result_datas[0]->MaxDate])
				->where('Message', 'LIKE', 'Alarme:%')
				->get();

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
			$i++;
		}
		foreach($result as &$lote) {
			foreach($lote as &$val) {
				unset($lote['StringTable']);
				unset($lote['FloatTable']);
				unset($lote['DataIndex']);
			}
		}
	} else if (count($result_lotes) == 1) {
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
		$result[$result_lotes[0]->Val]['Alarme'] = $connection2->table('AllEvent')
			->select(
				'ServerName',
				'EventTimeStamp',
				'EventCategory',
				'severity',
				'Message',
				'Priority',
				'Active',
				'Acked',
				'ConditionName'
			)
			->whereBetween('EventTimeStamp', [$result_datas[0]->MinDate, $result_datas[0]->MaxDate])
			->where('Message', 'LIKE', 'Alarme:%')
			->get();
		}
	return $result;


});

function formatDataBD($ts) {
	if ($ts == '') {
		return '';
	}
	$tmp = cvdate($ts);
	$d = getdate($tmp);
	$yr = $d["year"];
	$mo = $d["mon"];
	$da = $d["mday"];

    return sprintf("%04d-%02d-%02d", $yr, $mo, $da);
}

function cvdate($s) {
	$delimiter = '';
	$s = str_replace(' de ','/',strtolower($s));
	if (strpos($s,'-') >0 ) $delimiter = '-';
	elseif (strpos($s,'/')>0) $delimiter = '/';
	elseif (strpos($s,' ')>0) $delimiter = ' ';
	elseif (strpos($s,'.')>0) $delimiter = '.';
	$s = str_replace(', ',$delimiter,$s);
	if (empty($delimiter)) return 0;
	$p1 = strpos($s,$delimiter);
	$p2 = strpos($s,$delimiter,$p1+1);
	$a = substr($s,$p2+1);
	$m = substr($s,$p1+1,$p2-($p1+1));
	$d = substr($s,0,$p1);
	if (intval($a) < 100) {
		$a = (intval($a) > 69) ? strval(1900+intval($a)) : strval(2000+intval($a));
	}
	if (intval($m) == 0) {
		return cvdate_portugues($d,$m,$a);
	} else {
		return cvdate_numerico($d,$m,$a);
	}
}

function cvdate_numerico($d,$m,$y) {
	$d2 = 0;
	$m2 = 0;
	$y2 = 0;
	$d2 = intval($d);
	$m2 = intval($m);
	$y2 = intval($y);
	if (($d2 == 0) || ($m2 == 0) || ($y2 == 0)) return 0;
	return mktime(0, 0, 0, $m2, $d2, $y2);
}

function cvdate_portugues($d,$m,$y) {
	$d2=0; $m2=0; $y2=0;
	$d2=intval($d);
	$m=strtolower($m);
	switch(substr($m,0,3)) {
		case 'jan': $m2=1; break;
		case 'fev': $m2=2; break;
		case 'mar': $m2=3; break;
		case 'abr': $m2=4; break;
		case 'mai': $m2=5; break;
		case 'jun': $m2=6; break;
		case 'jul': $m2=7; break;
		case 'ago': $m2=8; break;
		case 'set': $m2=9; break;
		case 'out': $m2=10; break;
		case 'nov': $m2=11; break;
		case 'dez': $m2=12; break;
	}
	$y2=intval($y);
	if (($d2==0)||($m2==0)||($y2==0)) return 0;
	return mktime(0,0,0,$m2,$d2,$y2);
}
