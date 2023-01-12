<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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
namespace OCA\WorkflowKitinerary\Activity\Settings;

use OCA\WorkflowKitinerary\AppInfo\Application;
use OCP\Activity\ActivitySettings;
use OCP\IL10N;

abstract class KitineraryActivitySettings extends ActivitySettings {
	public function __construct(
		protected IL10N $l,
	) {
	}

	public function getGroupIdentifier(): string {
		return Application::APP_ID;
	}

	public function getGroupName(): string {
		return $this->l->t('Kitinerary');
	}
}
