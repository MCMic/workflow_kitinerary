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
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null) {
		if ($event->getApp() !== Application::APP_ID || $event->getType() !== 'import') {
			throw new \InvalidArgumentException();
		}

		$l = $this->languageFactory->get(Application::APP_ID, $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			$subject = $l->t('Imported {event}');
		} else {
			$subject = $l->t('Imported {event} from {file}');
		}

		if ($event->getSubject() === self::SUBJECT_IMPORTED) {
			// TODO Change icon depending on ticket?
			if ($this->activityManager->getRequirePNG()) {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'longdistancetrain.png')));
			} else {
				$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'longdistancetrain.svg')));
			}
		} else {
			throw new \InvalidArgumentException();
		}

		$this->setSubjects($event, $subject);
		$event = $this->eventMerger->mergeEvents('file', $event, $previousEvent);

		return $event;
	}

	private function setSubjects(IEvent $event, string $subject): void {
		$subjectParams = $event->getSubjectParameters();
		// if (empty($subjectParams)) {
		// 	// Try to fall back to the old way, but this does not work for emails.
		// 	// But at least old activities still work.
		// 	$subjectParams = [
		// 		'id' => $event->getObjectId(),
		// 		'path' => $event->getObjectName(),
		// 	];
		// }
		$parameters = [
			'file' => $this->richObjectFactory->fromFileData(
				$subjectParams['fileId'],
				$subjectParams['fileName'],
				trim($subjectParams['filePath'], '/')
			),
			'event' => $this->richObjectFactory->fromEventData(
				$subjectParams['eventId'],
				$subjectParams['summary'],
				$event->getAffectedUser(),
				$subjectParams['calendar']
			),
		];

		$event->setParsedSubject(str_replace(['{file}', '{event}'], [$parameters['file']['path'],$parameters['event']['name']], $subject))
			->setRichSubject($subject, $parameters);
	}
}
