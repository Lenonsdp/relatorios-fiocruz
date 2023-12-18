<?php

namespace App\Http\Controllers;

use App\Services\GetRelatorioService;
use Illuminate\Http\Request;

class GetRelatorioController extends AbstractController {

	public function __construct(private GetRelatorioService $service) {}

	public function get(Request $request) {
		$data = [
			'dateStart' => $request->input('startDate'),
			'dateEnd' => $request->input('endDate'),
			'operator' => $request->input('operator'),
			'lote' => $request->input('lote')
		];

		$relatorios = $this->service->get($data);

		// if ($this->service->hasError()) {
		// 	return $this->responseError(
		// 		'Erro ao obter os detalhes do pedido.',
		// 		'Não foi possível obter os detalhes do pedido, pois ocorreram problemas na validação ou no iFood.',
		// 		'GET_STORE_STATUS_ERROR',
		// 		$this->service->getErrors()
		// 	);
		// }

		return $this->responseSuccess((array) $relatorios);
	}
}
