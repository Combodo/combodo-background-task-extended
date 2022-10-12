<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService;

abstract class AbstractTimeRangeWeeklyScheduledProcess extends AbstractWeeklyScheduledProcess
{
	const MODULE_SETTING_MAX_TIME = 'end_time';
	const MODULE_SETTING_TIME_LIMIT = 'execution_time_limit';
	const MODULE_SETTING_EXECUTION_STEP = 'execution_step';

	/**
	 * Allowed time range start hour
	 *
	 * @return string
	 */
	protected function GetDefaultModuleSettingTime(){
		return '01:00';
	}

	/**
	 * Allowed time range end hour
	 *
	 * @return string
	 */
	protected function GetDefaultModuleSettingEndTime(){
		return '05:00';
	}

	/**
	 * Execution time limit in seconds (each step during allowed time range)
	 *
	 * @return int
	 */
	protected function GetDefaultModuleSettingTimeLimit(){
		return 30;
	}

	/**
	 * Scheduling time step (between 2 steps during allowed time range)
	 *
	 * @return int
	 */
	protected function GetDefaultModuleSettingTimeStep(){
		return 10;
	}

	/**
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 * @throws \ProcessInvalidConfigException
	 * @throws \Exception
	 */
	public function GetNextOccurrence($sCurrentTime = 'now')
	{
		$oAnonymizerService = $this->GetWeeklyScheduledService();
		$iCurrentTime = (new DateTime($sCurrentTime))->getTimestamp();
		return $oAnonymizerService->GetNextOccurrence($iCurrentTime);
	}

	/**
	 * @return \Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService
	 * @throws \ProcessInvalidConfigException
	 */
	public function GetWeeklyScheduledService(): TimeRangeWeeklyScheduledService
	{
		$oAnonymizerService = new TimeRangeWeeklyScheduledService();
		$bEnabled = MetaModel::GetConfig()->GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_ENABLED, static::DEFAULT_MODULE_SETTING_ENABLED);
		$sStartTime = MetaModel::GetConfig()->GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_TIME, $this->GetDefaultModuleSettingTime());
		$sEndTime = MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_MAX_TIME, $this->GetDefaultModuleSettingEndTime());
		$iTimeStep = MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_EXECUTION_STEP, $this->GetDefaultModuleSettingTimeStep());

		$oAnonymizerService->SetEnabled($bEnabled);
		$oAnonymizerService->SetStartTime($sStartTime);
		$oAnonymizerService->SetEndTime($sEndTime);
		$oAnonymizerService->SetAllowedRangeTimeStep($iTimeStep);
		$oAnonymizerService->SetDays($this->InterpretWeekDays());

		return $oAnonymizerService;
	}

}