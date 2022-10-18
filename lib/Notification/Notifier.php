<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Côme Chilliet <come.chilliet@nextcloud.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
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

namespace OCA\WorkflowKitinerary\Notification;

use OCA\WorkflowKitinerary\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	protected IFactory $l10nFactory;
	protected IURLGenerator $urlGenerator;
	protected IAppManager $appManager;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $urlGenerator,
		IAppManager $appManager
	) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->appManager = $appManager;
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Kitinerary');
	}

	/**
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException('Unhandled app');
		}

		if ($notification->getSubject() === 'importDone') {
			return $this->handleImportDone($notification, $languageCode);
		}

		throw new \InvalidArgumentException('Unhandled subject');
	}

	public function handleImportDone(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$param = $notification->getSubjectParameters();

		$path = $param['filePath'];
		if (strpos($path, '/' . $notification->getUser() . '/files/') === 0) {
			// Remove /user/files/...
			$fullPath = $path;
			[,,, $path] = explode('/', $fullPath, 4);
		}
		$notification
			->setRichSubject(
				$l->t('Imported {event}'),
				[
					'event' => $this->generateRichObjectEvent(
						$param['eventId'],
						$param['summary'],
						$notification->getUser(),
						$param['calendar']
					),
				])
			->setParsedSubject(str_replace('{event}', $param['summary'], $l->t('Imported {event}')))
			->setRichMessage(
				$l->t('Successfully imported from {file}'),
				[
					'file' => [
						'type' => 'file',
						'id' => $param['fileId'],
						'name' => $param['fileName'],
						'path' => $path,
						'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $param['fileId']]),
					],
				])
			->setParsedMessage(
				str_replace(
					['{file}'],
					[$param['fileName']],
					$l->t('Successfully imported from {file}')
				)
			);

		return $notification;
	}

	/**
	 * @return array<string,string>
	 */
	protected function generateRichObjectEvent(string $id, string $name, string $owner, string $calendarUri): array {
		$object = [
			'type' => 'calendar-event',
			'id' => $id,
			'name' => $name,
		];

		if ($this->appManager->isEnabledForUser('calendar')) {
			try {
				// The calendar app needs to be manually loaded for the routes to be loaded
				\OC_App::loadApp('calendar');
				$objectId = base64_encode('/remote.php/dav/calendars/' . $owner . '/' . $calendarUri . '/' . $id);
				$link = [
					'view' => 'dayGridMonth',
					'timeRange' => 'now',
					'mode' => 'sidebar',
					'objectId' => $objectId,
					'recurrenceId' => 'next'
				];
				$object['link'] = $this->urlGenerator->linkToRouteAbsolute('calendar.view.indexview.timerange.edit', $link);
			} catch (\Exception $error) {
				// Do nothing
			}
		}
		return $object;
	}
}
