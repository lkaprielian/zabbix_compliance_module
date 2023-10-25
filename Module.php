<?php declare(strict_types = 1);
 
namespace Modules\Compliance;
 
use APP;
use CController as CAction;
 
class Module extends \Zabbix\Core\CModule {
	/**
	 * Initialize module.
	 */

	public function init(): void {
		// Initialize main menu (CMenu class instance).
		APP::Component()->get('menu.main')
			->findOrAdd(_('Monitoring'))
				->getSubmenu()
					->insertAfter('Discovery', (new \CMenuItem(_('Compliances'))))
						->findOrAdd(_('Compliances'))
							->getSubmenu()
								->insertAfter('', (new \CMenuItem(_('compliance tree')))
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
