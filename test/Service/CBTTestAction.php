<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Test\Service;

use Combodo\iTop\ComplexBackgroundTask\Action\iAction;
use DBObject;
use Exception;

class CBTTestAction implements iAction
{
	private $aParams = [];
	private $oTask;

	/**
	 * @inheritDoc
	 */
	public function __construct(DBObject $oTask, $iEndExecutionTime)
	{
		$this->oTask = $oTask;
		$this->aParams = [
			'Init' => 'Task1 init',
			'Retry' => 'Task1 retry',
			'Execute' => 'Task1 execute',
			'ExecReturn' => true,
		];
	}

	/**
	 * @param array $aParams
	 */
	public function SetParams(array $aParams)
	{
		$this->aParams = $aParams;
	}

	/**
	 * @inheritDoc
	 */
	public function Init()
	{
		if (isset($this->aParams['Init'])) {
			$sValue = $this->oTask->Get('action_params').' - '.$this->aParams['Init'];
			$this->oTask->Set('action_params', $sValue);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function Retry()
	{
		if (isset($this->aParams['Retry'])) {
			$sValue = $this->oTask->Get('action_params').' - '.$this->aParams['Retry'];
			$this->oTask->Set('action_params', $sValue);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function Execute(): bool
	{
		if (isset($this->aParams['Execute'])) {
			$sValue = $this->oTask->Get('action_params').' - '.$this->aParams['Execute'];
			$this->oTask->Set('action_params', $sValue);
		}
		if (isset($this->aParams['ExecReturn'])) {
			if ($this->aParams['ExecReturn'] === 'Exception') {
				throw new Exception('Test Exception');
			}
			return $this->aParams['ExecReturn'];
		}

		return true;
	}
}

class CBTTestAction2 extends CBTTestAction
{}