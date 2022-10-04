<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowKitinerary;

use ChristophWurst\KItinerary\Adapter;
use ChristophWurst\KItinerary\Bin\BinaryAdapter;
use ChristophWurst\KItinerary\Flatpak\FlatpakAdapter;
use ChristophWurst\KItinerary\Sys\SysAdapter;
use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowKitinerary\AppInfo\Application;
use OCP\Calendar\Exceptions\CalendarException;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use UnexpectedValueException;

class Operation implements ISpecificOperation {
	private IL10N $l;
	private IURLGenerator $urlGenerator;
	private LoggerInterface $logger;
	private IManager $calendarManager;
	private ?string $userId;

	public function __construct(
		IL10N $l,
		IURLGenerator $urlGenerator,
		BinaryAdapter $binAdapter,
		FlatpakAdapter $flatpakAdapter,
		SysAdapter $sysAdapter,
		LoggerInterface $logger,
		IManager $calendarManager,
		?string $userId
	) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->binAdapter = $binAdapter;
		$this->flatpakAdapter = $flatpakAdapter;
		$this->sysAdapter = $sysAdapter;
		$this->logger = $logger;
		$this->calendarManager = $calendarManager;
		$this->userId = $userId;
	}

	private function findAvailableAdapter(): Adapter {
		if ($this->sysAdapter->isAvailable()) {
			$this->sysAdapter->setLogger($this->logger);
			return $this->sysAdapter;
		}
		if ($this->binAdapter->isAvailable()) {
			$this->binAdapter->setLogger($this->logger);
			return $this->binAdapter;
		}
		if ($this->flatpakAdapter->isAvailable()) {
			return $this->flatpakAdapter;
		}
		throw new \Exception('No kitinerary adapter is available');
	}

	public function validateOperation(string $name, array $checks, string $operation): void {
		$calendars = self::listUserCalendars($this->calendarManager, $this->userId);
		$this->logger->error('Operation is '.$operation);
		if (!isset($calendars[$operation])) {
		$this->logger->error('Not in '.print_r($calendars, true));
			throw new UnexpectedValueException($this->l->t('Please select a calendar.'));
		}
	}

	public function getDisplayName(): string {
		return $this->l->t('Kitinerary');
	}

	public function getDescription(): string {
		return $this->l->t('Convert travel documents into calendar events and inserts them in a calendar.');
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return true;
	}

	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if (!$event instanceof GenericEvent) {
			return;
		}

		if ($eventName === '\OCP\Files::postRename') {
			[, $node] = $event->getSubject();
		} else {
			$node = $event->getSubject();
		}
		/** @var Node $node */

		// '', admin, 'files', 'path/to/file.txt'
		[,, $folder,] = explode('/', $node->getPath(), 4);
		if ($folder !== 'files' || $node instanceof Folder) {
			return;
		}

		$adapter = $this->findAvailableAdapter();
		$this->logger->debug('Using adapter '.get_class($adapter));
		$this->logger->error('Using adapter '.get_class($adapter));

		$itinerary = $adapter->extractIcalFromString($node->getContent());

		$this->logger->error(
			'Analized '.$node->getPath().' size:'.strlen($node->getContent()) . ' || ' . print_r($itinerary, true)
		);
		// throw new \Exception('Analized '.$node->getPath().' size:'.strlen($node->getContent()).' result:'.print_r($itinerary, true));

		$matches = $ruleMatcher->getFlows(false);
		error_log('matches number: '.count($matches));
		foreach ($matches as $match) {
			error_log('MMMMMM ' . $match['operation']);
			if ($match['operation'] ?? false) {
				[$userUri, $calendarUri] = json_decode($match['operation']);
				$this->insertIcalEvent($userUri, $calendarUri, $node->getName(), $itinerary);
				break;
			}
		}
	}

	private function insertIcalEvent(string $userUri, string $calendarUri, string $fileName, string $icalEvent): void {
		error_log('insertIcalEvent');
		$calendar = current($this->calendarManager->getCalendarsForPrincipal($userUri, [$calendarUri]));
		error_log('insertIcalEvent:calendar name' . $calendar->getDisplayName());

		/** @var VCalendar $vCalendar */
		$vCalendar = Reader::read($icalEvent);
		/** @var VEvent $vEvent */
		$vEvent = $vCalendar->{'VEVENT'};
		$events = $vEvent->getIterator();

		$counter = 0;
		foreach ($events as $event) {
			error_log('ELEMENT:::' . get_class($event));
			unset($vCalendar->VEVENT);
			$vCalendar->add('VEVENT', $event);

			try {
				$calendar->createFromString($fileName . $counter++ . '.ics', $vCalendar->serialize());
			} catch (CalendarException $e) {
				throw $e;
			}
		}
		if (!$calendar || !($calendar instanceof ICreateFromString)) {
			throw new RuntimeException('Could not find a public writable calendar for this principal');
		}
	}

	private static function computePrincipalUri(string $userId): string {
		return 'principals/users/' . $userId;
	}

	public static function listUserCalendars(IManager $calendarManager, string $userId) {
		$userCalendars = [];
		$userUri = self::computePrincipalUri($userId);
		$calendars = $calendarManager->getCalendarsForPrincipal($userUri);
		foreach ($calendars as $calendar) {
			$value = json_encode([$userUri, $calendar->getUri()]);
			$userCalendars[$value] = $calendar->getDisplayName();
		}
		return $userCalendars;
	}

	public function getEntityId(): string {
		return File::class;
	}
}
