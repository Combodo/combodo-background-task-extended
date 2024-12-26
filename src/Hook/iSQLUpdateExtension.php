<?php
/**
 * @copyright   Copyright (C) 2010-2024 Combodo SAS
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\BackgroundTaskEx\Hook;

interface iSQLUpdateExtension
{
	/**
	 * Called when a class has been updated by SQL directly
	 *
	 * @param string $sClass    Datamodel class
	 * @param array $aIds       List of the Ids concerned
	 *
	 * @return void
	 */
	public function OnSQLUpdate(string $sClass, array $aIds);
}