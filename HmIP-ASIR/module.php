<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/HmIP-ASIR
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class QuittungstonHmIPASIR extends IPSModule
{
    //Helper
    use QTHMIPASIR_backupRestore;
    use QTHMIPASIR_muteMode;
    use QTHMIPASIR_toneAcknowledgement;
    use QTHMIPASIR_triggerVariable;

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
        // Tone acknowledgement
        $this->RegisterPropertyInteger('AlarmSiren', 0);
        $this->RegisterPropertyInteger('AlarmSirenSwitchingDelay', 0);
        // Virtual remote controls
        $this->RegisterPropertyString('VirtualRemoteControls', '[]');
        $this->RegisterPropertyInteger('VirtualRemoteControlSwitchingDelay', 0);
        // Trigger variables
        $this->RegisterPropertyString('TriggerVariables', '[]');
        // Mute mode
        $this->RegisterPropertyBoolean('UseAutomaticMuteMode', false);
        $this->RegisterPropertyString('MuteModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('MuteModeEndTime', '{"hour":6,"minute":0,"second":0}');

        // Variables
        // Tone acknowledgement
        $profile = 'QTHMIPASIR.' . $this->InstanceID . '.AcousticSignal';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
            IPS_SetVariableProfileIcon($profile, 'Speaker');
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Batterie leer', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Unscharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'Intern Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 3, 'Extern Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 4, 'Intern verzögert Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 5, 'Extern verzögert Scharf', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 6, 'Ereignis', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 7, 'Fehler', '', 0x00FF00);
        $this->RegisterVariableInteger('AcousticSignal', 'Quittungston', $profile, 10);
        $this->EnableAction('AcousticSignal');
        // Mute mode
        $profile = 'QTHMIPASIR.' . $this->InstanceID . '.MuteMode.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Speaker', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Speaker', 0x00FF00);
        $this->RegisterVariableBoolean('MuteMode', 'Stummschaltung', $profile, 20);
        $this->EnableAction('MuteMode');

        // Timers
        $this->RegisterTimer('StartMuteMode', 0, 'QTHMIPASIR_StartMuteMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopMuteMode', 0, 'QTHMIPASIR_StopMuteMode(' . $this->InstanceID . ',);');
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

        // Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Delete all registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        // Validation
        if (!$this->ValidateConfiguration()) {
            return;
        }

        // Register references and update messages
        $id = $this->ReadPropertyInteger('AlarmSiren');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterReference($id);
        }
        $properties = ['VirtualRemoteControls', 'TriggerVariables'];
        foreach ($properties as $property) {
            $variables = json_decode($this->ReadPropertyString($property));
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterReference($variable->ID);
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }

        $this->SetMuteModeTimer();
        $this->CheckMuteModeTimer();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $profiles = ['AcousticSignal', 'MuteMode.Reversed'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'QTHMIPASIR.' . $this->InstanceID . '.' . $profile;
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
                $scriptText = 'QTHMIPASIR_CheckTriggerVariable(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                @IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Alarm siren
        $id = $this->ReadPropertyInteger('AlarmSiren');
        $enabled = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $enabled = true;
        }
        $formData['elements'][1]['items'][0] = [
            'type'  => 'RowLayout',
            'items' => [$formData['elements'][1]['items'][0]['items'][0] = [
                'type'    => 'SelectInstance',
                'name'    => 'AlarmSiren',
                'caption' => 'HmIP-ASIR -O/-2 Instanz, Kanal 3',
                'width'   => '600px',
            ],
                $formData['elements'][1]['items'][0]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $enabled
                ],
                $formData['elements'][1]['items'][0]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'ID ' . $id . ' konfigurieren',
                    'visible'  => $enabled,
                    'objectID' => $id
                ]
            ]
        ];
        $formData['elements'][1]['items'][1] = [
            'type'    => 'NumberSpinner',
            'name'    => 'AlarmSirenSwitchingDelay',
            'caption' => 'Schaltverzögerung',
            'minimum' => 0,
            'suffix'  => 'Millisekunden'
        ];
        // Virtual remote controls
        $variables = json_decode($this->ReadPropertyString('VirtualRemoteControls'));
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
                    'Use'            => $use,
                    'AcousticSignal' => $variable->AcousticSignal,
                    'ID'             => $id,
                    'rowColor'       => $rowColor];
            }
        }
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
                $secondVariable = 0;
                if (@isset($variable->SecondVariable)) {
                    $secondVariable = $variable->SecondVariable;
                }
                $secondVariableValue = '';
                if (@isset($variable->SecondVariableValue)) {
                    $secondVariableValue = $variable->SecondVariableValue;
                }
                $formData['elements'][3]['items'][0]['values'][] = [
                    'Use'                 => $use,
                    'ID'                  => $id,
                    'TriggerType'         => $variable->TriggerType,
                    'TriggerValue'        => $variable->TriggerValue,
                    'SecondVariable'      => $secondVariable,
                    'SecondVariableValue' => $secondVariableValue,
                    'AcousticSignal'      => $variable->AcousticSignal,
                    'rowColor'            => $rowColor];
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
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription,
                'rowColor'           => $rowColor];
        }
        // Status
        $formData['status'][0] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => 'Quittungston HmIP-ASIR wird erstellt',
        ];
        $formData['status'][1] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => 'Quittungston HmIP-ASIR ist aktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][2] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => 'Quittungston HmIP-ASIR wird gelöscht (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][3] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => 'Quittungston HmIP-ASIR ist inaktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][4] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ')',
        ];
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function EnableVirtualRemoteControlVariableConfigurationButton(int $ObjectID): void
    {
        $this->UpdateFormField('VirtualRemoteControlVariableConfigurationButton', 'caption', 'Variable ' . $ObjectID . ' Bearbeiten');
        $this->UpdateFormField('VirtualRemoteControlVariableConfigurationButton', 'visible', true);
        $this->UpdateFormField('VirtualRemoteControlVariableConfigurationButton', 'enabled', true);
        $this->UpdateFormField('VirtualRemoteControlVariableConfigurationButton', 'objectID', $ObjectID);
    }

    public function EnableTriggerVariableConfigurationButton(int $ObjectID): void
    {
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'caption', 'Variable ' . $ObjectID . ' Bearbeiten');
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'visible', true);
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'enabled', true);
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'objectID', $ObjectID);
    }

    public function ShowVariableDetails(int $VariableID): void
    {
        if ($VariableID == 0 || !@IPS_ObjectExists($VariableID)) {
            return;
        }
        // Variable
        echo 'ID: ' . $VariableID . "\n";
        echo 'Name: ' . IPS_GetName($VariableID) . "\n";
        $variable = IPS_GetVariable($VariableID);
        if (!empty($variable)) {
            $variableType = $variable['VariableType'];
            switch ($variableType) {
                case 0:
                    $variableTypeName = 'Boolean';
                    break;

                case 1:
                    $variableTypeName = 'Integer';
                    break;

                case 2:
                    $variableTypeName = 'Float';
                    break;

                case 3:
                    $variableTypeName = 'String';
                    break;

                default:
                    $variableTypeName = 'Unbekannt';
            }
            echo 'Variablentyp: ' . $variableTypeName . "\n";
        }
        // Profile
        $profile = @IPS_GetVariableProfile($variable['VariableProfile']);
        if (empty($profile)) {
            $profile = @IPS_GetVariableProfile($variable['VariableCustomProfile']);
        }
        if (!empty($profile)) {
            $profileType = $variable['VariableType'];
            switch ($profileType) {
                case 0:
                    $profileTypeName = 'Boolean';
                    break;

                case 1:
                    $profileTypeName = 'Integer';
                    break;

                case 2:
                    $profileTypeName = 'Float';
                    break;

                case 3:
                    $profileTypeName = 'String';
                    break;

                default:
                    $profileTypeName = 'Unbekannt';
            }
            echo 'Profilname: ' . $profile['ProfileName'] . "\n";
            echo 'Profiltyp: ' . $profileTypeName . "\n\n";
        }
        if (!empty($variable)) {
            echo "\nVariable:\n";
            print_r($variable);
        }
        if (!empty($profile)) {
            echo "\nVariablenprofil:\n";
            print_r($profile);
        }
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AcousticSignal':
                $this->ExecuteToneAcknowledgement($Value, false);
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