<?php

/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Service;

use Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException;
use DateTime;

class TimeRangeWeeklyScheduledService
{
	private $bEnabled;
	private $sStartTime;
	private $sEndTime;
	private $aDays;

	public const WEEK_DAY_TO_N = [
		'monday'    => 1,
		'tuesday'   => 2,
		'wednesday' => 3,
		'thursday'  => 4,
		'friday'    => 5,
		'saturday'  => 6,
		'sunday'    => 7,
	];

	/**
	 * @param int $iCurrentTime
	 *
	 * @return \DateTime
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 */
	public function GetNextOccurrence($iCurrentTime)
	{
		if (!$this->bEnabled) {
			//if background process is disabled
			return new DateTime('3000-01-01');
		}

		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sStartTime)) {
			throw new BackgroundTaskExException("Wrong format for setting 'start time' (found '$this->sStartTime')");
		}
		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sEndTime)) {
			throw new BackgroundTaskExException("Wrong format for setting 'end time' (found '$this->sEndTime')");
		}
		$iStartTimeToday = $this->GetTodayTimeStamp($iCurrentTime, $this->sStartTime);
		$iEndTimeToday = $this->GetTodayTimeStamp($iCurrentTime, $this->sEndTime);

		$oPlannedStart = new DateTime();
		$oPlannedStart->setTimeStamp($iCurrentTime);

		if ($iStartTimeToday < $iEndTimeToday) {
			// Allowed execution time range is staring and ending the same day
			//         00:00     start     end       00:00
			// ----------+---------[========]----------+--------
			//
			if (!$this->IsAllowedDay($iCurrentTime)) {
				// Today is not allowed, start new allowed day
				$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime);
			} else {
				if ($iCurrentTime >= $iStartTimeToday && $iCurrentTime <= $iEndTimeToday) {
					// Into the current allowed time range
					//         00:00     start     end       00:00
					// ----------+---------[========]----------+--------
					//                        ^
				} elseif ($iCurrentTime < $iStartTimeToday) {
					// Before the allowed time range got to the start
					//         00:00     start     end       00:00
					// ----------+---------[========]----------+--------
					//               ^>>>>>
					$oPlannedStart->setTimeStamp($iStartTimeToday);
				} else {
					// After the allowed time range, got to start next allowed day
					//        start     end       00:00      start     end       00:00
					// ---------[========]----------+----------[========]----------+--------
					//                          ^>>>>>>>>>>>>>>
					$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime);
				}
			}
		} else {
			// Allowed execution time range is around midnight (e.g. the start day is not the same as end day)
			//       start  00:00   end
			// --------[======+=====]---------
			//
			if ($iCurrentTime <= $iEndTimeToday) {
				// Before the end of the allowed time range
				//       start  00:00   end
				// --------[======+=====]---------
				//                   ^

				// Check if yesterday was allowed
				if (!$this->IsAllowedDay($iCurrentTime - 86400)) {
					// Start another allowed day. Go back one day to find the next allowed day (may be today)
					$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime - 86400);
				}
			} else {
				if ($this->IsAllowedDay($iCurrentTime)) {
					if ($iCurrentTime < $iStartTimeToday) {
						// Before the allowed time range go to the start
						//       start  00:00   end
						// --------[======+=====]---------
						//   ^>>>>>
						$oPlannedStart->setTimeStamp($iStartTimeToday);
					} else {
						// Into the current allowed time range (between start of time range and midnight)
						//       start  00:00   end
						// --------[======+=====]---------
						//           ^
					}
				} else {
					// Start another allowed day
					$oPlannedStart = $this->GetNextOccurrenceNextDay($iCurrentTime);
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
	 * Interpret current setting for the week days
	 *
	 * @param string $sWeekDays
	 *
	 * @return array of int (1 = monday)
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 */
	public function WeekDaysToNumeric(string $sWeekDays)
	{
		$aDays = [];

		if ($sWeekDays !== '') {
			$aWeekDaysRaw = explode(',', $sWeekDays);
			foreach ($aWeekDaysRaw as $sWeekDay) {
				$sWeekDay = strtolower(trim($sWeekDay));
				if (array_key_exists($sWeekDay, static::WEEK_DAY_TO_N)) {
					$aDays[] = static::WEEK_DAY_TO_N[$sWeekDay];
				} else {
					throw new BackgroundTaskExException("Wrong format for week days (found '$sWeekDay')");
				}
			}
		}
		if (count($aDays) === 0) {
			throw new BackgroundTaskExException('No day selected');
		}
		$aDays = array_unique($aDays);
		sort($aDays);

		return $aDays;
	}

	/**
	 * @param $aDays array of int (1 = monday)
	 *
	 * @return string
	 * @throws \Combodo\iTop\BackgroundTaskEx\Helper\BackgroundTaskExException
	 */
	public function WeekDaysToString($aDays)
	{
		$aWeekDays = [];
		foreach (static::WEEK_DAY_TO_N as $sDay => $iDay) {
			if (in_array($iDay, $aDays)) {
				$aWeekDays[] = $sDay;
			} else {
				throw new BackgroundTaskExException("Wrong format for days list found: ".var_export($aDays, true));
			}
		}

		if (count($aWeekDays) == 0) {
			throw new BackgroundTaskExException('No day selected');
		}

		return implode(', ', $aWeekDays);
	}
}
