<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/Quittungston%203
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class Quittungston3 extends IPSModule # HomeMatic
{
    // Helper
    use QT3_backupRestore;
    use QT3_muteMode;
    use QT3_toneAcknowledgement;

    // Constants
    private const DELAY_MILLISECONDS = 100;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        // Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableAcousticSignal', true);
        $this->RegisterPropertyBoolean('EnableMuteMode', true);
        //Tone acknowledgement
        $this->RegisterPropertyInteger('AlarmSiren', 0);
        $this->RegisterPropertyInteger('AlarmSirenSwitchingDelay', 0);
        //Trigger
        $this->RegisterPropertyString('TriggerVariables', '[]');
        //Mute function
        $this->RegisterPropertyBoolean('UseAutomaticMuteMode', false);
        $this->RegisterPropertyString('MuteModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('MuteModeEndTime', '{"hour":6,"minute":0,"second":0}');

        // Variables
        // Tone acknowledgement
        $profile = 'QT3.' . $this->InstanceID . '.AcousticSignal';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
            IPS_SetVariableProfileIcon($profile, 'Speaker');
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Alarm Aus', '', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'Extern scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'Intern scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 3, 'Alarm blockiert', '', 0x00FF00);
        $this->RegisterVariableInteger('AcousticSignal', 'Quittungston', $profile, 10);
        $this->EnableAction('AcousticSignal');
        // Mute mode
        $this->RegisterVariableBoolean('MuteMode', 'Stummschaltung', '~Switch', 20);
        $this->EnableAction('MuteMode');

        // Timers
        $this->RegisterTimer('StartMuteMode', 0, 'QT3_StartMuteMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopMuteMode', 0, 'QT3_StopMuteMode(' . $this->InstanceID . ',);');
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Options
        IPS_SetHidden($this->GetIDForIdent('AcousticSignal'), !$this->ReadPropertyBoolean('EnableAcousticSignal'));
        IPS_SetHidden($this->GetIDForIdent('MuteMode'), !$this->ReadPropertyBoolean('EnableMuteMode'));

        // Validation
        if (!$this->ValidateConfiguration()) {
            return;
        }

        $this->RegisterMessages();
        $this->SetMuteModeTimer();
        $this->CheckMuteModeTimer();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $profiles = ['AcousticSignal'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'QT3.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                // $Data[0] = actual value
                // $Data[1] = value changed
                // $Data[2] = last value
                // $Data[3] = timestamp actual value
                // $Data[4] = timestamp value changed
                // $Data[5] = timestamp last value

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                // Check trigger
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                $scriptText = 'QT3_CheckTrigger(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Trigger variables
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $rowColor = '#C0FFC0'; # light green
                $use = $variable->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $variable->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][2]['items'][0]['values'][] = [
                    'Use'                   => $use,
                    'TriggeringVariable'    => $id,
                    'Trigger'               => $variable->Trigger,
                    'Value'                 => $variable->Value,
                    'AcousticSignal'        => $variable->AcousticSignal,
                    'rowColor'              => $rowColor];
            }
        }

        // Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '#C0FFC0'; # light green
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData['actions'][1]['items'][0]['values'][] = [
                'SenderID'              => $senderID,
                'SenderName'            => $senderName,
                'MessageID'             => $messageID,
                'MessageDescription'    => $messageDescription,
                'rowColor'              => $rowColor];
        }

        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AcousticSignal':
                $this->ExecuteToneAcknowledgement($Value);
                break;

            case 'MuteMode':
                $this->ToggleMuteMode($Value);
                break;

        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterMessages(): void
    {
        // Unregister VM_UPDATE
        $messages = $this->GetMessageList();
        if (!empty($messages)) {
            foreach ($messages as $id => $message) {
                foreach ($message as $messageType) {
                    if ($messageType == VM_UPDATE) {
                        $this->UnregisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }

        // Register VM_UPDATE
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
    }

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $result = false;
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        return $result;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}