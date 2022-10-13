<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Service;

use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException;
use DateTime;

class TimeRangeWeeklyScheduledService
{
	private $bEnabled;
	private $sStartTime;
	private $sEndTime;
	private $aDays;
	private $iAllowedRangeTimeStep;

	/**
	 * @param int $iCurrentTime
	 *
	 * @return \DateTime
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 */
	public function GetNextOccurrence($iCurrentTime)
	{
		if (!$this->bEnabled) {
			//if background process is disabled
			return new DateTime('3000-01-01');
		}

		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sStartTime)) {
			throw new ComplexBackgroundTaskException("Wrong format for setting 'start time' (found '$this->sStartTime')");
		}
		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sEndTime)) {
			throw new ComplexBackgroundTaskException("Wrong format for setting 'end time' (found '$this->sEndTime')");
		}
		$iStartTimeToday = $this->GetTodayTimeStamp($iCurrentTime, $this->sStartTime);
		$iEndTimeToday = $this->GetTodayTimeStamp($iCurrentTime, $this->sEndTime);

		$oPlannedStart = new DateTime();
		$oPlannedStart->setTimeStamp($iCurrentTime);

		if ($iStartTimeToday < $iEndTimeToday) {
			// Allowed execution time range is staring and ending the same day
			if (!$this->IsAllowedDay($iCurrentTime)) {
				// Today is not allowed, start newt allowed day
				$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime);
			} else {
				if ($iCurrentTime >= $iStartTimeToday && $iCurrentTime <= ($iEndTimeToday - $this->iAllowedRangeTimeStep)) {
					// Into the current allowed time range
					$oPlannedStart->modify("+ $this->iAllowedRangeTimeStep seconds");
				} elseif ($iCurrentTime < $iStartTimeToday) {
					// Before the allowed time range got to the start
					$oPlannedStart->setTimeStamp($iStartTimeToday);
				} else {
					// After the allowed time range, got to start next allowed day
					$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime);
				}
			}
		} else {
			// Allowed execution time range is around midnight (e.g. the start day is not the same as end day)
			if ($iCurrentTime <= ($iEndTimeToday - $this->iAllowedRangeTimeStep)) {
				// Check if yesterday was allowed
				if ($this->IsAllowedDay($iCurrentTime - 86400)) {
					// Into the current allowed time range (between midnight and end of time range)
					$oPlannedStart->modify("+ $this->iAllowedRangeTimeStep seconds");
				} else {
					// Start another allowed day
					$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime - 86400);
				}
			} else {
				if ($this->IsAllowedDay($iCurrentTime)) {
					if ($iCurrentTime < $iStartTimeToday) {
						// Before the allowed time range go to the start
						$oPlannedStart->setTimeStamp($iStartTimeToday);
					} else {
						// Into the current allowed time range (between start of time range and midnight)
						$oPlannedStart->modify("+ $this->iAllowedRangeTimeStep seconds");
					}
				} else {
					// Start another allowed day
					$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime - 86400);
				}
			}
		}

		return $oPlannedStart;
	}

	protected function GetTodayTimeStamp($iCurrentTime, $sHourMinute)
	{
		$oToday = new DateTime();
		$oToday->setTimeStamp($iCurrentTime);
		list($sHours, $sMinutes) = explode(':', $sHourMinute);
		$oToday->setTime((int)$sHours, (int)$sMinutes);

		return $oToday->getTimestamp();
	}

	/**
	 * Return the first available day from current time
	 *
	 * @param $iCurrentTime
	 *
	 * @return bool
	 */
	protected function IsAllowedDay($iCurrentTime): bool
	{
		$oCurrent = new DateTime();
		$oCurrent->setTimeStamp($iCurrentTime);
		$iDay = $oCurrent->format('N');
		return in_array($iDay, $this->aDays);
	}

	/**
	 * @param int $iCurrentTime
	 *
	 * @return \DateTime
	 */
	public function GetNextOccurrenceNextDay(int $iCurrentTime): DateTime
	{
		// Find the next active week day
		$oAvailableStart = new DateTime();
		$oAvailableStart->setTimeStamp($iCurrentTime);
		$oAvailableStart->setTime(0, 0);
		// Next day
		$oAvailableStart->modify('+1 day');

		$iNextPos = false;
		// Search same week
		for ($iDay = $oAvailableStart->format('N'); $iDay <= 7; $iDay++) {
			$iNextPos = array_search($iDay, $this->aDays);
			if ($iNextPos !== false) {
				break;
			}
		}

		if ($iNextPos === false) {
			// Jump to the first day within the next week
			$iFirstDayOfWeek = $this->aDays[0];
			$iDayMove = $oAvailableStart->format('N') - $iFirstDayOfWeek;
			$oAvailableStart->modify('-'.$iDayMove.' days');
			$oAvailableStart->modify('+1 weeks');
		} else {
			// Advance same week
			$iNextDayOfWeek = $this->aDays[$iNextPos];
			$iMove = $iNextDayOfWeek - $oAvailableStart->format('N');
			$oAvailableStart->modify('+'.$iMove.' days');
		}
		list($sHours, $sMinutes) = explode(':', $this->sStartTime);
		$oAvailableStart->setTime((int)$sHours, (int)$sMinutes);

		return $oAvailableStart;
	}

	/**
	 * @param bool $bEnabled
	 */
	public function SetEnabled(bool $bEnabled)
	{
		$this->bEnabled = $bEnabled;
	}

	/**
	 * @param string $sStartTime
	 */
	public function SetStartTime(string $sStartTime)
	{
		$this->sStartTime = $sStartTime;
	}

	/**
	 * @param string $sEndTime
	 */
	public function SetEndTime(string $sEndTime)
	{
		$this->sEndTime = $sEndTime;
	}

	/**
	 * @param array $aDays
	 */
	public function SetDays(array $aDays)
	{
		$this->aDays = $aDays;
	}

	/**
	 * @param int $iAllowedRangeTimeStep
	 */
	public function SetAllowedRangeTimeStep(int $iAllowedRangeTimeStep)
	{
		$this->iAllowedRangeTimeStep = $iAllowedRangeTimeStep;
	}


}