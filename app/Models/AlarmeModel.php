<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AlarmeModel {

	public function getDataAlarmeTable($dataMin, $dataMax) {
		$connection = DB::connection('alarme');

		$dataMin = now()->parse($dataMin)->addHours(3)->addSeconds(5);
		$dataMax = now()->parse($dataMax)->addHours(3)->addSeconds(5);

		return $connection->table('AllEvent')
			->select(
				'ServerName',
				 DB::raw('DATEADD(HOUR, -3, EventTimeStamp) as EventTimeStamp'),
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
