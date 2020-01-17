<?php

// Declare
declare(strict_types=1);

trait QTON_toneAcknowledgement
{
    //#################### HM-Sec-Sir-WM

    /**
     *
     * CHANNEL  = 3, SWITCH_PANIC
     *
     * STATE:
     *
     * false    = TURN_OFF
     * true     = TURN_ON
     *
     * CHANNEL  = 4, ARMING
     *
     * ARMSTATE:
     *
     * 0        = DISARMED
     * 1        = EXTSENS_ARMED
     * 2        = ALLSENS_ARMED
     * 3        = ALARM_BLOCKED
     */

    //#################### HmIP-ASIR-O
    //#################### HmIP-ASIR
    //#################### HmIP-ASIR-2

    /**
     *
     * CHANNEL  = 3, ALARM_SWITCH_VIRTUAL_RECEIVER
     *
     * ACOUSTIC_ALARM_SELECTION:
     *
     * 0        = DISABLE_ACOUSTIC_SIGNAL
     * 1        = FREQUENCY_RISING
     * 2        = FREQUENCY_FALLING
     * 3        = FREQUENCY_RISING_AND_FALLING
     * 4        = FREQUENCY_ALTERNATING_LOW_HIGH
     * 5        = FREQUENCY_ALTERNATING_LOW_MID_HIGH
     * 6        = FREQUENCY_HIGHON_OFF
     * 7        = FREQUENCY_HIGHON_LONGOFF
     * 8        = FREQUENCY_LOWON_OFF_HIGHON_OFF
     * 9        = FREQUENCY_LOWON_LONGOFF_HIGHON_LONGOFF
     * 10       = LOW_BATTERY
     * 11       = DISARMED
     * 12       = INTERNALLY_ARMED
     * 13       = EXTERNALLY_ARMED
     * 14       = DELAYED_INTERNALLY_ARMED
     * 15       = DELAYED_EXTERNALLY_ARMED
     * 16       = EVENT
     * 17       = ERROR
     *
     * OPTICAL_ALARM_SELECTION:
     *
     * 0        = DISABLE_OPTICAL_SIGNAL
     * 1        = BLINKING_ALTERNATELY_REPEATING
     * 2        = BLINKING_BOTH_REPEATING
     * 3        = DOUBLE_FLASHING_REPEATING
     * 4        = FLASHING_BOTH_REPEATING
     * 5        = CONFIRMATION_SIGNAL_0 LONG_LONG
     * 6        = CONFIRMATION_SIGNAL_1 LONG_SHORT
     * 7        = CONFIRMATION_SIGNAL_2 LONG_SHORT_SHORT
     *
     * DURATION_UNIT:
     *
     * 0        = SECONDS
     * 1        = MINUTES
     * 2        = HOURS
     *
     * DURATION_VALUE:
     *
     * n        = VALUE
     *
     */

    /**
     * Toggles the tone acknowledgement.
     *
     * @param bool $State
     * false    = no tone acknowledgement
     * true     = execute tone acknowledgement
     */
    public function ToggleToneAcknowledgement(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde mit Parameter ' . json_encode($State) . ' aufgerufen.', 0);
        // Check alarm sirens
        if (!$this->CheckExecution()) {
            $this->SetValue('ToneAcknowledgement', false);
            return;
        }
        $this->SetValue('ToneAcknowledgement', $State);
        if ($State) {
            $this->ExecuteToneAcknowledgement(-1, -1);
            $this->SetValue('ToneAcknowledgement', false);
        }
    }

    /**
     * Executes a tone acknowledgement.
     *
     * @param int $AcousticSignal
     * -1   = Use value from configuration form
     * 0    = No selection
     * 1    = Disarmed
     * 2    = Internally armed
     * 3    = Externally armed
     * 4    = Delayed internally armed
     * 5    = Delayed externally armed
     * 6    = Event
     * 7    = Error
     * 8    = Low battery
     *
     * @param int $OpticalSignal
     * -1   = Use value from configuration form
     * 0    = No optical signal
     * 1    = Blinking alternately repeating
     * 2    = Blinking both repeating
     * 3    = Double flashing repeating
     * 4    = Flashing both repeating
     * 5    = Confirmation signal 0 long long
     * 6    = Confirmation signal 0 long short
     * 7    = Confirmation signal 0 long short short
     */
    public function ExecuteToneAcknowledgement(int $AcousticSignal = 0, int $OpticalSignal = 0): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wurde mit Parametern ' . json_encode($AcousticSignal) . ' und ' . json_encode($OpticalSignal) . ' aufgerufen.', 0);
        // Check alarm sirens
        if (!$this->CheckExecution()) {
            return;
        }
        // Alarm sirens
        $count = $this->GetAlarmSirenAmount();
        if ($count > 0) {
            $this->SendDebug(__FUNCTION__, 'Der Quittungston wird ausgegeben.', 0);
            $alarmSirens = json_decode($this->ReadPropertyString('AlarmSirens'));
            $i = 0;
            foreach ($alarmSirens as $alarmSiren) {
                if ($alarmSiren->Use) {
                    $id = $alarmSiren->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $i++;
                        $type = $alarmSiren->Type;
                        $execute = true;
                        switch ($type) {
                            // Variable
                            case 1:
                                $execute = @RequestAction($id, true);
                                break;

                            // Script
                            case 2:
                                $execute = @IPS_RunScript($id);
                                break;

                            // HM-Sec-Sir-WM
                            case 3:
                                if ($AcousticSignal == -1) {
                                    $AcousticSignal = $this->ReadPropertyInteger('AcousticSignal');
                                    if ($AcousticSignal == 0) {
                                        return;
                                    }
                                }
                                switch ($AcousticSignal) {
                                    // Disarmed
                                    case 1:
                                    // Delayed internally armed
                                    case 4:
                                    // Delayed externally armed
                                    case 5:
                                    // Event
                                    case 6:
                                    // Error
                                    case 7:
                                    // Low battery
                                    case 8:
                                        $AcousticSignal = 0;
                                        break;

                                    // Internally armed
                                    case 2:
                                        $AcousticSignal = 1;
                                        break;

                                    // Externally armed
                                    case 3:
                                        $AcousticSignal = 2;
                                        break;

                                    default:
                                        return;
                                }
                                $execute = @HM_WriteValueInteger($id, 'ARMSTATE', $AcousticSignal);
                                break;

                            // HmIP-ASIR-O, HmIP-ASIR, HmIP-ASIR-2
                            case 4:
                            case 5:
                            case 6:
                                if ($AcousticSignal == -1) {
                                    $AcousticSignal = $this->ReadPropertyInteger('AcousticSignal');
                                    if ($AcousticSignal == 0) {
                                        return;
                                    }
                                }
                                switch ($AcousticSignal) {
                                    // Disarmed
                                    case 1:
                                        $AcousticSignal = 11;
                                        break;

                                    // Internally armed
                                    case 2:
                                        $AcousticSignal = 12;
                                        break;

                                    // Externally armed
                                    case 3:
                                        $AcousticSignal = 13;
                                        break;

                                    // Delayed internally armed
                                    case 4:
                                        $AcousticSignal = 14;
                                        break;

                                    // Delayed externally armed
                                    case 5:
                                        $AcousticSignal = 15;
                                        break;

                                    // Event
                                    case 6:
                                        $AcousticSignal = 16;
                                        break;

                                    // Error
                                    case 7:
                                        $AcousticSignal = 17;
                                        break;

                                    // Low battery
                                    case 8:
                                        $AcousticSignal = 10;
                                        break;

                                    default:
                                        return;

                                }
                                if ($OpticalSignal == -1) {
                                    $OpticalSignal = $this->ReadPropertyInteger('OpticalSignal');
                                }
                                $parameter1 = @HM_WriteValueInteger($id, 'DURATION_UNIT', 0);
                                $parameter2 = @HM_WriteValueInteger($id, 'DURATION_VALUE', 3);
                                $parameter3 = @HM_WriteValueInteger($id, 'OPTICAL_ALARM_SELECTION', $OpticalSignal);
                                $parameter4 = @HM_WriteValueInteger($id, 'ACOUSTIC_ALARM_SELECTION', $AcousticSignal);
                                if (!$parameter1 || !$parameter2 || !$parameter3 || !$parameter4) {
                                    $execute = false;
                                }
                                break;

                        }
                        // Log & Debug
                        if (!$execute) {
                            $text = 'Der Quittungston konnte nicht ausgegeben werden. (ID ' . $id . ')';
                            $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
                        } else {
                            $text = 'Der Quittungston wurde ausgegeben. (ID ' . $id . ')';
                        }
                        $this->SendDebug(__FUNCTION__, $text, 0);
                        // Execution delay for next alarm siren
                        if ($count > 1 && $i < $count) {
                            IPS_Sleep(self::DELAY_MILLISECONDS);
                        }
                    }
                }
            }
        }
    }

    //#################### Private

    /**
     * Checks the execution.
     *
     * @return bool
     * false    = no alarm siren exists
     * true     = at least one alarm siren exists
     */
    private function CheckExecution(): bool
    {
        $execute = false;
        $alarmSirens = json_decode($this->ReadPropertyString('AlarmSirens'));
        if (!empty($alarmSirens)) {
            foreach ($alarmSirens as $alarmSiren) {
                if ($alarmSiren->Use) {
                    $id = $alarmSiren->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $execute = true;
                    }
                }
            }
        }
        // Log & Debug
        if (!$execute) {
            $text = 'Es ist keine Alarmsirene vorhanden!';
            $this->LogMessage(__FUNCTION__ . ', ' . $text, KL_ERROR);
            $this->SendDebug(__FUNCTION__, $text, 0);
        }
        return $execute;
    }

    /**
     * Gets the amount of alarm sirens.
     *
     * @return int
     * Returns the amount of used alarm sirens.
     */
    private function GetAlarmSirenAmount(): int
    {
        $amount = 0;
        $alarmSirens = json_decode($this->ReadPropertyString('AlarmSirens'));
        if (!empty($alarmSirens)) {
            foreach ($alarmSirens as $alarmSiren) {
                if ($alarmSiren->Use) {
                    $id = $alarmSiren->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $amount++;
                    }
                }
            }
        }
        return $amount;
    }
}