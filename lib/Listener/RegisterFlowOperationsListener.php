<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\WorkflowKitinerary\Listener;

use OCA\WorkflowKitinerary\AppInfo\Application;
use OCA\WorkflowKitinerary\Operation;
use OCP\AppFramework\Services\IInitialState;
use OCP\Calendar\IManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use Psr\Container\ContainerInterface;

/**
 * @template-implements IEventListener<RegisterOperationsEvent>
 */
class RegisterFlowOperationsListener implements IEventListener {
	public function __construct(
		private ContainerInterface $container,
		private IInitialState $initialState,
		private IManager $calendarManager,
		private ?string $userId,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterOperationsEvent) {
			return;
		}

		if ($this->userId === null) {
			return;
		}

		$event->registerOperation($this->container->get(Operation::class));

		$this->initialState->provideInitialState('userCalendars', Operation::listUserCalendars($this->calendarManager, $this->userId));

		Util::addScript(Application::APP_ID, 'workflow_kitinerary-flow');
	}
}
