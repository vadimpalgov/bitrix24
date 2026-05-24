<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class bit_mcp extends CModule
{
    public $MODULE_ID = 'bit.mcp';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = Loc::getMessage('BIT_MCP_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('BIT_MCP_MODULE_DESC');
        $this->PARTNER_NAME = 'FlightCore';
        $this->PARTNER_URI = '';
    }

    public function DoInstall(): void
    {
        RegisterModule($this->MODULE_ID);
    }

    public function DoUninstall(): void
    {
        UnRegisterModule($this->MODULE_ID);
    }
}
