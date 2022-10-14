<?php
/*
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */


use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskHelper;
use Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService;

abstract class AbstractTimeRangeWeeklyScheduledProcess extends AbstractWeeklyScheduledProcess
{
	const MODULE_SETTING_MAX_TIME = 'end_time';

	protected function GetModuleName()
	{
		return ComplexBackgroundTaskHelper::MODULE_NAME;
	}

	/**
	 * Allowed time range start hour
	 *
	 * @return string
	 */
	protected function GetDefaultModuleSettingTime(){
		return MetaModel::GetConfig()->GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_TIME, '01:30');
	}

	/**
	 * Allowed time range end hour
	 *
	 * @return string
	 */
	protected function GetDefaultModuleSettingEndTime(){
		return MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_MAX_TIME, '05:00');
	}

	/**
	 * @param string $sCurrentTime date formatted
	 *
	 * @return \DateTime
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 * @throws \ProcessInvalidConfigException
	 */
	public function GetNextOccurrence($sCurrentTime = 'now')
	{
		$oService = $this->GetWeeklyScheduledService();
		$iCurrentTime = (new DateTime($sCurrentTime))->getTimestamp();
		return $oService->GetNextOccurrence($iCurrentTime);
	}

	/**
	 * @return \Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService
	 * @throws \ProcessInvalidConfigException
	 */
	public function GetWeeklyScheduledService(): TimeRangeWeeklyScheduledService
	{
		$Service = new TimeRangeWeeklyScheduledService();
		$bEnabled = MetaModel::GetConfig()->GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_ENABLED, static::DEFAULT_MODULE_SETTING_ENABLED);
		$sStartTime = MetaModel::GetConfig()->GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_TIME, $this->GetDefaultModuleSettingTime());
		$sEndTime = MetaModel::GetModuleSetting($this->GetModuleName(), static::MODULE_SETTING_MAX_TIME, $this->GetDefaultModuleSettingEndTime());

		$Service->SetEnabled($bEnabled);
		$Service->SetStartTime($sStartTime);
		$Service->SetEndTime($sEndTime);
		$Service->SetDays($this->InterpretWeekDays());

		return $Service;
	}

}