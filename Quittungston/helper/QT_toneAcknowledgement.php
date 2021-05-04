<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/Quittungston
 */

declare(strict_types=1);

trait QT_toneAcknowledgement
{
    public function ToggleToneAcknowledgement(bool $State, bool $UseSwitchingDelay = false): bool
    {
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
            return $result;
        }
        $id = $this->ReadPropertyInteger('ToneAcknowledgementVariable');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('ToneAcknowledgementVariableSwitchingDelay'));
            }
            $actualValue = $this->GetValue('AcousticSignal');
            $this->SetValue('AcousticSignal', $State);
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . json_encode($State), 0);
            $toggle = @RequestAction($id, $State);
            if (!$toggle) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $toggleAgain = @RequestAction($id, $State);
                if (!$toggleAgain) {
                    $result = false;
                    // Revert
                    $this->SetValue('AcousticSignal', $actualValue);
                    $stateName = 'aus';
                    if ($State) {
                        $stateName = 'ein';
                    }
                    $errorMessage = 'Quittungston konnte nicht ' . $stateName . 'geschaltet werden!';
                    $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                    $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                    $this->LogMessage($errorMessage, KL_ERROR);
                }
            }
            if ($State) {
                $impulseDuration = $this->ReadPropertyInteger('ImpulseDuration');
                if ($impulseDuration > 0) {
                    IPS_Sleep($impulseDuration);
                    $actualValue = $this->GetValue('AcousticSignal');
                    $this->SetValue('AcousticSignal', false);
                    $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . json_encode(false), 0);
                    $toggle = @RequestAction($id, false);
                    if (!$toggle) {
                        IPS_Sleep(self::DELAY_MILLISECONDS);
                        $toggleAgain = @RequestAction($id, false);
                        if (!$toggleAgain) {
                            $result = false;
                            //Revert
                            $this->SetValue('AcousticSignal', $actualValue);
                            $errorMessage = 'Quittungston konnte nicht ausgeschaltet werden!';
                            $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                            $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                            $this->LogMessage($errorMessage, KL_ERROR);
                        }
                    }
                }
            }
        }
        return $result;
    }
}