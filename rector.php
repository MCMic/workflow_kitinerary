<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->paths([
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	]);

	// register a single rule
	$rectorConfig->rule(RemoveUnusedPromotedPropertyRector::class);
	$rectorConfig->rule(SimplifyEmptyCheckOnEmptyArrayRector::class);
	$rectorConfig->rule(LongArrayToShortArrayRector::class);

	// define sets of rules
	$rectorConfig->sets([
		LevelSetList::UP_TO_PHP_80,
		// SetList::CODE_QUALITY,
		SetList::CODING_STYLE,
		SetList::TYPE_DECLARATION,
		// SetList::DEAD_CODE,
	]);
};
