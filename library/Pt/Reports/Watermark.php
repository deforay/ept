<?php

use setasign\Fpdi\Tcpdf\Fpdi;

class Pt_Reports_Watermark extends Pt_Reports_PdfRotate
{
    private $waterMarkText = null;
    public $_tplIdx;
    public $numPages;

    public function __construct($waterMarkText)
    {
        $this->waterMarkText = $waterMarkText;
    }

    public function Header()
    {
        global $fullPathToFile;
        if (isset($this->waterMarkText) && $this->waterMarkText != "") {
            //Put the watermark

            $this->SetFont('freesans', 'B', 120, '', false);
            $this->SetTextColor(230, 228, 198);
            $this->RotatedText(25, 190, $this->waterMarkText, 45);
        }

        if (null !== $this->_tplIdx) {
            // THIS IS WHERE YOU GET THE NUMBER OF PAGES

            $this->numPages = $this->setSourceFile($fullPathToFile);
            $this->_tplIdx = $this->importPage(1);
        }
        $this->useTemplate($this->_tplIdx, 0, 0, 200);
    }

    public function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin

        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
        //$this->SetAlpha(0.7);

    }
}
