<?php

use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExLog;

/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */
class MockTestAction extends ComplexBackgroundTaskAction
{
	private $aParams;
	private $oTask;

	public static function Init()
	{
		$aParams = [
			'category' => '',
			'key_type' => 'autoincrement',
			'name_attcode' => ['name'],
			'state_attcode' => '',
			'reconc_keys' => [],
			'db_table' => 'priv_complex_background_task_action_cbt_test',
			'db_key_field' => 'id',
			'db_finalclass_field' => 'finalclass',
		];
		MetaModel::Init_Params($aParams);
		MetaModel::Init_InheritAttributes();

		MetaModel::Init_SetZListItems('list', [
			0 => 'name',
			1 => 'rank',
		]);
	}

	/**
	 * @param array $aParams
	 */
	public function SetParams(array $aParams)
	{
		$this->aParams = $aParams;
	}

	/**
	 * @param mixed $oTask
	 */
	public function SetTask($oTask)
	{
		$this->oTask = $oTask;
	}

	/**
	 * @inheritDoc
	 */
	public function InitActionParams()
	{
		BackgroundTaskExLog::Info('InitActionParams called');
		if (isset($this->aParams['Init'])) {
			$sValue = $this->oTask->Get('action_params').' - '.$this->aParams['Init'];
			$this->oTask->Set('action_params', $sValue);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function ChangeActionParamsOnError(): bool
	{
		BackgroundTaskExLog::Info('ChangeActionParamsOnError called');
		if (isset($this->aParams['Retry'])) {
			$sValue = $this->oTask->Get('action_params').' - '.$this->aParams['Retry'];
			$this->oTask->Set('action_params', $sValue);
			return true;
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function ExecuteAction($iEndExecutionTime): bool
	{
		BackgroundTaskExLog::Info('ExecuteAction called');
		if (isset($this->aParams['Execute'])) {
			$sValue = $this->oTask->Get('action_params').' - '.$this->aParams['Execute'];
			$this->oTask->Set('action_params', $sValue);
		}
		if (isset($this->aParams['ExecReturn'])) {
			if ($this->aParams['ExecReturn'] === true || $this->aParams['ExecReturn'] === false) {
				return $this->aParams['ExecReturn'];
			}
			throw new $this->aParams['ExecReturn']('Test Exception');
		}

		return true;
	}
}
