<?php

namespace Combodo\iTop\ComplexBackgroundTask\Helper;

use Exception;
use Throwable;

class ComplexBackgroundTaskException extends Exception
{
	public function __construct($message = '', $code = 0, Throwable $previous = null)
	{
		parent::__construct(ComplexBackgroundTaskHelper::MODULE_NAME.': '.$message, $code, $previous);
	}
}