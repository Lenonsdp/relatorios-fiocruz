<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AlarmeModel {

	public function getDataAlarmeTable($dataMin, $dataMax) {
		$connection = DB::connection('alarme');

		$dataMin = now()->parse($dataMin)->addHours(3);
		$dataMax = now()->parse($dataMax)->addHours(3);

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
