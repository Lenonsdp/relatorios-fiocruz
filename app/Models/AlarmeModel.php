<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AlarmeModel {

	public function getDataAlarmeTable($dataMin, $dataMax) {
		$connection = DB::connection('alarme');

		// Subtract 3 hours from $dataMin and $dataMax
		$dataMin = now()->parse($dataMin)->subHours(3);
		$dataMax = now()->parse($dataMax)->subHours(3);

		return $connection->table('AllEvent')
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
			->whereBetween('EventTimeStamp', [$dataMin, $dataMax])
			->where('Message', 'LIKE', 'Alarme:%')
			->get();
	}
}
