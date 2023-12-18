<?php

namespace App\Services;

use App\Errors\ErrorCollectorTrait;

abstract class AbstractService {
	use ErrorCollectorTrait;
}
