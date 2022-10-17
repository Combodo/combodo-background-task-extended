<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use LogAPI;

class BackgroundTaskExLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'BackgroundTaskExLog';

	protected static $m_oFileLog = null;
}