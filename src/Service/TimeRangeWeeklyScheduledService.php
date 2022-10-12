<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Service;

use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException;
use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskLog;
use DateTime;

class TimeRangeWeeklyScheduledService
{
	private $bEnabled;
	private $sStartTime;
	private $sEndTime;
	private $sTimeLimit;
	private $aDays;

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

		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sEndTime)) {
			throw new ComplexBackgroundTaskException("Wrong format for setting 'end time' (found '$this->sEndTime')");
		}
		$dEndToday = new DateTime();
		$dEndToday->setTimeStamp($iCurrentTime);
		list($sHours, $sMinutes) = explode(':', $this->sEndTime);
		$dEndToday->setTime((int)$sHours, (int)$sMinutes);
		$iEndTimeToday = $dEndToday->getTimestamp();
		if($dEndToday<$iCurrentTime){
			//the end time is tomorrow, we have to change the day
			$dEndToday->modify('+1 days');
			$iEndTimeToday = $dEndToday->getTimestamp();
		}

		ComplexBackgroundTaskLog::Debug("End time: $this->sEndTime");
		ComplexBackgroundTaskLog::Debug("Next occurrence: $iEndTimeToday");
		ComplexBackgroundTaskLog::Debug("time limit: $this->sTimeLimit");
		ComplexBackgroundTaskLog::Debug("current time: $iCurrentTime");

		// IF FINISH next time is tomorrow TODO ????
		if ($iCurrentTime > $iEndTimeToday) {
			return $this->GetNextOccurrenceNextDay($iCurrentTime);
		} else {
			//TRY ANOTHER TIME next time is 2 seconds  later
			ComplexBackgroundTaskLog::Debug('Later'  );

			$oPlannedStart = new DateTime();
			$oPlannedStart->setTimeStamp($iCurrentTime);
			$oPlannedStart->modify('+ 2 seconds');

			return $oPlannedStart;
		}
	}

	/**
	 * @param int $iCurrentTime
	 *
	 * @return \DateTime
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 */
	public function GetNextOccurrenceNextDay(int $iCurrentTime): DateTime
	{
		ComplexBackgroundTaskLog::Debug('Next day');

		// Find the next active week day
		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $this->sStartTime)) {
			throw new ComplexBackgroundTaskException("Wrong format for setting 'start time' (found '$this->sStartTime')");
		}
		$oNow = new DateTime();
		$oNow->setTimeStamp($iCurrentTime);

		$oStartToday = clone $oNow;
		list($sHours, $sMinutes) = explode(':', $this->sStartTime);
		$oStartToday->setTime((int)$sHours, (int)$sMinutes);

		//case next time is today because start time is before midnight
		if ($oNow < $oStartToday) {
			//test if today is a valid day
			$iDay = $oNow->format('N');
			if (in_array($iDay, $this->aDays)) {
				return $oStartToday;
			}
		}

		$iNextPos = false;
		for ($iDay = $oNow->format('N'); $iDay <= 7; $iDay++) {
			$iNextPos = array_search($iDay, $this->aDays);
			if ($iNextPos !== false) {
				if (($iDay > $oNow->format('N')) || ($oNow->format('H:i') < $this->sStartTime)) {
					break;
				}
				$iNextPos = false; // necessary on sundays
			}
		}

		// 3rd - Compute the result
		//
		if ($iNextPos === false) {
			// Jump to the first day within the next week
			$iFirstDayOfWeek = $this->aDays[0];
			$iDayMove = $oNow->format('N') - $iFirstDayOfWeek;
			$oRet = clone $oNow;
			$oRet->modify('-'.$iDayMove.' days');
			$oRet->modify('+1 weeks');
		} else {
			$iNextDayOfWeek = $this->aDays[$iNextPos];
			$iMove = $iNextDayOfWeek - $oNow->format('N');
			$oRet = clone $oNow;
			$oRet->modify('+'.$iMove.' days');
		}
		list($sHours, $sMinutes) = explode(':', $this->sStartTime);
		$oRet->setTime((int)$sHours, (int)$sMinutes);

		return $oRet;
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
	 * @param string $sTimeLimit
	 */
	public function SetTimeLimit(string $sTimeLimit)
	{
		$this->sTimeLimit = $sTimeLimit;
	}

	/**
	 * @param array $aDays
	 */
	public function SetDays(array $aDays)
	{
		$this->aDays = $aDays;
	}


}