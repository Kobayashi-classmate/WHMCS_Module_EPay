<?php
# 同步返回页面
# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
include("../epay.php");

use Illuminate\Database\Capsule\Manager as Capsule;

function convert_helper($invoiceid, $amount)
{
	$setting = Capsule::table("tblpaymentgateways")->where("gateway", "alipay")->where("setting", "convertto")->first();
	// 系统没多货币 , 直接返回
	if (empty($setting)) {
		return $amount;
	}

	// 获取用户ID 和 用户使用的货币ID
	$data = Capsule::table("tblinvoices")->where("id", $invoiceid)->first();
	$userid = $data->userid;
	$currency = getCurrency($userid);

	// 返回转换后的
	return convertCurrency($amount, $setting->value, $currency["id"]);
}

$gatewaymodule = "epay";
$GATEWAY = getGatewayVariables($gatewaymodule);

$url			= $GATEWAY['systemurl'];
$companyname 	= $GATEWAY['companyname'];
$currency		= $GATEWAY['currency'];

if (!$GATEWAY["type"]) die("Module Not Activated");

$gatewayPID 			= $GATEWAY['pid'];
$gatewaySELLER_KEY 	= $GATEWAY['key'];
$epay = new Epay(array(), $gatewayPID, $gatewaySELLER_KEY, $GATEWAY);


$Notify = $epay->getSignVeryfy($_GET, $_GET['sign']);
if ($Notify) {
	if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
		echo 'success';
		# Get Returned Variables
		$invoiceid = $_GET['out_trade_no'];
		$transid = $_GET['trade_no'];
		$amount = $_GET['money'];
		$fee = 0;

		$amount = convert_helper($invoiceid, $amount);

		checkCbTransID($transid);
		addInvoicePayment($invoiceid, $transid, $amount, $fee, $gatewaymodule);
		logTransaction($GATEWAY["name"], $_GET, "Successful-A");
	} else {
		logTransaction($GATEWAY["name"], $_GET, "Unsuccessful");
		echo "fail";
	}
} else {
	echo "fail";
}
