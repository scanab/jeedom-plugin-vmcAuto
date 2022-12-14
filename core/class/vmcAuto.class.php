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
  public static function computeAirDensity($temperature, $pression) {
    $AVOGADRO = 6.02214179*pow(10, 23);      // Avogadro constant, mol-1 (NIST, CODATA 2006)
    $BOLTZMANN = 1.3806504*pow(10, -23);     // Boltzmann constant, J K-1 (NIST, CODATA 2006)
    $UNIVERSAL_GAZ = $AVOGADRO * $BOLTZMANN; // universal gas constant J mol-1 K-1
    if ($temperature < -273.15) {
      throw new Exception(__('Il n\'y a pas de température au-dessous de 0° Kelvin', __FILE__));
    }
    if ($pression <= 0) {
      throw new Exception(__('Des pressions negatives sont impossibles', __FILE__));
    }
    return $pression * 100.0 / $UNIVERSAL_GAZ / ($temperature + 273.15);
  }
  
  // H2O saturation pressure from Lowe & Ficke, 1974
  public static function computeH2oSaturationPressure($temperature) {
    if ($temperature < -50) {
      throw new Exception(__('Le calcul de la pression de saturation en-dessous de -50° C n\'est pas possible', __FILE__));
    }
    $pwat = 6.107799961 + $temperature*(0.4436518521 + $temperature*(0.01428945805 + $temperature*(2.650648471*pow(10, -4) + $temperature*(3.031240396*pow(10, -6) + $temperature*(2.034080948*pow(10, -8) + $temperature*6.136820929*pow(10, -11))))));
    $pice = 6.109177956 + $temperature*(0.5034698970 + $temperature*(0.01886013408 + $temperature*(4.176223716*pow(10, -4) + $temperature*(5.824720280*pow(10, -6) + $temperature*(4.838803174*pow(10, -8) + $temperature*1.838826904*pow(10, -11))))));
    return min($pwat, $pice);
  }
  
  // calcul de la concentration d'eau en g/m3 en fonction de la température, de la pression et du taux d'humidité
  public static function computeH2oConcentration($temperature, $pression, $humidity) {
    log::add('vmcAuto', 'debug', "computeH2oConcentration($temperature, $pression, $humidity)");
    $MH2O = 18.01534; // molar mass of water, g mol-1
    if (($humidity < 0) || ($humidity > 100)) {
      throw new Exception(__('Cette humidité relative est impossible', __FILE__));
    }
    if ($temperature < -50.0) {
      throw new Exception(__('Le calcul de la pression de saturation en-dessous de -50° C n\'est pas possible', __FILE__));
    }
    $airDensity = self::computeAirDensity($temperature, $pression);
    $psat = self::computeH2oSaturationPressure($temperature);
    $ph2o = $psat * $humidity / 100;
    if (($ph2o > $psat) || ($ph2o > $pression)) {
      throw new Exception(__('La concentration est plus haute que la saturation', __FILE__));
    }
    if ($ph2o < 0.039) {
      throw new Exception(__('Le calcul de la pression de saturation en-dessous de -50° C n\'est pas possible', __FILE__));
    }
    $vmr = $ph2o / $pression;
    return $vmr * $MH2O * $airDensity;
  }

  // calcul du taux d'humidité fonction de la température, de la pression et de la concentration en h2o
  public static function computeH2oHumidity($temperature, $pression, $h2oConcentration) {
    log::add('vmcAuto', 'debug', "computeH2oHumidity($temperature, $pression, $h2oConcentration)");
    $MH2O = 18.01534; // molar mass of water, g mol-1
    if ($h2oConcentration < 0) {
      throw new Exception(__('Une concentration négative n\'est pas possible', __FILE__));
    }
    if ($temperature < -50.0) {
      throw new Exception(__('Le calcul de la pression de saturation en-dessous de -50° C n\'est pas possible', __FILE__));
    }
    $airDensity = self::computeAirDensity($temperature, $pression);
    //log::add('vmcAuto', 'debug', "airDensity = $airDensity");
    $psat = self::computeH2oSaturationPressure($temperature);
    //log::add('vmcAuto', 'debug', "psat = $psat");
    $vmr = $h2oConcentration / $MH2O / $airDensity;
    //log::add('vmcAuto', 'debug', "vmr = $vmr");
    $ph2o = $vmr * $pression;
    //log::add('vmcAuto', 'debug', "ph2o = $ph2o");
    if (($ph2o > $psat) || ($ph2o > $pression)) {
      throw new Exception(__('La concentration est plus haute que la saturation', __FILE__));
    }
    if ($ph2o < 0.039) {
      throw new Exception(__('Le calcul de la pression de saturation en-dessous de -50° C n\'est pas possible', __FILE__));
    }
    return $ph2o / $psat * 100;
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
  */
  public static function cron() {
    foreach (eqLogic::byType('vmcAuto', true) as $eqLogic) {
      $autorefresh = $eqLogic->getConfiguration('autorefresh');
      if ($eqLogic->getIsEnable() == 1 && $autorefresh != '') {
        try {
          $c = new Cron\CronExpression(checkAndFixCron($autorefresh), new Cron\FieldFactory);
          if ($c->isDue()) {
            $eqLogic->calculate();
          }
        } catch (Exception $exc) {
          log::add('vmcAuto', 'error', __('Expression cron non valide pour', __FILE__) . ' ' . $eqLogic->getHumanName() . ' : ' . $autorefresh);
        }
      }
    }
  }

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
    $this->validateMandatoryCmdInConfig('cmdPressionAtmo', 'info', 'pression atmosphérique');
    $this->validateMandatoryCmdInConfig('cmdTemperatureExt', 'info', 'température extérieure');
    $this->validateMandatoryCmdInConfig('cmdTemperatureInt', 'info', 'température interieure');
    $this->validateMandatoryCmdInConfig('cmdHumidityExt', 'info', 'humidité extérieure');
    $this->validateMandatoryCmdInConfig('cmdHumidityInt', 'info', 'humidité interieure');
    $this->validateMandatoryCmdInConfig('cmdVmcOn', 'action', 'ON de la ventilation');
    $this->validateOptionalCmdInConfig('cmdVmcState', 'info', 'état de la ventilation');
    if ($this->getConfiguration('typeVmc') == 'cmdOnOff') 
      $this->validateMandatoryCmdInConfig('cmdVmcOff', 'action', 'OFF de la ventilation');
  }

  private function validateOptionalCmdInConfig($confEntry, $type, $label) {
    $conf = $this->getConfiguration($confEntry);
    if ($conf != '') {
      $cmdId = trim(str_replace('#', '', $conf));
      $cmd = cmd::byId($cmdId);
      if (!is_object($cmd)) throw new Exception(__("La commmande $label doit être une commande valide", __FILE__));
      if ($cmd->getType() != $type) throw new Exception(__("La commmande $label doit être de type $type", __FILE__));
    }
  }
  
  private function validateMandatoryCmdInConfig($confEntry, $type, $label) {
    $conf = $this->getConfiguration($confEntry);
    if ($conf != '') {
      $cmdId = trim(str_replace('#', '', $conf));
      $cmd = cmd::byId($cmdId);
      if (!is_object($cmd)) throw new Exception(__("La commmande $label doit être une commande valide", __FILE__));
      if ($cmd->getType() != $type) throw new Exception(__("La commmande $label doit être de type $type", __FILE__));
    } else {
      if (!is_object($cmd)) throw new Exception(__("La commmande $label doit être renseignée", __FILE__));
    }
  }


  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
    if ($this->getConfiguration('autorefresh') == '') {
      $this->setConfiguration('autorefresh', '* * * * *');
    }
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
    $cmd = $this->getCmd(null, 'refresh');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('refresh');
      $cmd->setName(__('Rafraichir', __FILE__));
      $cmd->setIsVisible(1);
      $cmd->setOrder(0);
	}
    $cmd->setType('action');
    $cmd->setSubType('other');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();

    $cmd = $this->getCmd(null, 'H2OconcentrationInt');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('H2OconcentrationInt');
      $cmd->setName(__('Concentration H2O intérieur', __FILE__));
      $cmd->setIsVisible(1);
	  $cmd->setUnite('g/m3');
	  $cmd->setConfiguration('historizeRound', 3);
      $cmd->setTemplate('dashboard', 'core::line');
      $cmd->setTemplate('mobile', 'core::line');
      $cmd->setOrder(5);
	}
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();

    $cmd = $this->getCmd(null, 'H2OconcentrationExt');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('H2OconcentrationExt');
      $cmd->setName(__('Concentration H2O extérieur', __FILE__));
      $cmd->setIsVisible(1);
	  $cmd->setUnite('g/m3');
	  $cmd->setConfiguration('historizeRound', 3);
      $cmd->setTemplate('dashboard', 'core::line');
      $cmd->setTemplate('mobile', 'core::line');
      $cmd->setOrder(4);
	}
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();

    $cmd = $this->getCmd(null, 'theoreticalH2OhumidityInt');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('theoreticalH2OhumidityInt');
      $cmd->setName(__('Humidité intérieure théorique accessible', __FILE__));
      $cmd->setIsVisible(1);
	  $cmd->setUnite('%');
	  $cmd->setConfiguration('historizeRound', 3);
      $cmd->setTemplate('dashboard', 'core::line');
      $cmd->setTemplate('mobile', 'core::line');
      $cmd->setOrder(6);
	}
    $cmd->setConfiguration('minValue', '0');
    $cmd->setConfiguration('maxValue', '100');
    $cmd->setType('info');
    $cmd->setSubType('numeric');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();
	
    $cmd = $this->getCmd(null, 'regulationState');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('regulationState');
      $cmd->setName(__('Régulation en cours', __FILE__));
      $cmd->setIsVisible(1);
      $cmd->setTemplate('dashboard', 'core::line');
      $cmd->setTemplate('mobile', 'core::line');
      $cmd->setOrder(7);
	}
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();
	
    $cmd = $this->getCmd(null, 'autoState');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('autoState');
      $cmd->setName(__('Etat automatisme', __FILE__));
      $cmd->setIsVisible(0);
      $cmd->setTemplate('dashboard', 'core::line');
      $cmd->setTemplate('mobile', 'core::line');
      $cmd->setOrder(3);
	}
    $cmd->setType('info');
    $cmd->setSubType('binary');
    $cmd->setEqLogic_id($this->getId());
    $cmd->save();
	
    $cmd = $this->getCmd(null, 'autoOn');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('autoOn');
      $cmd->setName(__('Activer automatisme', __FILE__));
      $cmd->setIsVisible(1);
      $cmd->setTemplate('dashboard', 'core::binarySwitch');
      $cmd->setTemplate('mobile', 'core::binarySwitch');
      $cmd->setOrder(1);
	}
    $cmd->setType('action');
    $cmd->setSubType('other');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setConfiguration('infoValue', '1');
    $cmd->setConfiguration('infoName', 'autoState');
    $cmd->setValue($this->getCmd('info', 'autoState')->getId());
    $cmd->save();
    	
    $cmd = $this->getCmd(null, 'autoOff');
    if (!is_object($cmd)) {
      $cmd = new vmcAutoCmd();
      $cmd->setLogicalId('autoOff');
      $cmd->setName(__('Désactiver automatisme', __FILE__));
      $cmd->setIsVisible(1);
      $cmd->setTemplate('dashboard', 'core::binarySwitch');
      $cmd->setTemplate('mobile', 'core::binarySwitch');
      $cmd->setOrder(2);
	}
    $cmd->setType('action');
    $cmd->setSubType('other');
    $cmd->setEqLogic_id($this->getId());
    $cmd->setConfiguration('infoValue', '0');
    $cmd->setConfiguration('infoName', 'autoState');
    $cmd->setValue($this->getCmd('info', 'autoState')->getId());
    $cmd->save();
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

  public function calculate() {
    try {
      $cExt = self::computeH2oConcentration($this->getExteriorTemperature(), $this->getAtmosphericPressure(),$this->getExteriorHumidity());
      $cmdConcentrationExt = $this->getCmd(null, 'H2OconcentrationExt');
      $cmdConcentrationExt->event($cExt);
      log::add('vmcAuto', 'debug', "concentration H2O extérieur : $cExt g/m3");
   
      $cInt = self::computeH2oConcentration($this->getInteriorTemperature(), $this->getAtmosphericPressure(), $this->getInteriorHumidity());
      $cmdConcentrationInt = $this->getCmd(null, 'H2OconcentrationInt');
      $cmdConcentrationInt->event($cInt);
      log::add('vmcAuto', 'debug', "concentration H2O intérieur : $cInt g/m3");
      
      $theoreticalH2OhumidityInt = self::computeH2oHumidity($this->getInteriorTemperature(), $this->getAtmosphericPressure(), $cExt);
      $cmdTheoreticalH2OhumidityInt = $this->getCmd(null, 'theoreticalH2OhumidityInt');
      $cmdTheoreticalH2OhumidityInt->event($theoreticalH2OhumidityInt);
      log::add('vmcAuto', 'debug', "concentration H2O intérieur théorique accessible : $theoreticalH2OhumidityInt %");
      
      $maxHumidity = $this->getConfiguration('maxHygrometry', 70);
      $minHumidity = $this->getConfiguration('minHygrometry', 40);
      $seuilDeclenchement = $this->getConfiguration('seuilDeclenchement', 5);
	  $humidityInt = $this->getInteriorHumidity();
      if ($this->isAutomatismeOn()) {
        log::add('vmcAuto', 'debug', "Automatisme is ON");
        $cmdRegulationState = $this->getCmd(null, 'regulationState');
        log::add('vmcAuto', 'debug', "humidityInt : $humidityInt / theoreticalH2OhumidityInt : $theoreticalH2OhumidityInt / maxHumidity : $maxHumidity / minHumidity : $minHumidity");
        if ($humidityInt > $maxHumidity && $theoreticalH2OhumidityInt <= ($humidityInt - $seuilDeclenchement)) {
          log::add('vmcAuto', 'debug', '$humidityInt > $maxHumidity && $theoreticalH2OhumidityInt <= ($humidityInt - $seuilDeclenchement)');
          log::add('vmcAuto', 'debug', "$humidityInt > $maxHumidity && $theoreticalH2OhumidityInt <= " . ($humidityInt - $seuilDeclenchement));
          $this->startVentilation();
          $cmdRegulationState->event(1);
        } else if ($humidityInt < $minHumidity && $theoreticalH2OhumidityInt >= ($humidityInt + $seuilDeclenchement)) {
          log::add('vmcAuto', 'debug', '$humidityInt < $minHumidity && $theoreticalH2OhumidityInt >= ($humidityInt + $seuilDeclenchement)');
          log::add('vmcAuto', 'debug', "$humidityInt < $minHumidity && $theoreticalH2OhumidityInt >= " . ($humidityInt + $seuilDeclenchement));
          $this->startVentilation();
          $cmdRegulationState->event(1);
        } else {
          if ($this->isRegulationActive()) {
            $this->stopVentilation();
          }
          $cmdRegulationState->event(0);
        }
      } else {
        log::add('vmcAuto', 'debug', "Automatisme is OFF");
        $cmdRegulationState = $this->getCmd(null, 'regulationState');
        $cmdRegulationState->event(0);
      }
    } catch (Exception $exc) {
      log::add('vmcAuto', 'error', $exc->getMessage());
    }    
  }

  public function isRegulationActive() {
    $cmdRegulationState = $this->getCmd(null, 'regulationState');
    return $this->getValueFromCmd($cmdRegulationState->getId());
  }

  public function isAutomatismeOn() {
    $cmdAutoState = $this->getCmd(null, 'autoState');
    return $this->getValueFromCmd($cmdAutoState->getId());
  }

  public function isVmcOn() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdVmcState')));
    return $this->getValueFromCmd($cmdId);
  }
  
  private function getAtmosphericPressure() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdPressionAtmo')));
    return $this->getValueFromCmd($cmdId);
  }

  private function getInteriorTemperature() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdTemperatureInt')));
    return $this->getValueFromCmd($cmdId);
  }

  private function getInteriorHumidity() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdHumidityInt')));
    return $this->getValueFromCmd($cmdId);
  }

  private function getExteriorTemperature() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdTemperatureExt')));
    return $this->getValueFromCmd($cmdId);
  }

  private function getExteriorHumidity() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdHumidityExt')));
    return $this->getValueFromCmd($cmdId);
  }

  private function getValueFromCmd($cmdId) {
    if ($cmdId == '') return false;
    $cmd = cmd::byId($cmdId);
    if (!is_object($cmd)) return false;
    return $cmd->execCmd();
  }

  public function startVentilation() {
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdVmcOn')));
    if ($cmdId == '') return false;
    $cmd = cmd::byId($cmdId);
    if (!is_object($cmd)) return false;
    if ($this->getConfiguration('typeVmc') == 'timer') {
      if (!$this->isVmcOn()) {
        log::add('vmcAuto', 'info', "Start ventilation");
      } else {
        log::add('vmcAuto', 'debug', "Relance du timer de la ventilation");
	  }
      return $cmd->execCmd();
    } else if ($this->getConfiguration('typeVmc') == 'cmdOnOff') {
      if (!$this->isVmcOn()) {
        log::add('vmcAuto', 'info', "Start ventilation");
        return $cmd->execCmd();
      } else {
        log::add('vmcAuto', 'debug', "Ventilation déja allumée");
      }
    } else {
        log::add('vmcAuto', 'error', "Type de VMC inconnue : " . $this->getConfiguration('typeVmc'));
	}
  }

  public function stopVentilation() {
    $typeVmc = $this->getConfiguration('typeVmc');
    if ($typeVmc != 'cmdOnOff') return false;
    $cmdId = trim(str_replace('#', '', $this->getConfiguration('cmdVmcOff')));
    if ($cmdId == '') return false;
    $cmd = cmd::byId($cmdId);
    if (!is_object($cmd)) return false;
    log::add('vmcAuto', 'info', "Stop ventilation");
    return $cmd->execCmd();
  }

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
    log::add('vmcAuto', 'debug', "Execute " . $this->getLogicalId() . ' on ' . $this->getEqLogic()->getHumanName());
    switch ($this->getLogicalId()) {
      case 'vmcON' :
        $eqlogic = $this->getEqLogic();
        $eqlogic->startVentilation();
        break;
      case 'vmcOFF' :
        $eqlogic = $this->getEqLogic();
        $eqlogic->stopVentilation();
        break;
      case 'refresh' :
        $eqlogic = $this->getEqLogic();
        $eqlogic->calculate();
        break;
      case 'autoOn' :
      case 'autoOff' :
        $eqlogic = $this->getEqLogic();
        $infoName = $this->getConfiguration('infoName');
        $infoValue = $this->getConfiguration('infoValue') == 1 ? 1 : 0;
        $infoCmd = $eqlogic->getCmd(null, $infoName);
        $infoCmd->event($infoValue);
        if ($eqlogic->isRegulationActive() && $this->getLogicalId() == 'autoOff') $eqlogic->stopVentilation();
        break;
      //case 'vmcState' :
      case 'H2OconcentrationInt' :
      case 'H2OconcentrationExt' :
      case 'theoreticalH2OhumidityInt' :
      case 'autoState' :
        break;
    }
  }

  /*     * **********************Getteur Setteur*************************** */

}
