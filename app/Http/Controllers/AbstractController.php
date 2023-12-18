<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class AbstractController extends Controller {

	public function responseSuccess(array $data = null, int $code = 200, array $headers = []) {
		$response = is_null($data) ? response()->noContent() : response()->json($data, $code);

		if (!empty($headers)) {
			$response->withHeaders($headers);
		}

		return $response;
	}

	public function responseError(string $message, string $description, $type = 'UNKNOWN', array $fields = [], int $code = 400) {
		$error = [
			'error' => [
				'type' => $type,
				'message' => $message,
				'description' => $description
			]
		];

		if (!empty($fields)) {
			$error['error']['fields'] = $fields;
		}

		return response()->json($error, $code);
	}

	public function responseUnauthorized(string $message = '') {
		$message = $message ?: 'Falha na autenticação.';
		$error = [
			'error' => [
				'type' => 'UNAUTHORIZED',
				'message' => 'Não autorizado.',
				'description' => $message
			]
		];

		return response()->json($error, 401);
	}

	public function responseForbidden(string $message = '') {
		$message = $message ?: 'Você não possui permissão para acessar este recurso.';
		$error = [
			'error' => [
				'type' => 'FORBIDDEN',
				'message' => 'Você não possui permissão.',
				'description' => $message
			]
		];

		return response()->json($error, 403);
	}

	public function responseNotFound(?string $customMessage = null) {
		$error = [
			'error' => [
				'type' => 'NOT_FOUND',
				'message' => 'Não encontrado.',
				'description' => '%s Verifique a URL e os parâmetros informados e tente novamente.'
			]
		];

		$error['error']['description'] = sprintf($error['error']['description'], $customMessage ?: 'Recurso não encontrado.');

		return response()->json($error, 404);
	}
}
