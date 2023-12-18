<?php
namespace App\Errors;

use App\Errors\ErrorObj;
use Exception;

trait ErrorCollectorTrait {
	protected $errors = [];
	protected $warnings = [];

	public function clearErrors(): void {
		$this->errors = [];
	}

	public function clearWarnings(): void {
		$this->warnings = [];
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function getWarnings(): array {
		return $this->warnings;
	}

	public function setError(array $error, ...$replaceTexts): void {
		$this->validateErrorStructure($error);
		$this->warnIfDivergingTextsToPlaceholdersCount($error, $replaceTexts);
		$message = !empty($replaceTexts) ? sprintf($error['message'], ...$replaceTexts) : $error['message'];
		$this->errors[] = new ErrorObj($error['code'], $message, $error['element'], $error['namespace']);
	}

	public function setWarning(array $error, ...$replaceTexts): void {
		$this->validateErrorStructure($error);
		$this->warnIfDivergingTextsToPlaceholdersCount($error, $replaceTexts);
		$message = !empty($replaceTexts) ? sprintf($error['message'], ...$replaceTexts) : $error['message'];
		$this->warnings[] = new ErrorObj($error['code'], $message, $error['element'], $error['namespace']);
	}

	public function hasError(): bool {
		return !empty($this->getErrors());
	}

	public function hasWarning(): bool {
		return !empty($this->getWarnings());
	}

	public function parseErrorObjs(array $objs): array {
		return array_map(function($errorObj) {
			return ['message' => $errorObj->getMsg()];
		}, $objs);
	}

	private function validateErrorStructure(array $error): void {
		$validationError = false;
		$errorStructure = ['code', 'message', 'element', 'namespace'];
		$structureDiff = array_merge(
			array_diff($errorStructure, array_keys($error)),
			array_diff(array_keys($error), $errorStructure)
		);
		$validationError = !empty($structureDiff);

		if (!$validationError) {
			$typeStructure = ['integer', 'string', 'string', 'string'];
			$errorTypes = [
				gettype($error['code']),
				gettype($error['message']),
				gettype($error['element']),
				gettype($error['namespace'])
			];
			$typeStructureDiff = array_merge(
				array_diff_assoc($typeStructure, $errorTypes),
				array_diff_assoc($errorTypes, $typeStructure)
			);
			$validationError = !empty($typeStructureDiff);
		}

		if ($validationError) {
			throw new Exception('An invalid error structure has been passed to AbstractService. Expected: Array (\'code\' => (int), \'message\' => (string), \'element\' => (string), \'namespace\' => (string)). Given: ' . print_r($error, true));
		}
	}

	private function warnIfDivergingTextsToPlaceholdersCount($error, $replaceTexts) {
		$countPlaceholders = substr_count($error['message'], '%s');

		if ($countPlaceholders != count($replaceTexts)) {
			throw new Exception('Number of replace arguments in the error text differs from the number of placeholders in the message');
		}
	}

	protected function transferErrorsFrom(object $instance): void {
		$this->errors = array_merge($this->errors, $instance->getErrors());
		$this->warnings = array_merge($this->warnings, $instance->getWarnings());
	}

}
