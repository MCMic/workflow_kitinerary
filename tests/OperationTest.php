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
use OCP\Activity\IManager as ActivityManager;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\File;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\IManager as NotificationManager;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OperationTest extends TestCase {
	/** @var IL10N&MockObject */
	private IL10N|MockObject $l;
	/** @var IManager&MockObject */
	private IManager|MockObject $calendarManager;
	private Operation $operation;
	/** @var ICreateFromString&MockObject */
	private ICreateFromString|MockObject $calendar;
	/** @var FlatpakAdapter&MockObject */
	private FlatpakAdapter|MockObject $flatpakAdapter;

	protected function setUp(): void {
		parent::setUp();

		$this->calendarManager = $this->createMock(IManager::class);
		$this->l = $this->createMock(IL10N::class);

		$this->l->method('t')
			->willReturnArgument(0);

		$this->calendar = $this->createMock(ICreateFromString::class);
		$this->calendar->method('getUri')
			->willReturn('uri');

		$this->calendarManager->method('getCalendarsForPrincipal')
			->with('principals/users/fakeuser')
			->willReturn([$this->calendar]);

		$this->flatpakAdapter = $this->createMock(FlatpakAdapter::class);

		$binaryAdapter = $this->createMock(BinaryAdapter::class);
		$binaryAdapter->method('isAvailable')
			->willReturn(false);

		$sysAdapter = $this->createMock(SysAdapter::class);
		$sysAdapter->method('isAvailable')
			->willReturn(false);

		$this->operation = new Operation(
			$this->l,
			$this->createMock(IURLGenerator::class),
			$binaryAdapter,
			$this->flatpakAdapter,
			$sysAdapter,
			$this->createMock(LoggerInterface::class),
			$this->calendarManager,
			$this->createMock(NotificationManager::class),
			$this->createMock(ActivityManager::class),
			'fakeuser',
		);
	}

	public function testValidateOperation(): void {
		$this->calendarManager->expects(self::once())->method('getCalendarsForPrincipal');
		$this->operation->validateOperation('name', [], json_encode(['principals/users/fakeuser','uri']));
	}

	public static function dataOnEvent(): array {
		return [
			[
				'/fakeuser/files/path/to/file.pdf',
				file_get_contents(__DIR__ . '/documents/iata-bcbp-demo.pdf'),
				file_get_contents(__DIR__ . '/documents/iata-bcbp-demo.ics'),
			],
		];
	}

	/**
	 * @dataProvider dataOnEvent
	 */
	public function testOnEvent(string $path, string $content, string $icalContent): void {
		/** @psalm-suppress DeprecatedClass */
		$eventName = \OCP\Files::class . '::postRename';

		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects(self::once())->method('getFlows')
			->willReturn([['operation' => json_encode(['principals/users/fakeuser','uri'])]]);

		$node = $this->createMock(File::class);
		$node->expects(self::atLeastOnce())->method('getId')
			->willReturn(12);
		$node->expects(self::exactly(3))->method('getPath')
			->willReturn($path);
		$node->expects(self::once())->method('getContent')
			->willReturn($content);
		$node->expects(self::atLeastOnce())->method('getName')
			->willReturn('filename.pdf');

		/** @psalm-suppress DeprecatedClass */
		$event = $this->createMock(GenericEvent::class);
		$event->expects(self::once())->method('getSubject')
			->willReturn(['', $node]);

		$this->flatpakAdapter->expects(self::once())->method('isAvailable')
			->willReturn(true);

		$this->flatpakAdapter->expects(self::once())->method('extractIcalFromString')
			->with($content)
			->willReturn($icalContent);

		$this->calendar->expects(self::once())
			->method('createFromString')
			->with(
				'filename.pdfKIT-9b586afa-432d-4c9e-a320-c45474c7f7de.ics',
				self::stringContains('X-KDE-KITINERARY-RESERVATION')
			);

		$this->operation->onEvent($eventName, $event, $ruleMatcher);
	}
}
