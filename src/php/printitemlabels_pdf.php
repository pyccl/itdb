<?php 
//error_reporting(E_ALL); 
/* Spiros Ioannou 2009 , sivann _at_ gmail.com */
require("../init.php");
require_once('PDF_Label.php');
set_time_limit(15);

// 无文本：只清空正文，保留页头、图片
if (!empty($wantnotext)) {
    $labeltext = '';
    $wantraligntext = 0;

    // 无文本强制开启二维码，避免空白
    $wantbarcode = 1;
}

// 无二维码时，右侧文字自动关闭
if (empty($wantbarcode)) {
    $wantraligntext = 0;
}

$ids = "";
for ($i=0;$i<count($selitems);$i++)  {
  $ids .= "'".$selitems[$i]."'";
  if ($i < count($selitems)-1) $ids .= ", ";
}

// 修复 ambiguous column status
$sql="SELECT items.id,model,sn,sn2,sn3,itemtypeid,dnsname,ipv4,ipv6,label,internalid,
custom_user,custom_dept,agents.title as agtitle,items.status,purchprice,purchasedate,macs,
departments.name AS dept_name,
employees.name AS emp_name,
statustypes.statusdesc,
settings.currency,
settings.dateformat
FROM items
LEFT JOIN agents ON agents.id=items.manufacturerid
LEFT JOIN departments ON departments.id=items.custom_dept
LEFT JOIN employees ON employees.id=items.custom_user
LEFT JOIN statustypes ON statustypes.id = items.status
LEFT JOIN settings ON 1=1
WHERE items.id in ($ids)
order by items.id";
$sth=db_execute($dbh,$sql);

// 自动方向 + 自定义尺寸（真正 40×30 不变形）
$papersize = $_POST['papersize'];
$custom_w = isset($_POST['custom_w']) ? (int)$_POST['custom_w'] : 40;
$custom_h = isset($_POST['custom_h']) ? (int)$_POST['custom_h'] : 30;

// 自动判断方向：宽>高=横向L，否则纵向P
$orientation = ($custom_w > $custom_h) ? 'L' : 'P';

// 构造格式
$format = array(
    'paper-size' => ($papersize === 'Custom') ? array($custom_w, $custom_h) : $papersize,
    'metric' => 'mm',
    'marginLeft' => $lmargin,
    'marginTop' => $tmargin,
    'NX' => $cols,
    'NY' => $rows,
    'SpaceX' => $hpitch - $lwidth,
    'SpaceY' => $vpitch - $lheight,
    'width' => $lwidth,
    'height' => $lheight,
    'font-size' => $fontsize
);

// 用动态方向创建（关键！）
$pdf = new PDF_Label($format, 'mm', 1, 1, $orientation);

$pdf->AddPage();
$pdf->SetAuthor('ITDB Asset Management');
$pdf->SetTitle('Items');
$pdf->setFontSubsetting(true);
$pdf->SetFont('cid0cs', '', $fontsize);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

for ($skipno=0;$skipno<$labelskip;$skipno++) {
  $pdf->Add_Label("","",0,255,"",0,0,6,6);
}

$pages=0;
for ($row=1;$row<=$rows;$row++) {
  if ($pages>30) break;
  for ($col=1;$col<=$cols;$col++) {
    $r=$sth->fetch(PDO::FETCH_ASSOC);
    if (!$r) break;

    $idesc=$itypes[$r['itemtypeid']]['typedesc'];
    $id=sprintf("%04d",$r['id']);
    $ipv4=mb_substr($r['ipv4'],0,15);
    $ipv6=$r['ipv6'];
    $agtitle=$r['agtitle'];
    $model=$r['model'];
    $label=$r['label'];
    $internalid=$r['internalid'];
    $dept_name = !empty($r['dept_name']) ? $r['dept_name'] : $r['custom_dept'];
    $emp_name  = !empty($r['emp_name'])  ? $r['emp_name']  : $r['custom_user'];
    $desc=trim(mb_substr("$agtitle/$model",0,37));
    $sn=strlen($r['sn'])>0 ? $r['sn'] : (strlen($r['sn2'])>0 ? $r['sn2'] : $r['sn3']);
    $status = !empty($r['statusdesc']) ? $r['statusdesc'] : $r['status'];
    
    // 货币符号
    $currency_html = !empty($r['currency']) ? $r['currency'] : '&#20803;';
    $currency = html_entity_decode($currency_html);
    $purchprice = trim($r['purchprice']) !== '' ? $currency.$r['purchprice'] : '';
    
    // 日期格式化
    $purchasedate = '';
    if (!empty($r['purchasedate']) && is_numeric($r['purchasedate'])) {
        $df = !empty($r['dateformat']) ? $r['dateformat'] : 'ymd';
        if ($df == 'dmy') {
            $purchasedate = date('d/m/Y', $r['purchasedate']);
        } elseif ($df == 'mdy') {
            $purchasedate = date('m/d/Y', $r['purchasedate']);
        } else {
            $purchasedate = date('Y-m-d', $r['purchasedate']);
        }
    }
    
    $macs = $r['macs'];

    $labeltext = "";
    $print_fields = isset($_POST['print_fields']) ? $_POST['print_fields'] : array();

	if (empty($print_fields) || count($print_fields) == 0) {
	    $print_fields = array('id','manu_model','status','dept_user');
	}

    if (in_array('id', $print_fields) && trim($id) !== '') {
        $labeltext .= t('ID').":$id\n";
    }
    if (in_array('internalid', $print_fields) && trim($internalid) !== '') {
        $labeltext .= t('Internal ID').":$internalid\n";
    }
    if (in_array('itemtype', $print_fields) && trim($idesc) !== '') {
        $labeltext .= t('Type').":$idesc\n";
    }
    if (in_array('manu_model', $print_fields) && trim($desc) !== '') {
        $labeltext .= "$desc\n";
    }
    if (in_array('sn', $print_fields) && trim($sn) !== '') {
        $labeltext .= t('S/N').":$sn\n";
    }
    if (in_array('label', $print_fields) && trim($label) !== '') {
        $labeltext .= t('LBL').":$label\n";
    }
    if (in_array('status', $print_fields) && trim($status) !== '') {
        $labeltext .= t('Status').":$status\n";
    }
    $dept_user_str = trim("$dept_name/$emp_name");
    if (in_array('dept_user', $print_fields) && $dept_user_str !== '/') {
        $labeltext .= t('Department')."/".t('End User').":$dept_name/$emp_name\n";
    }
    if (in_array('purchprice', $print_fields) && trim($purchprice) !== '') {
        $labeltext .= t('Purchase Price').":$purchprice\n";
    }
    if (in_array('purchasedate', $print_fields) && trim($purchasedate) !== '') {
        $labeltext .= t('Purchase Date').":$purchasedate\n";
    }
    if (in_array('macs', $print_fields) && trim($macs) !== '') {
        $labeltext .= t('MAC').":$macs\n";
    }
    if (in_array('ipv4', $print_fields) && trim($ipv4) !== '') {
        $labeltext .= t('IPv4').":$ipv4\n";
    }
    if (in_array('ipv6', $print_fields) && trim($ipv6) !== '') {
        $labeltext .= t('IPv6').":$ipv6\n";
    }

    $headertext = $wantheadertext ? str_replace('_NL_',"\n",$headertext) : "";
    $image = $wantheaderimage ? $image : "";
    $barcode = $wantbarcode ? $qrtext.$id : "";
    $nbw=0.30;
    $barcodewidth=((strlen($barcode)+3)*(15+2)+20)*$nbw;
    $bh=max(0.15*$barcodewidth+3,16);
    
    if ($wantnotext) {
        $labeltext='';
    }

    $pdf->Add_Label(
        $headertext,
        $labeltext,
        $padding,
        $border,
        $image,
        $imagewidth,
        $imageheight,
        $headerfontsize,
        $fontsize,
        $idfontsize,
        $barcode,
        $nbw,
        $bh,
        $barcodesize,
        $wantraligntext
    );
  }
  if ($row==$rows) {
    $row=0;
    $col=0;
    $pages++;
  }
}

$dstr=date('Y-m-d_his');
$pdf->Output("labels-$dstr.pdf",'D');
?>
