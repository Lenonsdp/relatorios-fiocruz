<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AlarmeModel {

	public function getDataAlarmeTable($dataMin, $dataMax) {
		$connection = DB::connection('alarme');
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
