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
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use Psr\Container\ContainerInterface;
use OCP\AppFramework\Services\IInitialState;

class RegisterFlowOperationsListener implements IEventListener {
	private ContainerInterface $container;
	private IUserSession $userSession;
	private IInitialState $initialState;

	public function __construct(
		ContainerInterface $container,
		IUserSession $userSession,
		IInitialState $initialState
	) {
		$this->container = $container;
		$this->userSession = $userSession;
		$this->initialState = $initialState;
	}

	public function handle(Event $event): void {
		if (!$event instanceof RegisterOperationsEvent) {
			return;
		}
		$event->registerOperation($this->container->get(Operation::class));

		$this->initialState->provideInitialState('userCalendars', ['id1' => 'label']);

		Util::addScript(Application::APP_ID, 'workflow_kitinerary-flow');
	}
}
