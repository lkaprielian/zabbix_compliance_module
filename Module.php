<?php declare(strict_types = 1);
 
namespace Modules\Compliance;
 
use APP;
use CController as CAction;
 
class Module extends \Zabbix\Core\CModule {
	/**
	 * Initialize module.
	 */

	// public function init(): void {
	// 	// Initialize main menu (CMenu class instance).
	// 	APP::Component()->get('menu.main')
	// 		->findOrAdd(_('Tools'))
	// 			->setIcon('icon-inventory')
	// 				->getSubmenu()
	// 					->insertAfter('', (new \CMenuItem(_('Compliance'))))
	// 						->findOrAdd(_('Compliance'))
	// 							->getSubmenu()
	// 								->insertAfter('', (new \CMenuItem(_('Compliance tree')))
	// 									->setAction('compliance.view')
	// 								);

		// }
	public function init(): void {
		// Initialize main menu (CMenu class instance).
		APP::Component()->get('menu.main')
			->findOrAdd(_('Tools'))
				->setIcon('icon-inventory')
					->getSubmenu()
						->insertAfter('', (new \CMenuItem(_('Compliance')))
							->setAction('compliance.view')
						);
		}

	/**
	 * Event handler, triggered before executing the action.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onBeforeAction(CAction $action): void {
	}
 
	/**
	 * Event handler, triggered on application exit.
	 *
	 * @param CAction $action  Action instance responsible for current request.
	 */
	public function onTerminate(CAction $action): void {
	}
}
?>
