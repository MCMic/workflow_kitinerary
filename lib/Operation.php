<?php

declare(strict_types=1);

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
use OCA\WorkflowEngine\Entity\File as FileEntity;
use OCA\WorkflowKitinerary\Activity\Provider as ActivityProvider;
use OCA\WorkflowKitinerary\AppInfo\Application;
use OCP\Calendar\Exceptions\CalendarException;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\File;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\IManager as NotificationManager;
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
	private BinaryAdapter $binAdapter;
	private FlatpakAdapter $flatpakAdapter;
	private SysAdapter $sysAdapter;
	private LoggerInterface $logger;
	private IManager $calendarManager;
	private NotificationManager $notificationManager;
	private ?string $userId;

	public function __construct(
		IL10N $l,
		IURLGenerator $urlGenerator,
		BinaryAdapter $binAdapter,
		FlatpakAdapter $flatpakAdapter,
		SysAdapter $sysAdapter,
		LoggerInterface $logger,
		IManager $calendarManager,
		NotificationManager $notificationManager,
		?string $userId
	) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->binAdapter = $binAdapter;
		$this->flatpakAdapter = $flatpakAdapter;
		$this->sysAdapter = $sysAdapter;
		$this->logger = $logger;
		$this->calendarManager = $calendarManager;
		$this->notificationManager = $notificationManager;
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
		if ($this->userId === null) {
			throw new UnexpectedValueException($this->l->t('No user id in session'));
		}
		$calendars = self::listUserCalendars($this->calendarManager, $this->userId);
		if (!isset($calendars[$operation])) {
			throw new UnexpectedValueException($this->l->t('Please select a calendar.'));
		}
	}

	public function getDisplayName(): string {
		return $this->l->t('Kitinerary');
	}

	public function getDescription(): string {
		return $this->l->t('Convert travel documents into calendar events and inserts them into a calendar.');
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}

	public function isAvailableForScope(int $scope): bool {
		return ($scope === \OCP\WorkflowEngine\IManager::SCOPE_USER);
	}

	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if (!$event instanceof GenericEvent) {
			return;
		}

		$matches = $ruleMatcher->getFlows(false);
		$operations = [];
		foreach ($matches as $match) {
			if ($match['operation'] ?? false) {
				// Collect settings of matching rules
				$operations[] = json_decode($match['operation']);
			}
		}
		if (empty($operations)) {
			// No rule is matching, we were called for nothing
			return;
		}

		if ($eventName === '\OCP\Files::postRename') {
			/** @psalm-suppress DeprecatedMethod */
			[, $node] = $event->getSubject();
		} else {
			/** @psalm-suppress DeprecatedMethod */
			$node = $event->getSubject();
		}
		/** @var Node $node */

		// '', admin, 'files', 'path/to/file.txt'
		[,, $folder,] = explode('/', $node->getPath(), 4);
		if ($folder !== 'files' || !($node instanceof File)) {
			return;
		}

		$adapter = $this->findAvailableAdapter();
		$this->logger->debug('Using adapter '.get_class($adapter));

		$itinerary = $adapter->extractIcalFromString($node->getContent());

		foreach ($operations as [$userUri, $calendarUri]) {
			$this->insertIcalEvent($userUri, $calendarUri, $node, $itinerary);
		}
	}

	private function insertIcalEvent(string $userUri, string $calendarUri, File $file, string $icalEvent): void {
		$calendar = current($this->calendarManager->getCalendarsForPrincipal($userUri, [$calendarUri]));
		if (!$calendar || !($calendar instanceof ICreateFromString)) {
			throw new RuntimeException('Could not find a public writable calendar for this principal');
		}

		/** @var VCalendar $vCalendar */
		$vCalendar = Reader::read($icalEvent);
		/** @var VEvent $vEvent */
		$vEvent = $vCalendar->{'VEVENT'};
		$events = $vEvent->getIterator();

		foreach ($events as $event) {
			unset($vCalendar->VEVENT);
			$vCalendar->add($event);

			try {
				$eventFilename = $file->getName() . $event->UID . '.ics';
				$calendar->createFromString($eventFilename, $vCalendar->serialize());
				$this->successNotication($userUri, $calendarUri, $eventFilename, (string)($event->SUMMARY ?? $this->l->t('Untitled event')), $file);
				$this->successActivity();
			} catch (CalendarException $e) {
				throw $e;
			}
		}
	}

	private static function computePrincipalUri(string $userId): string {
		return 'principals/users/' . $userId;
	}

	private static function getUserIdFromPrincipalUri(string $userUri): string {
		return explode('/', $userUri, 3)[2];
	}

	private function successNotication(string $userUri, string $calendarUri, string $eventId, string $eventSummary, File $file): void {
		$userId = self::getUserIdFromPrincipalUri($userUri);
		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($userId)
			->setApp(Application::APP_ID)
			->setDateTime(new \DateTime())
			->setSubject('importDone', [
				'principal' => $userUri,
				'calendar' => $calendarUri,
				'summary' => $eventSummary,
				'fileId' => $file->getId(),
				'fileName' => $file->getName(),
				'filePath' => $file->getPath(),
				'eventId' => $eventId,
			])
			->setObject('import', sha1($file->getName().$eventId));
		$this->notificationManager->notify($notification);
	}

	private function successActivity(string $userUri, string $calendarUri, string $eventId, string $eventSummary, File $file): void {
		$userId = self::getUserIdFromPrincipalUri($userUri);

		$event = $this->activityManager->generateEvent();
		$event->setAffectedUser($userId)
			->setApp(Application::APP_ID)
			->setType('import')
			->setSubject(
				ActivityProvider::SUBJECT_IMPORTED,
				[
					'principal' => $userUri,
					'calendar' => $calendarUri,
					'summary' => $eventSummary,
					'fileId' => $file->getId(),
					'fileName' => $file->getName(),
					'filePath' => $file->getPath(),
					'eventId' => $eventId,
				]
			)
			->setObject();
		$this->activityManager->publish($event);
	}

	/**
	 * @return array<string, string>
	 */
	public static function listUserCalendars(IManager $calendarManager, string $userId): array {
		$userCalendars = [];
		$userUri = self::computePrincipalUri($userId);
		$calendars = $calendarManager->getCalendarsForPrincipal($userUri);
		foreach ($calendars as $calendar) {
			$value = json_encode([$userUri, $calendar->getUri()]);
			$userCalendars[$value] = $calendar->getDisplayName() ?? $calendar->getUri();
		}
		return $userCalendars;
	}

	public function getEntityId(): string {
		return FileEntity::class;
	}
}
