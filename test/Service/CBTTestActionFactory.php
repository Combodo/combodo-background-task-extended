<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Test\Service;

use Combodo\iTop\ComplexBackgroundTask\Action\ActionFactory;
use DBObject;

class CBTTestActionFactory extends ActionFactory
{
	private $aParamsArray = [];
	private $iCurrAction;

	/**
	 * @param array $aParamsArray
	 */
	public function __construct(array $aParamsArray)
	{
		$this->aParamsArray = $aParamsArray;
		$this->iCurrAction = 0;
	}

	public function GetAction($sAction, DBObject $oAction, int $iEndExecutionTime)
	{
		$oAction = parent::GetAction($sAction, $oAction, $iEndExecutionTime);
		if ($oAction && method_exists($oAction, 'SetParams')) {
			$oAction->SetParams($this->aParamsArray[$this->iCurrAction]);
			if (isset($this->aParamsArray[$this->iCurrAction+1])) {
				$this->iCurrAction++;
			}
		}
		return $oAction;
	}

}