<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use Exception;
use Throwable;

class BackgroundTaskExException extends Exception
{
	public function __construct($message = '', $code = 0, Throwable $previous = null)
	{
		parent::__construct(BackgroundTaskExHelper::MODULE_NAME.': '.$message, $code, $previous);
	}
}