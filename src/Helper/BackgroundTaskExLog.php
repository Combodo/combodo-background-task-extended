<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use LogAPI;

class BackgroundTaskExLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'BackgroundTaskEx';
    protected static $m_oFileLog = null;

    public static function Enable($sTargetFile = null)
    {
        if (empty($sTargetFile)) {
            $sTargetFile = APPROOT.'log/error.log';
        }
        parent::Enable($sTargetFile);
    }
}