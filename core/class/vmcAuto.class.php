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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class vmcAuto extends eqLogic {
  const nAvo   = 6.02214179*pow(10, 23);             // Avogadro constant, mol-1 (NIST, CODATA 2006)
  const kBoltz = 1.3806504*pow(10, -23);             // Boltzmann constant, J K-1 (NIST, CODATA 2006)
  const kuniv  = self::nAvo * self::kBoltz; // universal gas constant J mol-1 K-1
  const MH2O   = 18.01534;                  // molar mass of water, g mol-1
  const Mdry   = 28.9644;                   // molar mass of dry air, g mol-1
  
  public static function warnings($n) {
	switch ($n)
	  case 1:
	    log::add('vmcAuto', 'error', __('Le calcul de la pression de saturation en-dessous de -50° C n\'est pas possible', __FILE__));
	    break;
      case 2:
	    log::add('vmcAuto', 'error', __('Cette humidité relative est impossible', __FILE__));
	    break;
      case 3:
	    log::add('vmcAuto', 'error', __('Ce point de rosée est impossible', __FILE__));
	    break;
      case 4:
	    log::add('vmcAuto', 'error', __('Une concentration negative n\'est pas possible', __FILE__));
	    break;
      case 5:
	    log::add('vmcAuto', 'error', __('La concentration est plus haute que la saturation', __FILE__));
	    break;
      case 6:
	    log::add('vmcAuto', 'error', __('Il n\'y a pas de température au-dessous de 0° Kelvin', __FILE__));
	    break;
      case 7:
	    log::add('vmcAuto', 'error', __('Des pressions negatives sont impossibles', __FILE__));
	    break;
  }

  public static function computeAirDensity($temperature, $pression) {
    if ($temperature < -273.15) {
      warnings(6);
	  return;
	}
    if ($pression <= 0) {
      warnings(7);
	  return;
	}
    return $pression * 100.0 / self::kuniv / ($temperature + 273.15);
  }
  
  // H2O saturation pressure from Lowe & Ficke, 1974
  public static function computeH2oSaturationPressure($temperature) {
    if ($temperature < -50) {
      warnings(1);
	  return;
	}
    $pwat = 6.107799961 + $temperature*(0.4436518521 + $temperature*(0.01428945805 + $temperature*(2.650648471*pow(10, -4) + $temperature*(3.031240396*pow(10, -6) + $temperature*(2.034080948*pow(10, -8) + $temperature*6.136820929*pow(10, -11))))));
    $pice = 6.109177956 + $temperature*(0.5034698970 + $temperature*(0.01886013408 + $temperature*(4.176223716*pow(10, -4) + $temperature*(5.824720280*pow(10, -6) + $temperature*(4.838803174*pow(10, -8) + $temperature*1.838826904*pow(10, -11))))));
    return min($pwat, $pice);
  }
  
  //public static function computeDewPoint($temperature, $ph2o) {
  //  $dewPoint = $temperature;
  //  while (computeH2oSaturationPressure($dewPoint) >= $ph2o) {
  //    $dewPoint -= 0.01;
  //  }
  //  return $dewPoint;
  }

  function c_H2O_rh($temperature, $pression, $humidity) {
    if (($humidity < 0)||($humidity > 100)) {warnings(2); return;}
    if ($temperature < -50.0) {warnings(1); return;}
    $airDensity = computeAirDensity($temperature, $pression);
    $psat = computeH2oSaturationPressure($temperature);
    $ph2o = $psat * $humidity / 100;
    if ($ph2o > $psat) {
      warnings(5);
      return;
	}
    if ($ph2o > $pression)) {
		warnings(5); 
		return;
	}
    if ($ph2o < 0.039) {
		warnings(1); 
		return;
	}
    //$dewPoint = computeDewPoint($temperature + 0.01, $ph2o);
    $vmr = $ph2o / $pression;
    //$spc = $vmr * self::MH2O / ($vmr * self::MH2O +(1.0 - $vmr) * self::Mdry);
    // form.rh_H2O.value=ausgab($humidity);
    // form.td_H2O.value=ausgab($dewPoint);
    // form.dT_H2O.value=ausgab($temperature-$dewPoint);
    // form.ppr_H2O.value=ausgab($ph2o);
    // form.vmr_H2O.value=ausgab($vmr*1000.);
    // console.log($vmr*self::MH2O*$airDensity);
    // form.conc_H2O.value=ausgab($vmr*self::MH2O*$airDensity);
    // form.mol_H2O.value=ausgab($vmr*$airDensity*nAvo*1.0e-6);
    // form.spc_H2O.value=ausgab($spc*1000.);
    // form.mmv_H2O.value=ausgab($spc/(1-$spc)*1000.);
	return $vmr * self::MH2O * $airDensity;
  }

  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  public static function cron5() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */

  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  public function toHtml($_version = 'dashboard') {}
  */

  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*     * **********************Getteur Setteur*************************** */

}

class vmcAutoCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
  }

  /*     * **********************Getteur Setteur*************************** */

}
