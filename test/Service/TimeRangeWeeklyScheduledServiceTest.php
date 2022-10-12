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
		require_once (__DIR__.'/../../vendor/autoload.php');
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
	public function testGetNextOccurrence($sExpected, $bEnabled, $sStartTime, $sEndTime, $sTimeLimit, $sCurrentTime, $aDays)
	{
		$oAnonymizerService = new TimeRangeWeeklyScheduledService();
		$iTimeLimit = date_format(date_create( $sTimeLimit), 'U');
		$iCurrentTime = date_format(date_create($sCurrentTime), 'U');
		$oExpected = new DateTime($sExpected);
		$oAnonymizerService->SetEnabled($bEnabled);
		$oAnonymizerService->SetStartTime($sStartTime);
		$oAnonymizerService->SetEndTime($sEndTime);
		$oAnonymizerService->SetTimeLimit($iTimeLimit);
		$oAnonymizerService->SetDays($aDays);
		$this->assertEquals($oExpected, $oAnonymizerService->GetNextOccurrence($iCurrentTime));
	}

	public function GetNextOccurrenceProvider()
	{
		// 2022-10-07 is a friday
		return [
			'disabled' => ['3000-01-01', false, '00:30', '05:30', '2022-10-07 17:30', '2022-10-07 17:00', [1,2,3,4,5,6,7]],
			'next day' => ['2022-10-08T00:30:00', true, '00:30', '05:30', '2022-10-07 17:30', '2022-10-07 17:00', [1,2,3,4,5,6,7]],
		];
	}
}
