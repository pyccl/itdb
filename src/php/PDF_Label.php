<?php
////////////////////////////////////////////////////////////////////////////////////////////////
// PDF_Label 
//
// Class to print labels in Avery or custom formats
//
// Copyright (C) 2003 Laurent PASSEBECQ (LPA)
// Based on code by Steve Dillon: steved@mad.scientist.com
// several additions, rewritten AddLabel function by Spiros Ioannou, 2009-2010 sivann at gmail, itdb project
//
//---------------------------------------------------------------------------------------------
// VERSIONS:
// 1.0: Initial release
// 1.1: + Added unit in the constructor
//      + Now Positions start at (1,1).. then the first label at top-left of a page is (1,1)
//      + Added in the description of a label:
//           font-size : defaut char size (can be changed by calling Set_Char_Size(xx);
//           paper-size: Size of the paper for this sheet (thanx to Al Canton)
//           metric    : type of unit used in this description
//                       You can define your label properties in inches by setting metric to
//                       'in' and print in millimiters by setting unit to 'mm' in constructor
//        Added some formats:
//           5160, 5161, 5162, 5163, 5164: thanx to Al Canton: acanton@adams-blake.com
//           8600                        : thanx to Kunal Walia: kunal@u.washington.edu
//      + Added 3mm to the position of labels to avoid errors 
// 1.2: = Bug of positioning
//      = Set_Font_Size modified -> Now, just modify the size of the font
// 1.3: + Labels are now printed horizontally
//      = 'in' as document unit didn't work
////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * PDF_Label - PDF label editing
 * @package PDF_Label
 * @author Laurent PASSEBECQ <lpasseb@numericable.fr>
 * @copyright 2003 Laurent PASSEBECQ
**/


require_once('../tcpdf/config/lang/chi.php');
require_once('../tcpdf/tcpdf.php');

//class PDF_Label extends PDF_Label {
class PDF_Label extends TCPDF{

	// Private properties
	var $_Margin_Left;			// Left margin of labels
	var $_Margin_Top;			// Top margin of labels
	var $_X_Space;				// Horizontal space between 2 labels
	var $_Y_Space;				// Vertical space between 2 labels
	var $_X_Number;				// Number of labels horizontally
	var $_Y_Number;				// Number of labels vertically
	var $_Width;				// Width of label
	var $_Height;				// Height of label
	var $_Line_Height;			// Line height
	var $_Padding;				// Padding
	var $_Metric_Doc;			// Type of metric for the document
	var $_COUNTX;				// Current x position
	var $_COUNTY;				// Current y position

	// List of label formats
	var $_Avery_Labels = array(
		'5160' => array('paper-size'=>'letter',	'metric'=>'mm',	'marginLeft'=>1.762,	'marginTop'=>10.7,		'NX'=>3,	'NY'=>10,	'SpaceX'=>3.175,	'SpaceY'=>0,	'width'=>66.675,	'height'=>25.4,		'font-size'=>8),
		'5161' => array('paper-size'=>'letter',	'metric'=>'mm',	'marginLeft'=>0.967,	'marginTop'=>10.7,		'NX'=>2,	'NY'=>10,	'SpaceX'=>3.967,	'SpaceY'=>0,	'width'=>101.6,		'height'=>25.4,		'font-size'=>8),
		'5162' => array('paper-size'=>'letter',	'metric'=>'mm',	'marginLeft'=>0.97,		'marginTop'=>20.224,	'NX'=>2,	'NY'=>7,	'SpaceX'=>4.762,	'SpaceY'=>0,	'width'=>100.807,	'height'=>35.72,	'font-size'=>8),
		'5163' => array('paper-size'=>'letter',	'metric'=>'mm',	'marginLeft'=>1.762,	'marginTop'=>10.7, 		'NX'=>2,	'NY'=>5,	'SpaceX'=>3.175,	'SpaceY'=>0,	'width'=>101.6,		'height'=>50.8,		'font-size'=>8),
		'5164' => array('paper-size'=>'letter',	'metric'=>'in',	'marginLeft'=>0.148,	'marginTop'=>0.5, 		'NX'=>2,	'NY'=>3,	'SpaceX'=>0.2031,	'SpaceY'=>0,	'width'=>4.0,		'height'=>3.33,		'font-size'=>12),
		'8600' => array('paper-size'=>'letter',	'metric'=>'mm',	'marginLeft'=>7.1, 		'marginTop'=>19, 		'NX'=>3, 	'NY'=>10, 	'SpaceX'=>9.5, 		'SpaceY'=>3.1, 	'width'=>66.6, 		'height'=>25.4,		'font-size'=>8),
		'L7163'=> array('paper-size'=>'A4',		'metric'=>'mm',	'marginLeft'=>5,		'marginTop'=>15, 		'NX'=>2,	'NY'=>7,	'SpaceX'=>25,		'SpaceY'=>0,	'width'=>99.1,		'height'=>38.1,		'font-size'=>9)
	);

	// Constructor
	function PDF_Label($format, $unit='mm', $posX=1, $posY=1, $orientation='P') {
		if (is_array($format)) {
			// Custom format
			$Tformat = $format;
		} else {
			// Built-in format
			if (!isset($this->_Avery_Labels[$format]))
				$this->Error('Unknown label format: '.$format);
			$Tformat = $this->_Avery_Labels[$format];
		}

		//parent::TCPDF('P', $unit, $Tformat['paper-size']);

		//($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false)
		//parent::TCPDF('P', $unit, $format, true, 'UTF-8', false);
		parent::__construct($orientation, $unit, $Tformat['paper-size'], true, 'UTF-8', false);

		$this->_Metric_Doc = $unit;
		$this->_Set_Format($Tformat);
		//$this->AddFont('BookAntiqua','','bkant.php');
		//$this->AddFont('dejavusans','','tahoma.php');
		//$this->AddFont('dejavusans','B','tahomabd.php');

		$this->SetFont('cid0cs');
		//$this->SetFont('dejavusans','B');

		$this->SetMargins(0,0); 
		$this->SetAutoPageBreak(false); 
		$this->_COUNTX = $posX-2;
		$this->_COUNTY = $posY-1;
	}

	function _Set_Format($format) {
		$this->_Margin_Left	= $this->_Convert_Metric($format['marginLeft'], $format['metric']);
		$this->_Margin_Top	= $this->_Convert_Metric($format['marginTop'], $format['metric']);
		$this->_X_Space 	= $this->_Convert_Metric($format['SpaceX'], $format['metric']);
		$this->_Y_Space 	= $this->_Convert_Metric($format['SpaceY'], $format['metric']);
		$this->_X_Number 	= $format['NX'];
		$this->_Y_Number 	= $format['NY'];
		$this->_Width 		= $this->_Convert_Metric($format['width'], $format['metric']);
		$this->_Height	 	= $this->_Convert_Metric($format['height'], $format['metric']);
		$this->Set_Font_Size($format['font-size']);
		$this->_Padding		= $this->_Convert_Metric(3, 'mm');
	}

	// convert units (in to mm, mm to in)
	// $src must be 'in' or 'mm'
	function _Convert_Metric($value, $src) {
		$dest = $this->_Metric_Doc;
		if ($src != $dest) {
			$a['in'] = 39.37008;
			$a['mm'] = 1000;
			return $value * $a[$dest] / $a[$src];
		} else {
			return $value;
		}
	}

	// Give the line height for a given font size
	function _Get_Height_Chars($pt) {
		$a = array(6=>2, 7=>2.5, 8=>3, 9=>4, 10=>5, 11=>6, 12=>7, 13=>8, 14=>9, 15=>10);
		if (!isset($a[$pt]))
			$this->Error('Invalid font size: ('.$pt.")");
		return $this->_Convert_Metric($a[$pt], 'mm');
	}

	// Sets the character size
	// This changes the line height too
	function Set_Font_Size($pt) {
		$this->_Line_Height = $this->_Get_Height_Chars($pt);
		$this->SetFontSize($pt);
	}

	function Add_Label($text_head, $text, $padding=0.5, $bordercolor=230, $img="",$imwidth=0,$imheight=0,
	                   $headerfontsize=6,$fontsize=6,$header_color='#004664',$barcode="",$bar_w="0.4",$bar_h="20",$qr_size=12,$raligntext=0) {

    $this->_COUNTX++;
    if ($this->_COUNTX == $this->_X_Number) {
        $this->_COUNTX = 0;
        $this->_COUNTY++;
        if ($this->_COUNTY == $this->_Y_Number) {
            $this->_COUNTY = 0;
            $this->AddPage();
        }
    }

    $_PosX = $this->_Margin_Left + $this->_COUNTX*($this->_Width+$this->_X_Space);
    $_PosY = $this->_Margin_Top + $this->_COUNTY*($this->_Height+$this->_Y_Space);

    $padding = 1;

    // 外框
    if ($bordercolor) {
        $this->SetDrawColor($bordercolor, $bordercolor, $bordercolor);
    }
    $this->Rect($_PosX, $_PosY, $this->_Width, $this->_Height);

    // --------------------------
    // 顶部图片+标题
    // --------------------------
    $headY = $_PosY + $padding;
    $headHeight = 0;

    if (strlen($img) && $imwidth>0) {
        $this->Image($img, $_PosX + $padding, $headY, $imwidth, $imheight);
        $headHeight = $imheight;
    }

    if (!empty($text_head)) {
        // 16进制标题颜色（复用idfontsize字段）
		$hex = ltrim($header_color, '#');
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
		$this->SetTextColor($r, $g, $b);

        $this->Set_Font_Size($headerfontsize);
        $this->SetXY($_PosX + $imwidth + $padding*2, $headY);
        $this->MultiCell($this->_Width - $imwidth - $padding*3, $this->_Line_Height, $text_head, 0, 'L');
        $headHeight = max($headHeight, $this->GetY() - $headY);
    }
    $headHeight += $padding;

		// 条码尺寸逻辑：有文本=设置值；无文本=设置值+2
		$qr_size_with_text = $qr_size;
		$qr_size_no_text = $qr_size + 5;
		
		// --------------------------
		// 无文本 → 中间大二维码（+2mm）
		// --------------------------
		if (trim($text) == '') {
		    if (!empty($barcode)) {
		        $qrs = $qr_size_no_text;
		        $qrx = $_PosX + ($this->_Width - $qrs)/2;
		        $qry = $_PosY + $headHeight + ($this->_Height - $headHeight - $qrs)/2;
		        $this->write2DBarcode($barcode, 'QRCODE,M', $qrx, $qry, $qrs, $qrs, [], 'N');
		    }
		    return;
		}
		
		// --------------------------
		// 有文本 → 用页面设置大小
		// --------------------------
	$qr_size = $qr_size_with_text;
    $qr_x = $_PosX;
    $text_max_width = $this->_Width - 2*$padding;

    if (!empty($barcode)) {
        if (!$raligntext) {
            $qr_x = $_PosX + $this->_Width - $qr_size - $padding;
            $text_max_width = $this->_Width - $qr_size - $padding*2;
        } else {
            $qr_x = $_PosX + 0.5;
            $text_max_width = $this->_Width - $qr_size - $padding*2;
        }
        $available_height = $this->_Height - $headHeight;
        $qr_y = $_PosY + $headHeight + ($available_height - $qr_size) / 2;

        $bstyle = array(
            'position' => '', 'align' => 'L', 'stretch' => false, 'fitwidth' => false,
            'border' => false, 'hpadding' => 0, 'vpadding' => 0,
            'fgcolor' => array(0,0,0), 'bgcolor' => false, 'text' => false,
        );
        $this->write2DBarcode($barcode, 'QRCODE,M', $qr_x, $qr_y, $qr_size, $qr_size, $bstyle, 'N');
    }

    // --------------------------
    // 文字：全部显示 + 加粗标题 + 厂商/型号加标题
    // --------------------------
    $this->SetFont('cid0cs');
    $this->SetTextColor(0,0,0);
    $this->Set_Font_Size($fontsize);

    $available_height = $this->_Height - $headHeight;
    $lines = explode("\n", trim($text));
    $lines = array_filter(array_map('trim', $lines));
    $line_h = $this->_Line_Height + 0.5;
    $total_text_h = count($lines) * $line_h;
    $startY = $_PosY + $headHeight + ($available_height - $total_text_h) / 2;

	$text_x = $_PosX;
	if ($raligntext && !empty($barcode)) {
	    // 文字在条码右侧
	    $text_x = $_PosX + $qr_size + 1;
	} else {
	    // 文字在条码左侧 → 强制靠左对齐
	    $text_x = $_PosX;
	}


    foreach ($lines as $line) {
        if (trim($line) === '') continue;

        $pos = strpos($line, ':');
        if ($pos !== false) {
            $key = substr($line, 0, $pos+1);
            $val = substr($line, $pos+1);

            $this->SetXY($text_x, $startY);
            $this->SetFont('', 'B');
            $this->Cell(50, $line_h, $key, 0, 0, 'L');

            $this->SetFont('', '');
            $this->SetXY($text_x + $this->GetStringWidth($key), $startY);
            $this->Cell(50, $line_h, $val, 0, 0, 'L');
        } else {
            $this->SetXY($text_x, $startY);
            $this->Cell(50, $line_h, $line, 0, 0, 'L');
        }

        $startY += $line_h;
    }
}

}
?>
