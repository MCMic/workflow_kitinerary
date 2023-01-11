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

namespace OCA\WorkflowKitinerary;

use OCP\App\IAppManager;
use OCP\IURLGenerator;

/**
 * This class contains helpers to generate the rich object arrays with correct keys and links.
 * It should ideally be merged upstream in nextcloud/server, with an interface in OCP.
 * Methods are named fromTypeData so that we can add later fromTypeObject to directly take an event or file object as parameter.
 */
class RichObjectFactory {
	public function __construct(
		protected IURLGenerator $urlGenerator,
		protected IAppManager $appManager,
	) {
	}

	/**
	 * @return array{type:'file',id:string,name:string,path:string,link:string}
	 */
	public function fromFileData(int $id, string $name, string $path): array {
		return [
			'type' => 'file',
			'id' => (string)$id,
			'name' => $name,
			'path' => $path,
			'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}

	/**
	 * @return array{type:'calendar-event',id:string,name:string,link?:string}
	 */
	public function fromEventData(string $id, string $name, string $ownerUid, string $calendarUri): array {
		$object = [
			'type' => 'calendar-event',
			'id' => $id,
			'name' => $name,
		];

		if ($this->appManager->isEnabledForUser('calendar')) {
			try {
				// The calendar app needs to be manually loaded for the routes to be loaded
				/** @psalm-suppress UndefinedClass */
				\OC_App::loadApp('calendar');
				$objectId = base64_encode('/remote.php/dav/calendars/' . $ownerUid . '/' . $calendarUri . '/' . $id);
				$link = [
					'view' => 'dayGridMonth',
					'timeRange' => 'now',
					'mode' => 'sidebar',
					'objectId' => $objectId,
					'recurrenceId' => 'next'
				];
				$object['link'] = $this->urlGenerator->linkToRouteAbsolute('calendar.view.indexview.timerange.edit', $link);
			} catch (\Exception) {
				// Do nothing
			}
		}
		return $object;
	}
}
