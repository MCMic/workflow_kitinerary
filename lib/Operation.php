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
use OCP\Activity\IManager as ActivityManager;
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
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
		private BinaryAdapter $binAdapter,
		private FlatpakAdapter $flatpakAdapter,
		private SysAdapter $sysAdapter,
		private LoggerInterface $logger,
		private IManager $calendarManager,
		private NotificationManager $notificationManager,
		private ActivityManager $activityManager,
		private ?string $userId,
	) {
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
			throw new UnexpectedValueException($this->l->t('No user ID in session'));
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

		/** @var array{operation?:string}[] */
		$matches = $ruleMatcher->getFlows(false);
		$operations = [];
		foreach ($matches as $match) {
			$operation = $match['operation'] ?? false;
			if ($operation !== false && $operation !== '') {
				// Collect settings of matching rules
				$decodedJson = json_decode($operation, null, 512, JSON_THROW_ON_ERROR);
				assert(is_array($decodedJson));
				assert(is_string($decodedJson[0]));
				assert(is_string($decodedJson[1]));
				$operations[] = $decodedJson;
			}
		}

		if ($operations === []) {
			// No rule is matching, we were called for nothing
			return;
		}

		/** @psalm-suppress DeprecatedClass */
		if ($eventName === \OCP\Files::class . '::postRename') {
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

		try {
			$adapter = $this->findAvailableAdapter();
			$this->logger->debug('Using adapter ' . $adapter::class);

			$itinerary = $adapter->extractIcalFromString($node->getContent());
		} catch (\Exception $e) {
			foreach ($operations as [$userUri, $calendarUri]) {
				$this->failureNotification($userUri, $calendarUri, $node, $e->getMessage());
			}
			throw $e;
		}

		foreach ($operations as [$userUri, $calendarUri]) {
			try {
				$this->insertIcalEvent($userUri, $calendarUri, $node, $itinerary);
			} catch (\Exception $e) {
				$this->failureNotification($userUri, $calendarUri, $node, $e->getMessage());
			}
		}
	}

	private function insertIcalEvent(string $userUri, string $calendarUri, File $file, string $icalEvent): void {
		$calendar = current($this->calendarManager->getCalendarsForPrincipal($userUri, [$calendarUri]));
		if (!$calendar || !($calendar instanceof ICreateFromString)) {
			throw new RuntimeException('Could not find a public writable calendar for this principal');
		}

		/** @var VCalendar $vCalendar */
		$vCalendar = Reader::read($icalEvent);
		/** @var VEvent|null $vEvent */
		$vEvent = $vCalendar->VEVENT;
		if ($vEvent instanceof VEvent) {
			/** @var iterable<VEvent> $events */
			$events = $vEvent->getIterator();
		} else {
			throw new \Exception('No events found in file ' . $file->getPath());
		}

		foreach ($events as $event) {
			unset($vCalendar->VEVENT);
			$vCalendar->add($event);

			try {
				$eventFilename = $file->getName() . ($event->UID ?? '') . '.ics';
				$calendar->createFromString($eventFilename, $vCalendar->serialize());
				$this->successNotification($userUri, $calendarUri, $eventFilename, (string)($event->SUMMARY ?? $this->l->t('Untitled event')), $file);
				$this->successActivity($userUri, $calendarUri, $eventFilename, (string)($event->SUMMARY ?? $this->l->t('Untitled event')), $this->extractTypeFromEvent($event), $file);
			} catch (CalendarException $calendarException) {
				throw $calendarException;
			}
		}
	}

	private static function computePrincipalUri(string $userId): string {
		return 'principals/users/' . $userId;
	}

	private static function getUserIdFromPrincipalUri(string $userUri): string {
		return explode('/', $userUri, 3)[2] ?? throw new \InvalidArgumentException('Incorrect format for principal URI: ' . $userUri);
	}

	private function extractTypeFromEvent(VEvent $vEvent): string {
		$json = $vEvent->{'X-KDE-KITINERARY-RESERVATION'} ?? $vEvent->{'STRUCTURED-DATA'} ?? '[]';
		try {
			$data = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($data)) {
				throw new \JsonException('Unexpected type decoded from json in X-KDE-KITINERARY-RESERVATION or STRUCTURED-DATA');
			}
		} catch (\JsonException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			$data = [];
		}
		return (string)($data[0]['@type'] ?? 'unknown');
	}

	private function successNotification(string $userUri, string $calendarUri, string $eventId, string $eventSummary, File $file): void {
		$userId = self::getUserIdFromPrincipalUri($userUri);

		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($userId)
			->setApp(Application::APP_ID)
			->setDateTime(new \DateTime())
			->setSubject(
				'importDone',
				[
					'event' => [
						'principal' => $userUri,
						'calendarUri' => $calendarUri,
						'summary' => $eventSummary,
						'id' => $eventId,
					],
					'file' => [
						'id' => $file->getId(),
						'name' => $file->getName(),
						'path' => $file->getPath(),
					],
				]
			)
			->setObject('import', sha1($file->getName() . $eventId));
		$this->notificationManager->notify($notification);
	}

	private function failureNotification(string $userUri, string $calendarUri, File $file, string $message): void {
		//TODO translate error messages in those notifications when possible
		$userId = self::getUserIdFromPrincipalUri($userUri);

		// Send notification to user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($userId)
			->setApp(Application::APP_ID)
			->setDateTime(new \DateTime())
			->setSubject(
				'importFailed',
				[
					'event' => [
						'principal' => $userUri,
						'calendarUri' => $calendarUri,
					],
					'file' => [
						'id' => $file->getId(),
						'name' => $file->getName(),
						'path' => $file->getPath(),
					],
					'error' => [
						'message' => $message,
					],
				]
			)
			->setObject('import', sha1($file->getName() . $userUri . $calendarUri));
		$this->notificationManager->notify($notification);
	}

	private function successActivity(string $userUri, string $calendarUri, string $eventId, string $eventSummary, string $eventType, File $file): void {
		$userId = self::getUserIdFromPrincipalUri($userUri);

		$event = $this->activityManager->generateEvent();
		$event->setAffectedUser($userId)
			->setApp(Application::APP_ID)
			->setType('import')
			->setSubject(
				ActivityProvider::SUBJECT_IMPORTED,
				[
					'event' => [
						'principal' => $userUri,
						'calendarUri' => $calendarUri,
						'summary' => $eventSummary,
						'id' => $eventId,
						'type' => $eventType,
					],
					'file' => [
						'id' => $file->getId(),
						'name' => $file->getName(),
						'path' => $file->getPath(),
					],
				]
			)
			->setObject('files', $file->getId());
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
			$value = json_encode([$userUri, $calendar->getUri()], JSON_THROW_ON_ERROR);
			$userCalendars[$value] = $calendar->getDisplayName() ?? $calendar->getUri();
		}

		return $userCalendars;
	}

	public function getEntityId(): string {
		return FileEntity::class;
	}
}
