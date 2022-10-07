<?php

namespace Combodo\iTop\ComplexBackgroundTask\Helper;

use LogAPI;

class ComplexBackgroundTaskLog extends LogAPI
{
	const CHANNEL_DEFAULT = 'ComplexBackgroundTaskLog';

	protected static $m_oFileLog = null;
}