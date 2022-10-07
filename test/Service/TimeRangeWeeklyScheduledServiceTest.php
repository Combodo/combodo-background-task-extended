<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Test\Service;

use Combodo\iTop\ComplexBackgroundTask\Service\TimeRangeWeeklyScheduledService;
use DateTime;
use PHPUnit\Framework\TestCase;

class TimeRangeWeeklyScheduledServiceTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		date_default_timezone_set('UTC');
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
	 * @throws \Combodo\iTop\BackgroundTask\Helper\BackgroundTaskException
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
