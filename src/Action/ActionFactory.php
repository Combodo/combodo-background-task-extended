<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Action;

use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException;
use DBObject;

class ActionFactory
{
	public function GetAction($sAction, DBObject $oAction, int $iEndExecutionTime)
	{
		if (is_null($sAction)) {
			return null;
		}
		if (class_exists($sAction) && isset(class_implements($sAction)[iAction::class])) {
			return new $sAction($oAction, $iEndExecutionTime);
		}
		throw new ComplexBackgroundTaskException("Class $sAction is not an Action class");
	}

}