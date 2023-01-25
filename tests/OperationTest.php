<?php

declare(strict_types=1);

/**
 * @copyright 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowKitinerary\Tests\Unit;

use ChristophWurst\KItinerary\Bin\BinaryAdapter;
use ChristophWurst\KItinerary\Flatpak\FlatpakAdapter;
use ChristophWurst\KItinerary\Sys\SysAdapter;
use OCA\WorkflowKitinerary\Operation;
use OCP\Calendar\IManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\IManager as NotificationManager;
use OCP\Activity\IManager as ActivityManager;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class OperationTest extends TestCase {
	private IManager $calendarManager;
	private Operation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->calendarManager = $this->createMock(IManager::class);

		$this->operation = new Operation(
			$this->createMock(IL10N::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(BinaryAdapter::class),
			$this->createMock(FlatpakAdapter::class),
			$this->createMock(SysAdapter::class),
			$this->createMock(LoggerInterface::class),
			$this->calendarManager,
			$this->createMock(NotificationManager::class),
			$this->createMock(ActivityManager::class),
			'fakeuser',
		);
	}

	public function testValidateOperation(): void {
		$this->operation->validateOperation('name', [], '');
	}
}
