<?php

declare(strict_types=1);

use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/appinfo',
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	])
	->withPhpSets(php81: true)
	->withTypeCoverageLevel(1)
	->withSets([
		NextcloudSets::NEXTCLOUD_27,
	]);
