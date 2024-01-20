<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RelatorioModel {

	public function getDatasMinMaxStringTable($data) {
		$connection = DB::connection('relatorio');
		$sanitizedData = array_map('htmlspecialchars', $data);

		// return '%' . $sanitizedData['operator'] . '%';
		return $connection->table('StringTable')
		->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
			->when($sanitizedData['dateStart'] && $sanitizedData['dateEnd'], function ($query) use ($sanitizedData) {
				return $query->whereBetween('DateAndTime', [$sanitizedData['dateStart'], $sanitizedData['dateEnd']]);
			})
			->when($sanitizedData['lote'] || $sanitizedData['operator'], function ($query) use ($sanitizedData) {
				return $query->where(function ($query) use ($sanitizedData) {
					if (!empty($sanitizedData['lote'])) {
						$query->orWhere('Val', 'LIKE',  '%' .$sanitizedData['lote'] . '%')->where('TagIndex', 8);
					} else if (!empty($sanitizedData['operator'])) {
						$query->orWhere('Val', 'LIKE', '%' . $sanitizedData['operator'] . '%')->where('TagIndex', 7);
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
			// 			$query->orWhere('Val', 'LIKE', $sanitizedData['lote'])->where('TagIndex', 8);
			// 		} else if ($sanitizedData['operator']) {
			// 			$query->orWhere('Val', $sanitizedData['operator'])->where('TagIndex', 7);
			// 		}
			// 	});
			// })
			->where('TagIndex', 8)
			->where('DateAndTime', '>', $dataMin)
			->where('DateAndTime', '<', $dataMax)
			->get();
	}

	public function getDatasMinMaxStringTableVal($val) {
		$connection = DB::connection('relatorio');
		return 	$connection->table('StringTable')
			->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
			->where('Val', 'LIKE', $val)
			->where('TagIndex', 8)
			->get();

	}


	public function getDataStringTable($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return $connection->table('StringTable')
			->selectRaw("CONVERT(VARCHAR, DateAndTime, 20) + ':' + CONVERT(VARCHAR, Millitm) AS DateAndTime, Val, TagIndex")
			->where('DateAndTime', '>', $dataMin)
			->where('DateAndTime', '<', $dataMax)
			->get();
	}



	public function getDataFloatTable($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return 	$connection->table('FloatTable')
		->selectRaw("CONVERT(VARCHAR, DateAndTime, 20) + ':' + CONVERT(VARCHAR, Millitm) AS DateAndTime, Val, TagIndex")
			->where('DateAndTime', '>', $dataMin)
			->where('DateAndTime', '<', $dataMax)
			->get();
	}

}
