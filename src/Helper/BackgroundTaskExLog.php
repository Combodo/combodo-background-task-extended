<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use LogAPI;

class BackgroundTaskExLog extends LogAPI
{
	public const CHANNEL_DEFAULT = 'BackgroundTaskEx';
    protected static $m_oFileLog = null;

    public const DEBUG_FILE = APPROOT.'log/error.log';

    public static function Enable($sTargetFile = null)
    {
        if (empty($sTargetFile)) {
            $sTargetFile = APPROOT.'log/error.log';
        }
        parent::Enable($sTargetFile);
    }
}