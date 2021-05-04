<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/HM-Sec-Sir-WM
 */

declare(strict_types=1);

trait QTHMSECSIRWM_toneAcknowledgement
{
    public function ExecuteToneAcknowledgement(int $AcousticSignal, bool $UseSwitchingDelay = false): bool
    {
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
            return $result;
        }
        $id = $this->ReadPropertyInteger('AlarmSiren');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('AlarmSirenSwitchingDelay'));
            }
            $actualAcousticSignal = $this->GetValue('AcousticSignal');
            $this->SetValue('AcousticSignal', $AcousticSignal);
            $acousticSignalList = [0 => 'Alarm Aus', 1 => 'Extern scharf', 2 => 'Intern scharf', 3 => 'Alarm blockiert'];
            $acousticSignalName = 'Wert nicht vorhanden!';
            if (array_key_exists($AcousticSignal, $acousticSignalList)) {
                $acousticSignalName = $acousticSignalList[$AcousticSignal];
            }
            switch ($AcousticSignal) {
                case 0: # Alarm off
                    $armState = 0;
                    break;

                case 1: # Externally armed
                    $armState = 1;
                    break;

                case 2: # Internally armed
                    $armState = 2;
                    break;

                case 3: # Alarm blocked
                    $armState = 3;
                    break;

                default:
                    return false;
            }
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . $AcousticSignal . ' - ' . $acousticSignalName, 0);
            $execute = @HM_WriteValueInteger($id, 'ARMSTATE', $armState);
            if (!$execute) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $executeAgain = @HM_WriteValueInteger($id, 'ARMSTATE', $armState);
                if (!$executeAgain) {
                    $result = false;
                    // Revert
                    $this->SetValue('AcousticSignal', $actualAcousticSignal);
                    $errorMessage = 'Quittungston ' . $AcousticSignal . ' - ' . $acousticSignalName . ' konnte nicht ausgegeben werden!';
                    $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                    $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                    $this->LogMessage($errorMessage, KL_ERROR);
                }
            }
        }
        return $result;
    }
}