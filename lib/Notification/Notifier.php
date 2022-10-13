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
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	protected IFactory $l10nFactory;
	protected IURLGenerator $urlGenerator;

	public function __construct(
		IFactory $l10nFactory,
		IURLGenerator $urlGenerator
	) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
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

		$notification
			->setRichSubject(
				$l->t('Imported {event}'),
				[
					'event' => [
						'type' => 'calendar-event',
						'id' => $param['eventId'],
						'name' => $param['summary'],
						'link' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute('calendar.view.index', ['objectId' => $param['eventId']])),
						//'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $param['fileId']]),
					],
				])
			->setParsedSubject(str_replace('{event}', $param['summary'], $l->t('Imported {event}')))
			->setRichMessage(
				$l->t('Successfully imported {event} from {file}'),
				[
					'event' => [
						'type' => 'calendar-event',
						'id' => $param['eventId'],
						'name' => $param['summary'],
						//'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $param['fileId']]),
					],
					'file' => [
						'type' => 'file',
						'id' => $param['fileId'],
						'name' => $param['fileName'],
						'path' => $param['filePath'],
						'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $param['fileId']]),
					],
				])
			->setParsedMessage(
				str_replace(
					['{event}', '{file}'],
					[$param['summary'], $param['fileName']],
					$l->t('Successfully imported {event} from {file}')
				)
			);

		return $notification;
	}
}
