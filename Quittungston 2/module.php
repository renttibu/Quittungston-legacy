<?php

/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

/*
 * @module      Quittungston 2 (HmIP-ASIR, HmIP-ASIR-O, HmIP-ASIR-2)
 *
 * @prefix      QT2
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Quittungston
 *
 * @guids       Library
 *              {FC09418F-79AF-F15B-EEF5-D45E9997E0D8}
 *
 *              Quittungston 2
 *              {92B2AA6B-E2EF-496F-DD43-4F98970EDA03}
 */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class Quittungston2 extends IPSModule
{
    //Helper
    use QT2_backupRestore;
    use QT2_muteMode;
    use QT2_toneAcknowledgement;

    //Constants
    private const QUITTUNGSTON_LIBRARY_GUID = '{FC09418F-79AF-F15B-EEF5-D45E9997E0D8}';
    private const QUITTUNGSTON2_MODULE_GUID = '{92B2AA6B-E2EF-496F-DD43-4F98970EDA03}';
    private const DELAY_MILLISECONDS = 100;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterTimers();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetOptions();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->RegisterMessages();
        $this->SetMuteModeTimer();
        $this->CheckMuteModeTimer();
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
                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value
                if ($this->CheckMaintenanceMode()) {
                    return;
                }
                //Trigger action
                if ($Data[1]) {
                    $scriptText = 'QT2_CheckTrigger(' . $this->InstanceID . ', ' . $SenderID . ');';
                    IPS_RunScriptText($scriptText);
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Info
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::QUITTUNGSTON_LIBRARY_GUID);
        $module = IPS_GetModule(self::QUITTUNGSTON2_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][1]['caption'] = "ID:\t\t\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][2]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][3]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][4]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][5]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][6]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'] . ', Normen Thiel';
        $formData['elements'][0]['items'][7]['caption'] = "Präfix:\t\t\tQT2";
        //Trigger variables
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $rowColor = '#C0FFC0'; //light green
                $use = $variable->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $variable->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; //light red
                }
                $formData['elements'][3]['items'][1]['values'][] = [
                    'Use'                                           => $use,
                    'AcousticSignal'                                => $variable->AcousticSignal,
                    'ID'                                            => $id,
                    'TriggerValue'                                  => $variable->TriggerValue,
                    'rowColor'                                      => $rowColor];
            }
        }
        //Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; //light red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '#C0FFC0'; //light green
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
                'SenderID'                                              => $senderID,
                'SenderName'                                            => $senderName,
                'MessageID'                                             => $messageID,
                'MessageDescription'                                    => $messageDescription,
                'rowColor'                                              => $rowColor];
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
                $this->TriggerToneAcknowledgement($Value);
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

    private function RegisterProperties(): void
    {
        //Info
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        //Functions
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
    }

    private function CreateProfiles(): void
    {
        //Tone acknowledgement
        $profile = 'QT2.' . $this->InstanceID . '.AcousticSignal';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
            IPS_SetVariableProfileIcon($profile, 'Speaker');
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'Batterie leer', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'Unscharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 3, 'Intern Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 4, 'Extern Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 5, 'Intern verzögert Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 6, 'Extern verzögert Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 7, 'Ereignis', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 8, 'Fehler', '', 0x00FF00);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['AcousticSignal'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'QT2.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        //Tone acknowledgement
        $profile = 'QT2.' . $this->InstanceID . '.AcousticSignal';
        $this->RegisterVariableInteger('AcousticSignal', 'Quittungston', $profile, 10);
        $this->EnableAction('AcousticSignal');
        //Mute mode
        $this->RegisterVariableBoolean('MuteMode', 'Stummschaltung', '~Switch', 20);
        $this->EnableAction('MuteMode');
    }

    private function SetOptions(): void
    {
        IPS_SetHidden($this->GetIDForIdent('AcousticSignal'), !$this->ReadPropertyBoolean('EnableAcousticSignal'));
        IPS_SetHidden($this->GetIDForIdent('MuteMode'), !$this->ReadPropertyBoolean('EnableMuteMode'));
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('StartMuteMode', 0, 'QT2_StartMuteMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopMuteMode', 0, 'QT2_StopMuteMode(' . $this->InstanceID . ',);');
    }

    private function RegisterMessages(): void
    {
        //Unregister
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
        //Register
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

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $message = 'Abbruch, der Wartungsmodus ist aktiv!';
            $this->SendDebug(__FUNCTION__, $message, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $message, KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }

    private function CheckMuteMode(): bool
    {
        $muteMode = boolval($this->GetValue('MuteMode'));
        if ($muteMode) {
            $message = 'Abbruch, die Stummschaltung ist aktiv!';
            $this->SendDebug(__FUNCTION__, $message, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $message, KL_WARNING);
        }
        return $muteMode;
    }
}