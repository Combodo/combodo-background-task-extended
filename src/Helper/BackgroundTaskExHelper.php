<?php

namespace Combodo\iTop\BackgroundTaskEx\Helper;

use Exception;
use ReflectionClass;
use SetupUtils;
use utils;

class BackgroundTaskExHelper
{
	const MODULE_NAME = 'combodo-complex-background-task';


	/**
	 * to be removed when minimum compat is 3.0.0
	 *
	 * @param string $sInterface
	 * @param string $sClassNameFilter
	 *
	 * @return array
	 */
	public function GetClassesForInterface(string $sInterface, string $sClassNameFilter = ''): array
	{
		$aMatchingClasses = [];
		$aExcludedPath = ['[\\\\/]lib[\\\\/]', '[\\\\/]core[\\\\/]oql[\\\\/]', '[\\\\/]node_modules[\\\\/]', '[\\\\/]datamodels[\\\\/]', '[\\\\/]itop-oauth-client[\\\\/]', '[\\\\/]test[\\\\/]'];

		if (!utils::IsDevelopmentEnvironment()) {
			// Try to read from cache
			$aFilePath = explode("\\", $sInterface);
			$sInterfaceName = end($aFilePath);
			$sCacheFileName = utils::GetCachePath()."ImplementingInterfaces/$sInterfaceName.php";
			if (is_file($sCacheFileName)) {
				$aMatchingClasses = include $sCacheFileName;
			}
		}

		if (empty($aMatchingClasses)) {
			$aAutoloadClassMaps = [APPROOT.'lib/composer/autoload_classmap.php'];
			// guess all autoload class maps from the extensions
			$aAutoloadClassMaps = array_merge($aAutoloadClassMaps, glob(APPROOT.'env-'.utils::GetCurrentEnvironment().'/*/vendor/composer/autoload_classmap.php'));

			$aClassMap = [];
			foreach ($aAutoloadClassMaps as $sAutoloadFile) {
				$aTmpClassMap = include $sAutoloadFile;
				$aClassMap = array_merge($aClassMap, $aTmpClassMap);
			}

			// Add already loaded classes
			$aCurrentClasses = array_fill_keys(get_declared_classes(), '');
			$aClassMap = array_merge($aClassMap, $aCurrentClasses);

			foreach ($aClassMap as $sPHPClass => $sPHPFile) {
				$bSkipped = false;

				// Check if our class matches name filter, or is in an excluded path
				if ($sClassNameFilter !== '' && strpos($sPHPClass, $sClassNameFilter) === false) {
					$bSkipped = true;
				} else {
					foreach ($aExcludedPath as $sExcludedPath) {
						// Note: We use '#' as delimiters as usual '/' is often used in paths.
						if ($sExcludedPath !== '' && preg_match('#'.$sExcludedPath.'#', $sPHPFile) === 1) {
							$bSkipped = true;
							break;
						}
					}
				}

				if (!$bSkipped) {
					try {
						$oRefClass = new ReflectionClass($sPHPClass);
						if ($oRefClass->implementsInterface($sInterface) && $oRefClass->isInstantiable()) {
							$aMatchingClasses[] = $sPHPClass;
						}
					} catch (Exception $e) {
					}
				}
			}

			if (!utils::IsDevelopmentEnvironment()) {
				// Save to cache
				$sCacheContent = "<?php\n\nreturn ".var_export($aMatchingClasses, true).';';
				SetupUtils::builddir(dirname($sCacheFileName));
				file_put_contents($sCacheFileName, $sCacheContent);
			}
		}

		return $aMatchingClasses;
	}

}