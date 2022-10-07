<?php
/**
 * @copyright   Copyright (C) 2010-2022 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\ComplexBackgroundTask\Action;

use DBObject;

interface iAction
{
	/**
	 * @param \DBObject $oTask
	 * @param int $iEndExecutionTime Unix timestamp of the end of execution
	 */
	public function __construct(DBObject $oTask, $iEndExecutionTime);

	/**
	 * Initialize the action parameters and save into the $oTask object (action_params).
	 */
	public function Init();

	/**
	 * Last execution failed, change the parameters in order to retry and save into the $oTask object (action_params).
	 */
	public function Retry();

	/**
	 * Execute the action using the parameters (action_params)
	 * When execution stops after execution timeout, store the parameters for the next execution in the $oTask object.
	 *
	 * @return bool true when action is finished, false if paused on execution timeout
	 */
	public function Execute(): bool;
}