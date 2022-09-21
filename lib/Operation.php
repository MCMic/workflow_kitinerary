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

use ChristophWurst\KItinerary\Bin\BinaryAdapter;
use ChristophWurst\KItinerary\Exception\KItineraryRuntimeException;
use ChristophWurst\KItinerary\ItineraryExtractor;
use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowKitinerary\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use UnexpectedValueException;
use Psr\Log\LoggerInterface;

class Operation implements ISpecificOperation {
	public const MODES = [
		'woot',
	];

	private IL10N $l;
	private IURLGenerator $urlGenerator;
	private LoggerInterface $logger;

	public function __construct(
		IL10N $l,
		IURLGenerator $urlGenerator,
		LoggerInterface $logger
	) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
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
		//~ try {
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

		// woot?
		/*$matches = $ruleMatcher->getFlows(false);
		$originalFileMode = $targetPdfMode = null;
		foreach ($matches as $match) {
			$fileModes = explode(';', $match['operation']);
			if ($originalFileMode !== 'keep') {
				$originalFileMode = $fileModes[0];
			}
			if ($targetPdfMode !== 'preserve') {
				$targetPdfMode = $fileModes[1];
			}
			if ($originalFileMode === 'keep' && $targetPdfMode === 'preserve') {
				// most conservative setting, no need to look into other modes
				break;
			}
		}*/
		//~ if (!empty($originalFileMode) && !empty($targetPdfMode)) {
		//~ TODO extraire le ics et l’insérer dans un calendrier
		//~ 'path' => $node->getPath(),
		$adapter = new BinaryAdapter();
		$adapter->setLogger($this->logger);
		if (!$adapter->isAvailable()) {
			throw new \Exception('not available');
		}
		//~ $extractor = new ItineraryExtractor($adapter);

		$itinerary = $adapter->extractFromString($node->getContent());

		$this->logger->error('Analized '.$node->getPath().' size:'.strlen($node->getContent()));
		throw new \Exception('Analized '.$node->getPath().' size:'.strlen($node->getContent()).' result:'.print_r($itinerary, true));
// 		\OC::$server->get(\Psr\Log\LoggerInterface::class)->error(print_r($itinerary, true));
		//~ }
		//~ } catch (KItineraryRuntimeException $e) {
		//~ } catch (NotFoundException $e) {
		//~ }
	}

	public function getEntityId(): string {
		return File::class;
	}
}
