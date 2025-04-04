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
use OCA\WorkflowKitinerary\RichObjectFactory;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\InvalidValueException;
use OCP\Notification\UnknownNotificationException;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {
	public function __construct(
		protected IFactory $l10nFactory,
		protected RichObjectFactory $richObjectFactory,
		private LoggerInterface $logger,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Kitinerary');
	}

	/**
	 * @throws UnknownNotificationException When the notification was not prepared by a notifier
	 * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException('Unhandled app');
		}

		if ($notification->getSubject() === 'importDone') {
			try {
				return $this->handleImportDone($notification, $languageCode);
			} catch (\Throwable $throwable) {
				$this->logger->error(
					'Ignoring broken notification',
					[
						'exception' => $throwable,
						'parameters' => $notification->getSubjectParameters(),
					]
				);
				throw new AlreadyProcessedException();
			}
		} elseif ($notification->getSubject() === 'importFailed') {
			try {
				return $this->handleImportFailed($notification, $languageCode);
			} catch (\Throwable $throwable) {
				$this->logger->error(
					'Ignoring broken notification',
					[
						'exception' => $throwable,
						'parameters' => $notification->getSubjectParameters(),
					]
				);
				throw new AlreadyProcessedException();
			}
		}

		throw new UnknownNotificationException('Unhandled subject');
	}

	/**
	 * @throws InvalidValueException
	 */
	public function handleImportDone(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		/** @var array{file:array{id:int,name:string,path:string},event:array{id:string,summary:string,calendarUri:string}} */
		$subjectParams = $notification->getSubjectParameters();

		$path = $subjectParams['file']['path'];
		if (str_starts_with($path, '/' . $notification->getUser() . '/files/')) {
			// Remove /user/files/...
			$fullPath = $path;
			[,,, $path] = explode('/', $fullPath, 4);
		}

		$notification
			->setRichSubject(
				$l->t('Imported {event}'),
				[
					'event' => $this->richObjectFactory->fromEventData(
						$subjectParams['event']['id'],
						$subjectParams['event']['summary'],
						$notification->getUser(),
						$subjectParams['event']['calendarUri']
					),
				])
			->setRichMessage(
				$l->t('Successfully imported from {file}'),
				[
					'file' => $this->richObjectFactory->fromFileData(
						$subjectParams['file']['id'],
						$subjectParams['file']['name'],
						$path
					),
				]);

		return $notification;
	}

	/**
	 * @throws InvalidValueException
	 */
	public function handleImportFailed(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		/** @var array{file:array{id:int,name:string,path:string},event:array{calendarUri:string},error:array{message:string}} */
		$subjectParams = $notification->getSubjectParameters();

		$path = $subjectParams['file']['path'];
		if (str_starts_with($path, '/' . $notification->getUser() . '/files/')) {
			// Remove /user/files/...
			$fullPath = $path;
			[,,, $path] = explode('/', $fullPath, 4);
		}

		$notification
			->setRichSubject($l->t('Failed to import events'))
			->setRichMessage(
				$l->t('Failed to import events from {file}: {error}'),
				[
					'file' => $this->richObjectFactory->fromFileData(
						$subjectParams['file']['id'],
						$subjectParams['file']['name'],
						$path,
					),
					'error' => $this->richObjectFactory->fromHighlightData(
						'error_message',
						$subjectParams['error']['message'],
					),
				]);

		return $notification;
	}
}
