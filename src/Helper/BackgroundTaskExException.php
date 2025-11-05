<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use Exception;
use Throwable;

class BackgroundTaskExException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $aContext = [])
    {
        if (!is_null($previous)) {
            $sStack = $previous->getTraceAsString();
            $sError = $previous->getMessage();
        } else {
            $sStack = $this->getTraceAsString();
            $sError = '';
        }

        $aContext['error'] = $sError;
        $aContext['stack'] = $sStack;
        BackgroundTaskExLog::Error($message, null, $aContext);
        parent::__construct($message, $code, $previous);
    }
}