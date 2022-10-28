<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\WorkflowKitinerary\Activity;

use OCA\WorkflowKitinerary\AppInfo\Application;
use OCA\WorkflowKitinerary\RichObjectFactory;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class Provider implements IProvider {
	public const SUBJECT_IMPORTED = 'imported';

	protected IFactory $languageFactory;
	protected IURLGenerator $url;
	protected IManager $activityManager;
	protected IEventMerger $eventMerger;
	protected RichObjectFactory $richObjectFactory;

	public function __construct(
		IFactory $languageFactory,
		IURLGenerator $url,
		IManager $activityManager,
		IEventMerger $eventMerger,
		RichObjectFactory $richObjectFactory
	) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->eventMerger = $eventMerger;
		$this->richObjectFactory = $richObjectFactory;
	}

	/**
	 * @param string $language
	 * @throws \InvalidArgumentException
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID || $event->getType() !== 'import') {
			throw new \InvalidArgumentException();
		}

		$l = $this->languageFactory->get(Application::APP_ID, $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			$subject = $l->t('Imported {event}');
		} else {
			$subject = $l->t('Imported {event} from {file}');
		}

		if ($event->getSubject() !== self::SUBJECT_IMPORTED) {
			throw new \InvalidArgumentException();
		}

		try {
			$this->setSubjects($event, $subject);
		} catch (\Throwable $t) {
			throw new \InvalidArgumentException($t->getMessage(), 0, $t);
		}
		$event = $this->eventMerger->mergeEvents('file', $event, $previousEvent);

		return $event;
	}

	private function setSubjects(IEvent $event, string $subject): void {
		/** @var array{file:array{id:int,name:string,path:string},event:array{id:string,summary:string,calendarUri:string,type:string}} */
		$subjectParams = $event->getSubjectParameters();

		$iconName = $this->getIconNameFromType($subjectParams['event']['type']);
		$iconName .= ($this->activityManager->getRequirePNG() ? '.png' : '.svg');
		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, $iconName)));

		$parameters = [
			'file' => $this->richObjectFactory->fromFileData(
				$subjectParams['file']['id'],
				$subjectParams['file']['name'],
				trim($subjectParams['file']['path'], '/')
			),
			'event' => $this->richObjectFactory->fromEventData(
				$subjectParams['event']['id'],
				$subjectParams['event']['summary'],
				$event->getAffectedUser(),
				$subjectParams['event']['calendarUri']
			),
		];

		$event->setParsedSubject(str_replace(['{file}', '{event}'], [$parameters['file']['path'],$parameters['event']['name']], $subject))
			->setRichSubject($subject, $parameters);
	}

	private function getIconNameFromType(string $type): string {
		switch ($type) {
			case 'FlightReservation':
				return 'flight';
			case 'TrainReservation':
				return 'longdistancetrain';
			case 'BusReservation':
				return 'bus';
			case 'LodgingReservation':
				return 'go-home-symbolic';
			case 'FoodEstablishmentReservation':
				return 'foodestablishment';
			case 'RentalCarReservation':
				return 'car';
			default:
				return 'meeting-attending';
		}
	}
}
