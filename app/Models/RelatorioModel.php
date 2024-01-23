<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RelatorioModel {

	public function getDatasMinMaxStringTable($data) {
		$connection = DB::connection('relatorio');
		$sanitizedData = array_map('htmlspecialchars', $data);
		return $connection->table('StringTable')
		->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
			->when($sanitizedData['dateStart'] && $sanitizedData['dateEnd'], function ($query) use ($sanitizedData) {
				return $query->whereBetween('DateAndTime', [$sanitizedData['dateStart'], $sanitizedData['dateEnd']]);
			})
			->when($sanitizedData['lote'] || $sanitizedData['operator'], function ($query) use ($sanitizedData) {
				return $query->where(function ($query) use ($sanitizedData) {
					if (!empty($sanitizedData['lote'])) {
						$query->orWhere('Val', 'LIKE',  '%' .$sanitizedData['lote'] . '%')->where('TagIndex', 1);
					} else if (!empty($sanitizedData['operator'])) {
						$query->orWhere('Val', 'LIKE', '%' . $sanitizedData['operator'] . '%')->where('TagIndex', 0);
					}
				});
			})
			->get();

	}

	public function getDistinctLotes($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return $connection->table('StringTable')
		->selectRaw('distinct Val')
			// ->when($sanitizedData['lote'] || $sanitizedData['operator'], function ($query) use ($sanitizedData) {
			// 	return $query->where(function ($query) use ($sanitizedData) {
			// 		if ($sanitizedData['lote']) {
			// 			$query->orWhere('Val', 'LIKE', $sanitizedData['lote'])->where('TagIndex', 1);
			// 		} else if ($sanitizedData['operator']) {
			// 			$query->orWhere('Val', $sanitizedData['operator'])->where('TagIndex', 0);
			// 		}
			// 	});
			// })
			->where('TagIndex', 1)
			->where('DateAndTime', '>', $dataMin)
			->where('DateAndTime', '<', $dataMax)
			->get();
	}

	public function getDatasMinMaxStringTableVal($val) {
		$connection = DB::connection('relatorio');
		return 	$connection->table('StringTable')
			->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
			->where('Val', 'LIKE', $val)
			->where('TagIndex', 1)
			->get();

	}


	public function getDataStringTable($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return $connection->table('StringTable')
			->select("*")
			// ->selectRaw("CONVERT(VARCHAR, DateAndTime, 20) + ':' + CONVERT(VARCHAR, Millitm) AS DateAndTime, Val, TagIndex")
			->where('DateAndTime', '>', $dataMin)
			->where('DateAndTime', '<', $dataMax)
			->get();
	}



	public function getDataFloatTable($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return 	$connection->table('FloatTable')
		// ->selectRaw("CONVERT(VARCHAR, DateAndTime, 20) + ':' + CONVERT(VARCHAR, Millitm) AS DateAndTime, Val, TagIndex")
			->select("*")
			->where('DateAndTime', '>', $dataMin)
			->where('DateAndTime', '<', $dataMax)
			->get();
	}

}
