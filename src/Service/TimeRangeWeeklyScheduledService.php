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
	/**
	 * @param bool $bEnabled
	 * @param string $sStartTime
	 * @param string $sEndTime
	 * @param string $sTimeLimit
	 * @param int $iCurrentTime
	 * @param array $aDays
	 *
	 * @return \DateTime
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 */
	public function GetNextOccurrence($bEnabled, $sStartTime, $sEndTime, $sTimeLimit, $iCurrentTime, $aDays)
	{
		if (!$bEnabled) {
			//if background process is disabled
			return new DateTime('3000-01-01');
		}

		if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $sEndTime)) {
			throw new ComplexBackgroundTaskException("Wrong format for setting 'end time' (found '$sEndTime')");
		}
		$dEndToday = new DateTime();
		$dEndToday->setTimeStamp($iCurrentTime);
		list($sHours, $sMinutes) = explode(':', $sEndTime);
		$dEndToday->setTime((int)$sHours, (int)$sMinutes);
		$iEndTimeToday = $dEndToday->getTimestamp();

		ComplexBackgroundTaskLog::Debug("End time: $sEndTime");
		ComplexBackgroundTaskLog::Debug("Next occurrence: $iEndTimeToday");
		ComplexBackgroundTaskLog::Debug("time limit: $sTimeLimit");
		ComplexBackgroundTaskLog::Debug("current time: $iCurrentTime");

		// IF FINISH next time is tomorrow TODO ????
		if ($iCurrentTime < $sTimeLimit || $iCurrentTime > $iEndTimeToday) {
			ComplexBackgroundTaskLog::Debug('Next day'  );

			// Find the next active week day
			if (!preg_match('/[0-2][0-9]:[0-5][0-9]/', $sStartTime)) {
				throw new ComplexBackgroundTaskException("Wrong format for setting 'start time' (found '$sStartTime')");
			}
			$oNow = new DateTime();
			$oNow->setTimeStamp($iCurrentTime);
			$iNextPos = false;
			for ($iDay = $oNow->format('N'); $iDay <= 7; $iDay++) {
				$iNextPos = array_search($iDay, $aDays);
				if ($iNextPos !== false) {
					if (($iDay > $oNow->format('N')) || ($oNow->format('H:i') < $sStartTime)) {
						break;
					}
					$iNextPos = false; // necessary on sundays
				}
			}

			// 3rd - Compute the result
			//
			if ($iNextPos === false) {
				// Jump to the first day within the next week
				$iFirstDayOfWeek = $aDays[0];
				$iDayMove = $oNow->format('N') - $iFirstDayOfWeek;
				$oRet = clone $oNow;
				$oRet->modify('-'.$iDayMove.' days');
				$oRet->modify('+1 weeks');
			} else {
				$iNextDayOfWeek = $aDays[$iNextPos];
				$iMove = $iNextDayOfWeek - $oNow->format('N');
				$oRet = clone $oNow;
				$oRet->modify('+'.$iMove.' days');
			}
			list($sHours, $sMinutes) = explode(':', $sStartTime);
			$oRet->setTime((int)$sHours, (int)$sMinutes);
			return $oRet;
		} else {
			//TRY ANOTHER TIME next time is 2 seconds  later
			ComplexBackgroundTaskLog::Debug('Later'  );

			$oPlannedStart = new DateTime();
			$oPlannedStart->setTimeStamp($iCurrentTime);
			$oPlannedStart->modify('+ 2 seconds');

			return $oPlannedStart;
		}
	}
}