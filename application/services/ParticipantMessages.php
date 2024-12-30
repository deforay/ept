<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Application_Service_ParticipantMessages
{
    protected $tempUploadDirectory;
    public function __construct()
    {
        $this->tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
    }
   
    public function addParticipantMessage($params)
    {
        $userDb = new Application_Model_DbTable_ParticipantMessages();
        return $userDb->addParticipantMessage($params);
    }
}
