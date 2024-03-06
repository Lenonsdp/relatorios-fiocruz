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
						$query->orWhere('Val', $sanitizedData['lote'])->where('TagIndex', 1);
					} else if (!empty($sanitizedData['operator'])) {
						$query->orWhere('Val', $sanitizedData['operator'])->where('TagIndex', 0);
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

	public function getDatasMinMaxStringTableVal($val, $dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return 	$connection->table('StringTable')
			->selectRaw('MIN(DateAndTime) as MinDate, MAX(DateAndTime) as MaxDate')
			->where('Val', 'LIKE', $val)
			->where('DateAndTime', '>=', $dataMin)
			->where('DateAndTime', '<=', $dataMax)
			->where('TagIndex', 1)
			->get();

	}


	public function getDataStringTable($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return $connection->table('StringTable')
			->select("*")
			->where('DateAndTime', '>=', $dataMin)
			->where('DateAndTime', '<=', $dataMax)
			->get();
	}



	public function getDataFloatTable($dataMin, $dataMax) {
		$connection = DB::connection('relatorio');
		return 	$connection->table('FloatTable')
			->select("*")
			->where('DateAndTime', '>=', $dataMin)
			->where('DateAndTime', '<=', $dataMax)
			->get();
	}

}
