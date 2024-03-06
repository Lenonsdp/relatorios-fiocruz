<?php

namespace App\Services;

use App\Models\AlarmeModel;
use App\Models\RelatorioModel;

class GetRelatorioService extends AbstractService {
	public function __construct(private RelatorioModel $relatorioModel, private AlarmeModel $alarmeModel) {}

	public function get(Array $data) {
		$data['dateStart'] = $data['dateStart'] ? $this->formatDataBD($data['dateStart']) . ' 00:00:00' : '';
		$data['dateEnd'] = $data['dateEnd'] ? $this->formatDataBD($data['dateEnd']) . ' 23:59:59' : '';
		$result = [];
		$manyLotes = false;
		$result_datas = $this->relatorioModel->getDatasMinMaxStringTable($data);
		if ($result_datas[0]->MinDate == null) {
			return [];
		}
		$result_lotes = $this->relatorioModel->getDistinctLotes($result_datas[0]->MinDate, $result_datas[0]->MaxDate);
		$count = count($result_lotes);

		if ($count > 1) {
			foreach($result_lotes as $result_lote) {
				if ($result_lote->Val == null || empty(trim($result_lote->Val))) {
					continue;
				}
				$resultMultipleLotesData = [];
				$resultMultipleLotesData = $this->relatorioModel->getDatasMinMaxStringTableVal($result_lote->Val, $result_datas[0]->MinDate, $result_datas[0]->MaxDate);
				$alias = $result_lote->Val;
				$result[$alias]['StringTable'] = $this->relatorioModel->getDataStringTable($resultMultipleLotesData[0]->MinDate, $resultMultipleLotesData[0]->MaxDate);

				$result[$alias]['FloatTable'] = $this->relatorioModel->getDataFloatTable($resultMultipleLotesData[0]->MinDate, $resultMultipleLotesData[0]->MaxDate);
				$result[$alias]['Alarme'] = $this->alarmeModel->getDataAlarmeTable($resultMultipleLotesData[0]->MinDate, $resultMultipleLotesData[0]->MaxDate);
				$result[$alias]['dataMin'] = $resultMultipleLotesData[0]->MinDate;
				$result[$alias]['dataMax'] = $resultMultipleLotesData[0]->MaxDate;
				$this->parserMultiplyLotes($result, $alias);
			}
			foreach($result as &$lote) {
				foreach($lote as &$val) {
					unset($lote['StringTable']);
					unset($lote['FloatTable']);
					unset($lote['DataIndex']);
				}
			}
		} else if (count($result_lotes) == 1) {
			$alias = $result_lotes[0]->Val;
			$result[$alias]['StringTable'] = $this->relatorioModel->getDataStringTable($result_datas[0]->MinDate, $result_datas[0]->MaxDate);

			$result[$alias]['FloatTable'] = $this->relatorioModel->getDataFloatTable($result_datas[0]->MinDate, $result_datas[0]->MaxDate);

			$result[$alias]['Alarme'] = $this->alarmeModel->getDataAlarmeTable($result_datas[0]->MinDate, $result_datas[0]->MaxDate);

			$result[$alias]['dataMin'] = $result_datas[0]->MinDate;
			$result[$alias]['dataMax'] = $result_datas[0]->MaxDate;
			$this->parserSingleLote($result, $alias);

			unset($result[$alias]['DataIndex']);
		}

		$result['manyLotesWarning'] = $manyLotes;
		return $result;
	}

	private function parserMultiplyLotes(&$result, $alias) {
		foreach($result as $lote) {
			foreach($lote['StringTable'] as $stringTable) {
				if ($stringTable->DateAndTime >= $result[$alias]['dataMin'] && $stringTable->DateAndTime <= $result[$alias]['dataMax']) {
					$result[$alias]['DataIndex'][$stringTable->DateAndTime][] = $stringTable;
				}
			}
			foreach($lote['FloatTable'] as $floatTable) {
				if ($floatTable->DateAndTime >= $result[$alias]['dataMin'] && $floatTable->DateAndTime <= $result[$alias]['dataMax']) {
					$result[$alias]['DataIndex'][$floatTable->DateAndTime][] = $floatTable;
				}
			}
		}
		if (!isset($result[$alias]['DataIndex'])) {
			return;
		}
		foreach($result[$alias]['DataIndex'] as $dataIndex) {
			foreach($dataIndex as $data) {
				if ($data->TagIndex == 4) {
					$result[$alias]['Ciclo_ok_nok'] = $data->Val;
				} else if ($data->TagIndex == 5) {
					$result[$alias]['ID_Dorna'] = $data->Val;
				} else if ($data->TagIndex == 3) {
					$result[$alias]['Nome_receita'] = $data->Val;
				} else if ($data->TagIndex == 0) {
					$result[$alias]['NomeUsuario'] = $data->Val;
				} else if ($data->TagIndex == 1) {
					$result[$alias]['Num_Lote'] = $data->Val;
				} else if ($data->TagIndex == 2) {
					$result[$alias]['Fase'][$data->Val][] = $data->DateAndTime;
				} else if ($data->TagIndex == 10) {
					$result[$alias]['PH_receita_max'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 11) {
					$result[$alias]['PH_receita_min'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 12) {
					$result[$alias]['Temperatura_receita_max'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 13) {
					$result[$alias]['Temperatura_receita_min'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 14) {
					$result[$alias]['Peso_receita'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 8) {
					$result[$alias]['Veloc_receita'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 6) {
					$result[$alias]['PH'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 7) {
					$result[$alias]['Temperatura'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 9) {
					$result[$alias]['Velocidade'][$data->DateAndTime] = $data->Val;
				}
			}
		}
	}

	private function parserSingleLote(&$result, $alias) {
		foreach($result as $lote) {
			foreach($lote['StringTable'] as $stringTable) {
				$result[$alias]['DataIndex'][$stringTable->DateAndTime][] = $stringTable;
			}
			foreach($lote['FloatTable'] as $floatTable) {
				$result[$alias]['DataIndex'][$floatTable->DateAndTime][] = $floatTable;
			}
		}

		unset($result[$alias]['StringTable']);
		unset($result[$alias]['FloatTable']);

		foreach($result[$alias]['DataIndex'] as $dataIndex) {
			foreach($dataIndex as $data) {
				if ($data->TagIndex == 4) {
					$result[$alias]['Ciclo_ok_nok'] = $data->Val;
				} else if ($data->TagIndex == 5) {
					$result[$alias]['ID_Dorna'] = $data->Val;
				} else if ($data->TagIndex == 3) {
					$result[$alias]['Nome_receita'] = $data->Val;
				} else if ($data->TagIndex == 0) {
					$result[$alias]['NomeUsuario'] = $data->Val;
				} else if ($data->TagIndex == 1) {
					$result[$alias]['Num_Lote'] = $data->Val;
				} else if ($data->TagIndex == 2) {
					$result[$alias]['Fase'][$data->Val][] = $data->DateAndTime;
				} else if ($data->TagIndex == 10) {
					$result[$alias]['PH_receita_max'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 11) {
					$result[$alias]['PH_receita_min'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 12) {
					$result[$alias]['Temperatura_receita_max'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 13) {
					$result[$alias]['Temperatura_receita_min'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 14) {
					$result[$alias]['Peso_receita'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 8) {
					$result[$alias]['Veloc_receita'][$data->DateAndTime][] = $data->Val;
				} else if ($data->TagIndex == 6) {
					$result[$alias]['PH'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 7) {
					$result[$alias]['Temperatura'][$data->DateAndTime] = $data->Val;
				} else if ($data->TagIndex == 9) {
					$result[$alias]['Velocidade'][$data->DateAndTime] = $data->Val;
				}
			}
		}
	}

	private function formatDataBD($ts) {
		if ($ts == '') {
			return '';
		}
		$tmp = $this->cvdate($ts);
		$d = getdate($tmp);
		$yr = $d["year"];
		$mo = $d["mon"];
		$da = $d["mday"];

		return sprintf("%04d-%02d-%02d", $yr, $mo, $da);
	}

	private function cvdate($s) {
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
			return $this->cvdate_portugues($d,$m,$a);
		} else {
			return $this->cvdate_numerico($d,$m,$a);
		}
	}

	private function cvdate_numerico($d,$m,$y) {
		$d2 = 0;
		$m2 = 0;
		$y2 = 0;
		$d2 = intval($d);
		$m2 = intval($m);
		$y2 = intval($y);
		if (($d2 == 0) || ($m2 == 0) || ($y2 == 0)) return 0;
		return mktime(0, 0, 0, $m2, $d2, $y2);
	}

	private function cvdate_portugues($d,$m,$y) {
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

}
