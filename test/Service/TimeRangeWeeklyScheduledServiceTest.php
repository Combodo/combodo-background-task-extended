<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Test\Service;

use Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskLog;
use Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use DateTime;

class TimeRangeWeeklyScheduledServiceTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
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
	 * @param $dExpected
	 * @param $bEnabled
	 * @param $sStartTime
	 * @param $sEndTime
	 * @param $sTimeLimit
	 * @param $iCurrentTime
	 * @param $aDays
	 *
	 * @return void
	 * @throws \Combodo\iTop\ComplexBackgroundTask\Helper\ComplexBackgroundTaskException
	 */
	public function testGetNextOccurrence($dExpected, $bEnabled, $sStartTime, $sEndTime, $sTimeLimit, $iCurrentTime, $aDays)
	{
		$oAnonymizerService = new TimeRangeWeeklyScheduledService();
		$this->assertEquals($dExpected, $oAnonymizerService->GetNextOccurrence($bEnabled, $sStartTime, $sEndTime, $sTimeLimit, $iCurrentTime, $aDays));
	}

	public function GetNextOccurrenceProvider()
	{
		return [
			'disabled' => [new DateTime('3000-01-01'), false, '00:30', '05:30', 1700000000, 1664464807, [1,2,3,4,5,6,7]],
			'next 2s' => [new DateTime('3000-01-01'), false, '00:30', '05:30', 1700000000, 1664464807, [1,2,3,4,5,6,7]],
		];
	}
}
