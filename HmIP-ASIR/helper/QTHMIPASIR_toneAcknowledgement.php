<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/HmIP-ASIR
 */

declare(strict_types=1);

trait QTHMIPASIR_toneAcknowledgement
{
    public function ExecuteToneAcknowledgement(int $AcousticSignal, bool $UseSwitchingDelay): bool
    {
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
            return $result;
        }
        // Alarm siren
        $alarmSirenResult = true;
        $id = $this->ReadPropertyInteger('AlarmSiren');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('AlarmSirenSwitchingDelay'));
            }
            $actualAcousticSignal = $this->GetValue('AcousticSignal');
            $this->SetValue('AcousticSignal', $AcousticSignal);
            $acousticSignalList = [0 => 'Batterie leer', 1 => 'Unscharf', 2 => 'Intern Scharf', 3 => 'Extern Scharf', 4 => 'Intern verzögert Scharf', 5 => 'Extern verzögert Scharf', 6 => 'Ereignis', 7 => 'Fehler'];
            $acousticSignalName = 'Wert nicht vorhanden!';
            if (array_key_exists($AcousticSignal, $acousticSignalList)) {
                $acousticSignalName = $acousticSignalList[$AcousticSignal];
            }
            switch ($AcousticSignal) {
                case 0: # Low battery
                    $acousticAlarmSelection = 10;
                    break;

                case 1: # Disarmed
                    $acousticAlarmSelection = 11;
                    break;

                case 2: # Internally armed
                    $acousticAlarmSelection = 12;
                    break;

                case 3: # Externally armed
                    $acousticAlarmSelection = 13;
                    break;

                case 4: # Delayed internally armed
                    $acousticAlarmSelection = 14;
                    break;

                case 5: # Delayed externally armed
                    $acousticAlarmSelection = 15;
                    break;

                case 6: # Event
                    $acousticAlarmSelection = 16;
                    break;

                case 7: # Error
                    $acousticAlarmSelection = 17;
                    break;

                default:
                    return false;
            }
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . $AcousticSignal . ' - ' . $acousticSignalName, 0);
            $parameter1 = @HM_WriteValueInteger($id, 'ACOUSTIC_ALARM_SELECTION', $acousticAlarmSelection);
            $parameter2 = @HM_WriteValueInteger($id, 'OPTICAL_ALARM_SELECTION', 0);
            $parameter3 = @HM_WriteValueInteger($id, 'DURATION_UNIT', 0);
            $parameter4 = @HM_WriteValueInteger($id, 'DURATION_VALUE', 5);
            if (!$parameter1 || !$parameter2 || !$parameter3 || !$parameter4) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $parameter1 = @HM_WriteValueInteger($id, 'ACOUSTIC_ALARM_SELECTION', $AcousticSignal);
                $parameter2 = @HM_WriteValueInteger($id, 'OPTICAL_ALARM_SELECTION', 0);
                $parameter3 = @HM_WriteValueInteger($id, 'DURATION_UNIT', 0);
                $parameter4 = @HM_WriteValueInteger($id, 'DURATION_VALUE', 5);
                if (!$parameter1 || !$parameter2 || !$parameter3 || !$parameter4) {
                    $alarmSirenResult = false;
                    // Revert
                    $this->SetValue('AcousticSignal', $actualAcousticSignal);
                }
            }
            if (!$alarmSirenResult) {
                $text = 'Fehler, ID ' . $id . ', der Quittungston konnte nicht ausgegeben werden!';
                $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
            } else {
                $text = 'ID ' . $id . ', der Quittungston wurde erfolgreich ausgegeben.';
            }
            $this->SendDebug(__FUNCTION__, $text, 0);
        }
        // Virtual remote controls
        $virtualRemoteControlResult = true;
        $virtualRemoteControls = json_decode($this->ReadPropertyString('VirtualRemoteControls'));
        if (!empty($virtualRemoteControls)) {
            foreach ($virtualRemoteControls as $virtualRemoteControl) {
                if ($virtualRemoteControl->Use) {
                    $signal = $virtualRemoteControl->AcousticSignal;
                    if ($AcousticSignal == $signal) {
                        if ($UseSwitchingDelay) {
                            IPS_Sleep($this->ReadPropertyInteger('VirtualRemoteControlSwitchingDelay'));
                        }
                        $id = $virtualRemoteControl->ID;
                        $action = @RequestAction($id, true);
                        if (!$action) {
                            IPS_Sleep(self::DELAY_MILLISECONDS);
                            $action = @RequestAction($id, true);
                        }
                        if (!$action) {
                            $virtualRemoteControlResult = false;
                            $text = 'Fehler, ID ' . $id . ', die virtuelle Fernbedienung konnte nicht geschaltet werden!';
                            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_ERROR);
                        } else {
                            $text = 'ID ' . $id . ', die virtuelle Fernbedienung wurde erfolgreich geschaltet.';
                        }
                        $this->SendDebug(__FUNCTION__, $text, 0);
                    }
                }
            }
        }
        if ($alarmSirenResult && $virtualRemoteControlResult) {
            $result = true;
        }
        return $result;
    }
}