<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Workflow_DocToPdf\AppInfo;

use OC\Files\Filesystem;
use OCA\Workflow_DocToPdf\StorageWrapper;
use OCP\Files\Storage\IStorage;
use OCP\Util;

class Application extends \OCP\AppFramework\App {

	/**
	 * Application constructor.
	 */
	public function __construct() {
		parent::__construct('workflow_doctopdf');
	}

	/**
	 * Register the app to several events
	 */
	public function registerHooksAndListeners() {
		Util::connectHook('OC_Filesystem', 'preSetup', $this, 'addStorageWrapper');
	}

	/**
	 * @internal
	 */
	public function addStorageWrapper() {
		// Needs to be added as the first layer
		Filesystem::addStorageWrapper('workflow_doctopdf', [$this, 'addStorageWrapperCallback'], -1);
	}

	/**
	 * @internal
	 * @param $mountPoint
	 * @param IStorage $storage
	 * @return StorageWrapper|IStorage
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function addStorageWrapperCallback($mountPoint, IStorage $storage) {
		if (!\OC::$CLI && !$storage->instanceOfStorage('OCA\Files_Sharing\SharedStorage')) {
			$operation = $this->getContainer()->query('OCA\Workflow_DocToPdf\Operation');

			return new StorageWrapper([
				'storage' => $storage,
				'operation' => $operation,
				'mountPoint' => $mountPoint,
			]);
		}

		return $storage;
	}

	/**
	 * Wrapper for type hinting
	 *
	 * @return \OCA\Workflow_DocToPdf\ConverterPlugin
	 * @throws \OCP\AppFramework\QueryException
	 */
	protected function getPlugin() {
		return $this->getContainer()->query(\OCA\Workflow_DocToPdf\ConverterPlugin::class);
	}
}
