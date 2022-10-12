<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Test\Service;

use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskLog;
use Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService;
use Combodo\iTop\Test\UnitTest\ItopTestCase;
use DateTime;

class TimeRangeWeeklyScheduledServiceTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once(__DIR__.'/../../vendor/autoload.php');
		date_default_timezone_set('UTC');
		$this->TEST_LOG_FILE = APPROOT.'log/test.log';
		ComplexBackgroundTaskLog::Enable($this->TEST_LOG_FILE);
		@unlink($this->TEST_LOG_FILE);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		if (file_exists($this->TEST_LOG_FILE)) {
			$sLogs = file_get_contents($this->TEST_LOG_FILE);
			$this->debug($sLogs);
		}
	}

	/**
	 * @dataProvider GetNextOccurrenceProvider
	 *
	 * @param $sExpected
	 * @param $bEnabled
	 * @param $sStartTime
	 * @param $sEndTime
	 * @param $sTimeLimit
	 * @param $sCurrentTime
	 * @param $aDays
	 *
	 * @return void
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 */
	public function testGetNextOccurrence($sExpected, $bEnabled, $sStartTime, $sEndTime, $sCurrentTime, $aDays)
	{
		$oAnonymizerService = new TimeRangeWeeklyScheduledService();
		$iCurrentTime = date_format(date_create($sCurrentTime), 'U');
		$oExpected = new DateTime($sExpected);
		$oAnonymizerService->SetEnabled($bEnabled);
		$oAnonymizerService->SetStartTime($sStartTime);
		$oAnonymizerService->SetEndTime($sEndTime);
		$oAnonymizerService->SetDays($aDays);
		$oAnonymizerService->SetAllowedRangeTimeStep(10);
		$this->assertEquals($oExpected, $oAnonymizerService->GetNextOccurrence($iCurrentTime));
	}

	public function GetNextOccurrenceProvider()
	{
		// day 1 is monday - 7 is sunday
		// 2022-10-07 is a friday
		return [
			'disabled' => ['3000-01-01', false, '00:30', '05:30', '2022-10-07 17:00', [1, 2, 3, 4, 5, 6, 7]],

			// Range 1 start and end of time range is the same day
			'range 1 before start time open day' => ['2022-10-07 00:30:00', true, '00:30', '05:30', '2022-10-07 00:15', [1, 2, 3, 4, 5, 6, 7]],
			'range 1 after end time open day'    => ['2022-10-08 00:30:00', true, '00:30', '05:30', '2022-10-07 17:00', [1, 2, 3, 4, 5, 6, 7]],
			'range 1 in time range open day'     => ['2022-10-07 01:30:10', true, '00:30', '05:30', '2022-10-07 01:30', [1, 2, 3, 4, 5, 6, 7]],

			// Next execution sunday 9th
			'range 1 before start time closed day same week' => ['2022-10-09 00:30:00', true, '00:30', '05:30', '2022-10-07 00:15', [1, 3, 4, 7]],
			'range 1 after end time closed day same week'    => ['2022-10-09 00:30:00', true, '00:30', '05:30', '2022-10-07 17:00', [1, 3, 4, 7]],
			'range 1 in time range closed day same week'     => ['2022-10-09 00:30:00', true, '00:30', '05:30', '2022-10-07 01:30', [1, 3, 4, 7]],

			// Next execution tuesday 11th
			'range 1 before start time closed day next week' => ['2022-10-11 00:30:00', true, '00:30', '05:30', '2022-10-07 00:15', [2, 3, 4]],
			'range 1 after end time closed day next week'    => ['2022-10-11 00:30:00', true, '00:30', '05:30', '2022-10-07 17:00', [2, 3, 4]],
			'range 1 in time range closed day next week'     => ['2022-10-11 00:30:00', true, '00:30', '05:30', '2022-10-07 01:30', [2, 3, 4]],

			// Range 2 starts one day and finish next day
			'range 2 before start time open day' => ['2022-10-07 23:30:00', true, '23:30', '05:30', '2022-10-07 17:00', [1, 2, 3, 4, 5, 6, 7]],
			'range 2 before end time open day'   => ['2022-10-07 03:00:10', true, '23:30', '05:30', '2022-10-07 03:00', [1, 2, 3, 4, 5, 6, 7]],
			'range 2 after start time open day'  => ['2022-10-07 23:50:10', true, '23:30', '05:30', '2022-10-07 23:50', [1, 2, 3, 4, 5, 6, 7]],

			// Next execution sunday 9th
			'range 2 before start time open previous closed current day same week' => ['2022-10-09 23:30:00', true, '23:30', '05:30', '2022-10-07 17:00', [1, 3, 4, 7]],
			'range 2 before end time open previous closed current day same week'   => ['2022-10-07 03:00:10', true, '23:30', '05:30', '2022-10-07 03:00', [1, 3, 4, 7]],
			'range 2 before end time closed previous closed current day same week' => ['2022-10-09 23:30:00', true, '23:30', '05:30', '2022-10-07 03:00', [1, 3, 7]],
			'range 2 before end time closed previous open current day same week'   => ['2022-10-09 23:30:00', true, '23:30', '05:30', '2022-10-07 03:00', [1, 3, 5, 7]],
			'range 2 after start time open previous closed current day same week'  => ['2022-10-09 23:30:00', true, '23:30', '05:30', '2022-10-07 23:50', [1, 3, 4, 7]],
			'range 2 after start time closed previous closed current day same week'=> ['2022-10-09 23:30:00', true, '23:30', '05:30', '2022-10-07 23:50', [1, 3, 7]],

			// Next execution sunday 11th
			'range 2 before start time open previous closed current day next week' => ['2022-10-11 23:30:00', true, '23:30', '05:30', '2022-10-07 17:00', [2, 3, 4]],
			'range 2 before end time open previous closed current day next week'   => ['2022-10-07 03:00:10', true, '23:30', '05:30', '2022-10-07 03:00', [2, 3, 4]],
			'range 2 before end time closed previous closed current day next week' => ['2022-10-11 23:30:00', true, '23:30', '05:30', '2022-10-07 03:00', [2, 3]],
			'range 2 before end time closed previous open current day next week'   => ['2022-10-11 23:30:00', true, '23:30', '05:30', '2022-10-07 03:00', [2, 3, 5]],
			'range 2 after start time open previous closed current day next week'  => ['2022-10-11 23:30:00', true, '23:30', '05:30', '2022-10-07 23:50', [2, 3, 4]],
			'range 2 after start time closed previous closed current day next week'=> ['2022-10-11 23:30:00', true, '23:30', '05:30', '2022-10-07 23:50', [2, 3]],
		];
	}
}
