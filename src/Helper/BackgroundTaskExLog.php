<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use IssueLog;
use LogAPI;

class BackgroundTaskExLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'BackgroundTaskExLog';
	const DEBUG_FILE = APPROOT.'log/background-task-extended.log';

	protected static $m_oFileLog = null;

	public static function Error($sMessage, $sChannel = null, $aContext = array())
	{
		parent::Debug($sMessage, $sChannel, $aContext);
		IssueLog::Error("ERROR: $sMessage", self::CHANNEL_DEFAULT, $aContext);
	}

	public static function Info($sMessage, $sChannel = null, $aContext = array())
	{
		parent::Debug($sMessage, $sChannel, $aContext);
		IssueLog::Info($sMessage, self::CHANNEL_DEFAULT, $aContext);
	}

	public static function Warning($sMessage, $sChannel = null, $aContext = array())
	{
		parent::Debug($sMessage, $sChannel, $aContext);
		IssueLog::Warning($sMessage, self::CHANNEL_DEFAULT, $aContext);
	}

}