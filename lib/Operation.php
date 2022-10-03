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
use ChristophWurst\KItinerary\Exception\KItineraryRuntimeException;
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
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use Psr\Log\LoggerInterface;
use RuntimeException;
use UnexpectedValueException;

class Operation implements ISpecificOperation {
	public const MODES = [
		'woot',
	];

	private IL10N $l;
	private IURLGenerator $urlGenerator;
	private LoggerInterface $logger;
	private IManager $calendarManager;
	private IUserSession $userSession;

	public function __construct(
		IL10N $l,
		IURLGenerator $urlGenerator,
		BinaryAdapter $binAdapter,
		FlatpakAdapter $flatpakAdapter,
		SysAdapter $sysAdapter,
		LoggerInterface $logger,
		IManager $calendarManager,
		IUserSession $userSession
	) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->binAdapter = $binAdapter;
		$this->flatpakAdapter = $flatpakAdapter;
		$this->sysAdapter = $sysAdapter;
		$this->logger = $logger;
		$this->calendarManager = $calendarManager;
		$this->userSession = $userSession;
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
		/*if (!in_array($operation, Operation::MODES)) {
			throw new UnexpectedValueException($this->l->t('Please choose a mode.'));
		}*/
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

		$this->logger->error('Analized '.$node->getPath().' size:'.strlen($node->getContent()));
		// throw new \Exception('Analized '.$node->getPath().' size:'.strlen($node->getContent()).' result:'.print_r($itinerary, true));

		$matches = $ruleMatcher->getFlows();
		$calendarId = null;
		foreach ($matches as $match) {
			if ($match['operation'] ?? false) {
				$calendarUri = $match['operation'];
				break;
			}
		}

		$this->insertIcalEvent($calendarUri, $node->getName(), $itinerary);
	}

	private function insertIcalEvent(string $calendarUri, string $fileName, string $icalEvent): void {
		// TODO add configuration for which calendar to use
		// TODO get the user that added the workflow, not the one that uploaded the file
		$user = $this->userSession->getUser();
		$calendar = current($this->calendarManager->getCalendarsForPrincipal($this->computePrincipalUri($user)));
		if (!$calendar || !($calendar instanceof ICreateFromString)) {
			throw new RuntimeException('Could not find a public writable calendar for this principal');
		}

		try {
			$calendar->createFromString($fileName . '.ics', $icalEvent);
		} catch (CalendarException $e) {
			throw $e;
		}
	}

	public function getEntityId(): string {
		return File::class;
	}
}
