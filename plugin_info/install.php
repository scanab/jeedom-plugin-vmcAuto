<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

// Fonction exécutée automatiquement après l'installation du plugin
function vmcAuto_install() {
}

// Fonction exécutée automatiquement après la mise à jour du plugin
function vmcAuto_update() {
	foreach (eqLogic::byType('vmcAuto') as $eqLogic) {
		// renommage de de la configuration 'typeVmcOff' en 'typeVmc'
		if (renameConfiguration($eqLogic, 'typeVmcOff', 'typeVmc')) {
			$eqLogic->save();
		}
		// correction des valeurs de configuration 'typeVmc'
		if ($eqLogic->getConfiguration('typeVmc') == 'cmd') {
			$eqLogic->setConfiguration('typeVmc', 'cmdOnOff');
			$eqLogic->save();
		}
	}
}

// Fonction exécutée automatiquement après la suppression du plugin
function vmcAuto_remove() {
}

function renameConfiguration($eqLogic, $from, $to) {
	if ($eqLogic->getConfiguration($from) != '') {
		if ($eqLogic->getConfiguration($to) == '') {
			log::add('vmcAuto', 'info', $eqLogic->getHumanName() . " - Rennomage de la configuration $from en $to");
			$eqLogic->setConfiguration($to, $eqLogic->getConfiguration($from));
			$eqLogic->setConfiguration($from, null);
			return true;
		} else {
			log::add('vmcAuto', 'debug', $eqLogic->getHumanName() . " - impossible de rennomer la configuration $from en $to : la cible existe déja");
		}
	} else {
		log::add('vmcAuto', 'debug', $eqLogic->getHumanName() . " - impossible de rennomer la configuration $from en $to : la source est inexistante");
	}
	return false;
}
