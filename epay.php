<?php

function epay_config()
{
	$configarray = array(
		"FriendlyName" => array("Type" => "System", "Value" => "易支付"),
		"api" => array("FriendlyName" => "API域名", "Type" => "text",),
		"ssl" => array("FriendlyName" => "SSL支持", "Type" => "yesno",),
		"pid" => array("FriendlyName" => "商户ID", "Type" => "text",),
		"key" => array("FriendlyName" => "商户密钥", "Type" => "text",),
	);
	return $configarray;
}

function epay_link($params)
{

	$_input_charset  = "utf-8";
	$sign_type       = "MD5";
	$transport       = $params['transport'];

	# Gateway Specific Variables
	$gatewaySELLER_KEY = $params['key'];
	$gatewayPID = $params['pid'];

	# Invoice Variables
	$invoiceid = $params['invoiceid'];
	$description = $params["description"];
	$amount = $params['amount']; # Format: ##.##

	# System Variables
	$companyname 		= $params['companyname'];
	$systemurl = rtrim($params['systemurl'], '/');
	$return_url			= $systemurl . '/viewinvoice.php?id=' . $invoiceid;
	$notify_url			= $systemurl . "/modules/gateways/epay/notify.php";
	$info = array(
		"return_url"      => $return_url,
		"notify_url"      => $notify_url,
		"subject"         => "$companyname 账单 #$invoiceid",
		"body"            => $description,
		"out_order_no"    => $invoiceid,
		"total_fee"       => $amount,
	);
	$epay = new Epay($info, $gatewayPID, $gatewaySELLER_KEY, $params);
	$code = $epay->get_code();

	$img["Alipay_Logo"] = $systemurl . "/modules/gateways/epay/Alipay.png";
	$img["WeChat_Pay_Logo"] = $systemurl . "modules/gateways/epay/WeChatPay.png";
	$img["QQ_Pay_Logo"] = $systemurl . "modules/gateways/epay/QQPay.png";

	if (stristr($_SERVER['PHP_SELF'], 'viewinvoice')) {
		return $code;
	} else {
		return "<img src='" . $img["Alipay_Logo"] . "' alt='支付宝' style=\"height: 5.5em;margin: 2em 3.7em;\"/><img src='" . $img["WeChat_Pay_Logo"] . "' alt='微信支付' style=\"height: 9em;\"/><img src='" . $img["QQ_Pay_Logo"] . "' alt='QQ支付' style=\"height: 8em;\"/>";
	}
}



class Epay
{
	private $payment;

	private $order;

	public function __construct($order_info = array(), $partner, $key, $whmparams)
	{
		$this->order = $order_info;
		$this->whmparams = $whmparams;
		if ($whmparams['ssl']) {
			$this->apiurl = 'https://' . $whmparams['api'] . '/';
		} else {
			$this->apiurl = 'http://' . $whmparams['api'] . '/';
		}
		$this->payment = array(
			'partner' => $partner,
			'key' => $key,
		);
	}

	public function get_code()
	{

		$parameter = array(
			"pid" => trim($this->payment['partner']),
			"out_trade_no"	=> $this->order['out_order_no'],
			"name"	=> $this->order['subject'],
			"money"	=> $this->order['total_fee'],
			"notify_url"	=> $this->order['notify_url'],
			"return_url"	=> $this->order['return_url'],
			"sitename"	=> 'WHMCS'
		);

		$html_text = $this->buildRequestForm($parameter, $this->payment['key']);
		return $html_text;
	}

	public function notify_verify()
	{

		$Notify = $this->getSignVeryfy($_GET, $_GET['sign']);
		if ($Notify) {
			if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
				return true;
			} else {
				return false;
			}
		} else {

			return false;
		}
	}


	public function return_verify()
	{
		$Notify = $this->getSignVeryfy($_GET, $_GET['sign']);
		if ($Notify) {
			if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
				return true;
			} else {
				return false;
			}
		} else {

			return false;
		}
	}

	function getSignVeryfy($para_temp, $sign)
	{

		$para_filter = $this->paraFilter($para_temp);

		$para_sort = $this->argSort($para_filter);

		$prestr = $this->createLinkstring($para_sort);

		$isSgin = false;
		$isSgin = $this->md5Verify($prestr, $sign, $this->payment['key']);

		return $isSgin;
	}

	public function md5Verify($prestr, $sign, $key)
	{
		$prestr = $prestr . $key;
		$mysgin = md5($prestr);
		if ($mysgin == $sign) {
			return true;
		} else {
			return false;
		}
	}


	public function buildRequestForm($para_temp, $key)
	{

		$para = $this->buildRequestPara($para_temp, $key);

		$sHtml = "<form id='paysubmit' name='paysubmit' action='" . $this->apiurl . "submit.php' accept-charset='utf-8' method='POST' style='ackground: #fff;font-size: 18px;font-family: 'Arial', 'Tahoma', '微软雅黑', '雅黑';line-height: 18px;padding: 0px;margin: 0px;text-align: center;'>";
		while (list($key, $val) = each($para)) {
			$sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
		}
		$img["Alipay_Logo"] = $this->whmparams['systemurl'] . "modules/gateways/epay/Alipay.png";
		$img["WeChat_Pay_Logo"] = $this->whmparams['systemurl'] . "modules/gateways/epay/WeChatPay.png";
		$img["QQ_Pay_Logo"] = $this->whmparams['systemurl'] . "modules/gateways/epay/QQPay.png";
		$sHtml = $sHtml . "<img src='" . $img["Alipay_Logo"] . "' alt='支付宝' style=\"height: 5.5em;margin: 2em 3.7em;\"/><img src='" . $img["WeChat_Pay_Logo"] . "' alt='微信支付' style=\"height: 9em;\"/><img src='" . $img["QQ_Pay_Logo"] . "' alt='QQ支付' style=\"height: 8em;\"/>";
		$sHtml = $sHtml . "<input type='submit' class='user-login-dropdown-form-button' value='去支付' style='width: 143px;height: 40px;background: url(" . $this->whmparams['systemurl'] . "modules/gateways/epay/b-pay.jpg) no-repeat left top;color: #FFF;'/>";
		$sHtml = $sHtml . "</form>";
		return $sHtml;
	}

	public function buildRequestPara($para_temp, $key)
	{

		$para_filter = $this->paraFilter($para_temp);

		$para_sort = $this->argSort($para_filter);

		$mysign = $this->buildRequestMysign($para_sort, $key);

		$para_sort['sign'] = $mysign;

		return $para_sort;
	}

	public function paraFilter($para)
	{
		$para_filter = array();
		while (list($key, $val) = each($para)) {
			if ($key == "sign" || $key == "sign_type" || $val == "") continue;
			else	$para_filter[$key] = $para[$key];
		}
		return $para_filter;
	}

	public function argSort($para)
	{
		ksort($para);
		reset($para);
		return $para;
	}

	public function buildRequestMysign($para_sort, $key)
	{

		$prestr = $this->createLinkstring($para_sort);
		$mysign = $this->md5Sign($prestr, $key);
		return $mysign;
	}
	public function md5Sign($prestr, $key)
	{
		$prestr = $prestr . $key;
		return md5($prestr);
	}

	public function createLinkstring($para)
	{
		$arg  = "";
		while (list($key, $val) = each($para)) {
			$arg .= $key . "=" . $val . "&";
		}

		$arg = substr($arg, 0, count($arg) - 2);

		if (get_magic_quotes_gpc()) {
			$arg = stripslashes($arg);
		}

		return $arg;
	}
}
