<?php

/**
 * 付款方式。
 */
abstract class SYSPay_PaymentMethod {

    /**
     * 不指定付款方式。
     */
    const ALL = 'ALL';

    /**
     * 信用卡付費。
     */
    const Credit = 'Credit';


    /**
     * 自動櫃員機。
     */
    const ATM = 'ATM';

    
    


}

/**
 * 付款方式子項目。
 */
abstract class SYSPay_PaymentMethodItem {

    /**
     * 不指定。
     */
    const None = '';
    
    // ATM 類(101~200)
    /**
     * 台新銀行。
     */
    const ATM_TAISHIN = 'TAISHIN';

    /**
     * 玉山銀行。
     */
    const ATM_ESUN = 'ESUN';

    /**
     * 華南銀行。
     */
    const ATM_HUANAN = 'HUANAN';

    /**
     * 台灣銀行。
     */
    const ATM_BOT = 'BOT';

    /**
     * 台北富邦。
     */
    const ATM_FUBON = 'FUBON';

    /**
     * 中國信託。
     */
    const ATM_CHINATRUST = 'CHINATRUST';
    
    /**
     * 土地銀行。
     */
    const ATM_LAND = 'LAND';
    
    /**
     * 國泰世華銀行。
     */
    const ATM_CATHAY = 'CATHAY';

    /**
     * 大眾銀行。
     */
    const ATM_Tachong = 'Tachong';

    /**
     * 永豐銀行。
     */
    const ATM_Sinopac = 'Sinopac';

    /**
     * 彰化銀行。
     */
    const ATM_CHB = 'CHB';

    /**
     * 第一銀行。
     */
    const ATM_FIRST = 'FIRST';
    
    
    /**
     * 信用卡(MasterCard/JCB/VISA)。
     */
    const Credit = 'Credit';

}

/**
 * 額外付款資訊。
 */
abstract class SYSPay_ExtraPaymentInfo {

    /**
     * 需要額外付款資訊。
     */
    const Yes = 'Y';

    /**
     * 不需要額外付款資訊。
     */
    const No = 'N';

}


class SYSPay_AllInOne {

    public $ServiceURL = 'ServiceURL';
    public $ServiceMethod = 'ServiceMethod';
    public $HashKey = 'HashKey';
    public $HashIV = 'HashIV';
    public $MerchantID = 'MerchantID';
    public $Mode = 'Mode';
    public $PaymentType = 'PaymentType';
    public $Send = 'Send';
    public $SendExtend = 'SendExtend';
    public $Query = 'Query';
    public $Action = 'Action';

    function __construct() {

        $this->PaymentType = 'aio';
        $this->Send = array(
            "ReturnURL"         => '',
            "ClientBackURL"     => '',
            "MerchantTradeNo"   => '',
            "MerchantTradeDate" => '',
            "TotalAmount"       => '',
            "TradeDesc"         => '',
            "ChoosePayment"     => SYSPay_PaymentMethod::ALL,
            "Remark"            => '',
            "NeedExtraPaidInfo" => SYSPay_ExtraPaymentInfo::No,
            "Items"             => array(),
            "StoreID"           => '',
            "CustomField1"      => '',
            "CustomField2"      => '',
            "CustomField3"      => '',
            "CustomField4"      => '',
        );

        $this->SendExtend = array();

        $this->Query = array(
            'MerchantTradeNo' => '',
            'TimeStamp' => ''
        );
    }
    
    //產生訂單
    function CheckOut($target = "_self") {
        $arParameters = array_merge( array('MerchantID' => $this->MerchantID) ,$this->Send);
        SYSPay_Send::CheckOut($target,$arParameters,$this->SendExtend,$this->HashKey,$this->HashIV,$this->ServiceURL);
    }

    //產生訂單html code
    function CheckOutString($paymentButton = null, $target = "_self") {
        $arParameters = array_merge( array('MerchantID' => $this->MerchantID) ,$this->Send);
        return SYSPay_Send::CheckOutString($paymentButton,$target = "_self",$arParameters,$this->SendExtend,$this->HashKey,$this->HashIV,$this->ServiceURL);
    }

    //取得付款結果通知的方法
    function CheckOutFeedback() {
        return $arFeedback = SYSPay_CheckOutFeedback::CheckOut(array_merge($_POST),$this->HashKey,$this->HashIV,0);   
    }

    //訂單查詢作業
    function QueryTradeInfo() {
        return $arFeedback = SYSPay_QueryTradeInfo::CheckOut(array_merge($this->Query,array('MerchantID' => $this->MerchantID)) ,$this->HashKey ,$this->HashIV ,$this->ServiceURL) ;
    }
    
    //信用卡定期定額訂單查詢的方法
    function QueryPeriodCreditCardTradeInfo() {
        return $arFeedback = SYSPay_QueryPeriodCreditCardTradeInfo::CheckOut(array_merge($this->Query,array('MerchantID' => $this->MerchantID, )) ,$this->HashKey ,$this->HashIV ,$this->ServiceURL);
    }

    
}

/**
* 抽象類
*/
abstract class SYSPay_Aio
{
    
    protected static function ServerPost($parameters ,$ServiceURL) {
        $ch = curl_init();

        if (FALSE === $ch) {
            throw new Exception('curl failed to initialize');
        }
        
        curl_setopt($ch, CURLOPT_URL, $ServiceURL);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
        $rs = curl_exec($ch);
        
        if (FALSE === $rs) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);

        return $rs;
    }
}

/**
*  產生訂單
*/
class SYSPay_Send extends SYSPay_Aio
{
    //付款方式物件
    public static $PaymentObj ;

    protected static function process($arParameters = array(),$arExtend = array())
    {
        //宣告付款方式物件
        $PaymentMethod    = 'SYSPay_'.$arParameters['ChoosePayment'];
        self::$PaymentObj = new $PaymentMethod;
        
        //檢查參數
        $arParameters = self::$PaymentObj->check_string($arParameters);
        
        //檢查商品
        $arParameters = self::$PaymentObj->check_goods($arParameters);


        //合併共同參數及延伸參數
        return array_merge($arParameters,$arExtend) ;
    }


    static function CheckOut($target = "_self",$arParameters = array(),$arExtend = array(),$HashKey='',$HashIV='',$ServiceURL=''){

        $arParameters = self::process($arParameters,$arExtend);
        //產生檢查碼
        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
       
        //生成表單，自動送出
        $szHtml =  '<!DOCTYPE html>';
        $szHtml .= '<html>';
        $szHtml .=     '<head>';
        $szHtml .=         '<meta charset="utf-8">';
        $szHtml .=     '</head>';
        $szHtml .=     '<body>';
        $szHtml .=         "<form id=\"__syspayForm\" method=\"post\" target=\"{$target}\" action=\"{$ServiceURL}\">";
        
        foreach ($arParameters as $keys => $value) {
            $szHtml .=         "<input type=\"hidden\" name=\"{$keys}\" value=\"{$value}\" />";
        }

        $szHtml .=             "<input type=\"hidden\" name=\"CheckMacValue\" value=\"{$szCheckMacValue}\" />";
        $szHtml .=         '</form>';
        $szHtml .=         '<script type="text/javascript">document.getElementById("__syspayForm").submit();</script>';
        $szHtml .=     '</body>';
        $szHtml .= '</html>';

        echo $szHtml ;
        exit;
    }

    static function CheckOutString($paymentButton,$target = "_self",$arParameters = array(),$arExtend = array(),$HashKey='',$HashIV='',$ServiceURL=''){
        
        $arParameters = self::process($arParameters,$arExtend);
        //產生檢查碼
        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
        
        $szHtml =  '<!DOCTYPE html>';
        $szHtml .= '<html>';
        $szHtml .=     '<head>';
        $szHtml .=         '<meta charset="utf-8">';
        $szHtml .=     '</head>';
        $szHtml .=     '<body>';
        $szHtml .=         "<form id=\"__syspayForm\" method=\"post\" target=\"{$target}\" action=\"{$ServiceURL}\">";

        foreach ($arParameters as $keys => $value) {
            $szHtml .=         "<input type=\"hidden\" name=\"{$keys}\" value=\"{$value}\" />";
        }

        $szHtml .=             "<input type=\"hidden\" name=\"CheckMacValue\" value=\"{$szCheckMacValue}\" />";
        $szHtml .=             "<input type=\"submit\" id=\"__paymentButton\" value=\"{$paymentButton}\" />";
        $szHtml .=         '</form>';
        $szHtml .=     '</body>';
        $szHtml .= '</html>';
        return  $szHtml ;
    }
}

class SYSPay_CheckOutFeedback extends SYSPay_Aio 
{
    static function CheckOut($arParameters = array(),$HashKey = '' ,$HashIV = ''){
        // 變數宣告。
        $arErrors = array();
        $arFeedback = array();
        $szCheckMacValue = '';

       

        // 重新整理回傳參數。
        foreach ($arParameters as $keys => $value) {
            if ($keys != 'CheckMacValue') {
                if ($keys == 'PaymentType') {
                    $value = str_replace('_CreditCard', '', $value);
                }
                
                $arFeedback[$keys] = $value;
            }
        }
        $CheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
        if ($CheckMacValue != $arParameters['CheckMacValue']) {
            array_push($arErrors, 'CheckMacValue verify fail.');
        }

        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }
        
        return $arFeedback;
    }
}

class SYSPay_QueryTradeInfo extends SYSPay_Aio
{
    static function CheckOut($arParameters = array(),$HashKey ='',$HashIV ='',$ServiceURL = ''){
        $arErrors = array();
        $arParameters['TimeStamp'] = time();
        $arFeedback = array();
        $arConfirmArgs = array();

        

        // 呼叫查詢。
        if (sizeof($arErrors) == 0) {
            $arParameters["CheckMacValue"] = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
            // 送出查詢並取回結果。
            $szResult = parent::ServerPost($arParameters,$ServiceURL);
            $szResult = str_replace(' ', '%20', $szResult);
            $szResult = str_replace('+', '%2B', $szResult);
            
            // 轉結果為陣列。
            parse_str($szResult, $arResult);
            // 重新整理回傳參數。
            foreach ($arResult as $keys => $value) {
                if ($keys == 'CheckMacValue') {
                    $szCheckMacValue = $value;
                } else {
                    $arFeedback[$keys] = $value;
                    $arConfirmArgs[$keys] = $value;
                }
            }

            // 驗證檢查碼。
            if (sizeof($arFeedback) > 0) {
                $szConfirmMacValue = SYSPay_CheckMacValue::generate($arConfirmArgs,$HashKey,$HashIV);
                if ($szCheckMacValue != $szConfirmMacValue) {
                    array_push($arErrors, 'CheckMacValue verify fail.');
                }
            }
        }

        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }

        return $arFeedback ;

    }    
}

class SYSPay_QueryPeriodCreditCardTradeInfo extends SYSPay_Aio
{
    static function CheckOut($arParameters = array(),$HashKey ='',$HashIV ='',$ServiceURL = ''){
        $arErrors = array();
        $arParameters['TimeStamp'] = time();
        $arFeedback = array();
        $arConfirmArgs = array();

        

        // 呼叫查詢。
        if (sizeof($arErrors) == 0) {
            $arParameters["CheckMacValue"] = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
            // 送出查詢並取回結果。
            $szResult = parent::ServerPost($arParameters,$ServiceURL);
            $szResult = str_replace(' ', '%20', $szResult);
            $szResult = str_replace('+', '%2B', $szResult);
            
            // 轉結果為陣列。
            $arResult = json_decode($szResult,true);
            // 重新整理回傳參數。
            foreach ($arResult as $keys => $value) {
                $arFeedback[$keys] = $value;
            }

        }

        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }

        return $arFeedback ;
    }
}

class SYSPay_DoAction extends SYSPay_Aio
{
    static function CheckOut($arParameters = array(),$HashKey ='',$HashIV ='',$ServiceURL = ''){
                // 變數宣告。
        $arErrors = array();
        $arFeedback = array();

        
        //產生驗證碼
        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
        $arParameters["CheckMacValue"] = $szCheckMacValue;
        // 送出查詢並取回結果。
        $szResult = self::ServerPost($arParameters,$ServiceURL);
        // 轉結果為陣列。
        parse_str($szResult, $arResult);
        // 重新整理回傳參數。
        foreach ($arResult as $keys => $value) {
            if ($keys == 'CheckMacValue') {
                $szCheckMacValue = $value;
            } else {
                $arFeedback[$keys] = $value;
            }
        }

        if (array_key_exists('RtnCode', $arFeedback) && $arFeedback['RtnCode'] != '1') {
            array_push($arErrors, vsprintf('#%s: %s', array($arFeedback['RtnCode'], $arFeedback['RtnMsg'])));
        }
        
        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }

        return $arFeedback ;

    }
}

class SYSPay_AioCapture extends SYSPay_Aio
{
    static function Capture($arParameters=array(),$HashKey='',$HashIV='',$ServiceURL=''){

        $arErrors   = array();
        $arFeedback = array();
        
        

        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
        $arParameters["CheckMacValue"] = $szCheckMacValue;

        // 送出查詢並取回結果。
        $szResult = self::ServerPost($arParameters,$ServiceURL);

        // 轉結果為陣列。
        parse_str($szResult, $arResult);

        // 重新整理回傳參數。
        foreach ($arResult as $keys => $value) {
            $arFeedback[$keys] = $value;
        }

        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }

        return $arFeedback;

    }
}

class SYSPay_TradeNoAio extends SYSPay_Aio
{
    static function CheckOut($target = "_self",$arParameters = array(),$HashKey='',$HashIV='',$ServiceURL=''){
        //產生檢查碼
        

        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
       
        //生成表單，自動送出
        $szHtml =  '<!DOCTYPE html>';
        $szHtml .= '<html>';
        $szHtml .=     '<head>';
        $szHtml .=         '<meta charset="utf-8">';
        $szHtml .=     '</head>';
        $szHtml .=     '<body>';
        $szHtml .=         "<form id=\"__syspayForm\" method=\"post\" target=\"{$target}\" action=\"{$ServiceURL}\">";
        
        foreach ($arParameters as $keys => $value) {
            $szHtml .=         "<input type=\"hidden\" name=\"{$keys}\" value=\"{$value}\" />";
        }

        $szHtml .=             "<input type=\"hidden\" name=\"CheckMacValue\" value=\"{$szCheckMacValue}\" />";
        $szHtml .=         '</form>';
        $szHtml .=         '<script type="text/javascript">document.getElementById("__syspayForm").submit();</script>';
        $szHtml .=     '</body>';
        $szHtml .= '</html>';
        
        echo $szHtml ;
        exit;
    }
}

class SYSPay_QueryTrade extends SYSPay_Aio
{
    static function CheckOut($arParameters = array(),$HashKey ='',$HashIV ='',$ServiceURL = ''){
        $arErrors = array();
        $arFeedback = array();
        $arConfirmArgs = array();

        
        // 呼叫查詢。
        if (sizeof($arErrors) == 0) {
            $arParameters["CheckMacValue"] = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
            // 送出查詢並取回結果。
            $szResult = parent::ServerPost($arParameters,$ServiceURL);
            
            // 轉結果為陣列。
            $arResult = json_decode($szResult,true);
            
            // 重新整理回傳參數。
            foreach ($arResult as $keys => $value) {
                $arFeedback[$keys] = $value;
            }
        }

        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }

        return $arFeedback ;
    }
}

class SYSPay_FundingReconDetail extends SYSPay_Aio
{
    static function CheckOut($target = "_self",$arParameters = array(),$HashKey='',$HashIV='',$ServiceURL=''){
        //產生檢查碼
        
        
        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
       
        //生成表單，自動送出
        $szHtml =  '<!DOCTYPE html>';
        $szHtml .= '<html>';
        $szHtml .=     '<head>';
        $szHtml .=         '<meta charset="utf-8">';
        $szHtml .=     '</head>';
        $szHtml .=     '<body>';
        $szHtml .=         "<form id=\"__syspayForm\" method=\"post\" target=\"{$target}\" action=\"{$ServiceURL}\">";
        
        foreach ($arParameters as $keys => $value) {
            $szHtml .=         "<input type=\"hidden\" name=\"{$keys}\" value=\"{$value}\" />";
        }

        $szHtml .=             "<input type=\"hidden\" name=\"CheckMacValue\" value=\"{$szCheckMacValue}\" />";
        $szHtml .=         '</form>';
        $szHtml .=         '<script type="text/javascript">document.getElementById("__syspayForm").submit();</script>';
        $szHtml .=     '</body>';
        $szHtml .= '</html>';

        echo $szHtml ;
        exit;
    }
}

class SYSPay_CreateTrade extends SYSPay_Aio
{
    //付款方式物件
    public static $PaymentObj ;

    protected static function process($arParameters = array(),$arExtend = array())
    {
        //宣告付款方式物件
        $PaymentMethod    = 'SYSPay_'.$arParameters['ChoosePayment'];
        self::$PaymentObj = new $PaymentMethod;
        
        //檢查參數
        $arParameters = self::$PaymentObj->check_string($arParameters);
        
        //檢查商品
        $arParameters = self::$PaymentObj->check_goods($arParameters);

        

        //合併共同參數及延伸參數
        return array_merge($arParameters,$arExtend) ;
    }

    static function CheckOut($arParameters = array(),$arExtend = array(),$HashKey='',$HashIV='',$ServiceURL=''){

        $arErrors   = array();
        $arFeedback = array();
        $szCheckMacValueReturn = '' ;

        $arParameters = self::process($arParameters,$arExtend);

        //產生檢查碼
        $szCheckMacValue = SYSPay_CheckMacValue::generate($arParameters,$HashKey,$HashIV);
        $arParameters["CheckMacValue"] = $szCheckMacValue;

        // 送出查詢並取回結果。
        $szResult = self::ServerPost($arParameters,$ServiceURL);

        // 轉結果為陣列。
        $arResult = json_decode($szResult,true);

        // 重新整理回傳參數。
        foreach ($arResult as $keys => $value) {
            if ($keys == 'CheckMacValue') {
                $szCheckMacValueReturn = $value;
            } else {
                $arFeedback[$keys] = $value;
            }
        }

        if (array_key_exists('RtnCode', $arFeedback) && $arFeedback['RtnCode'] != '1') {
            array_push($arErrors, vsprintf('#%s: %s', array($arFeedback['RtnCode'], $arFeedback['RtnMsg'])));
        }
        else{
            // 參數取回壓碼驗證
            $szCheckMacValueReturnParameters = SYSPay_CheckMacValue::generate($arFeedback,$HashKey,$HashIV);

            if($szCheckMacValueReturnParameters != $szCheckMacValueReturn){
                array_push($arErrors, 'CheckMacValue verify fail.');
            }
        }

        if (sizeof($arErrors) > 0) {
            throw new Exception(join('- ', $arErrors));
        }

        return $arFeedback ;
    }
}

Abstract class SYSPay_Verification
{

    // 付款方式延伸參數
    public $arPayMentExtend = array();

    //檢查共同參數
    public function check_string($arParameters = array()){
        
        $arErrors = array();
        if (strlen($arParameters['MerchantID']) == 0) {
            array_push($arErrors, 'MerchantID is required.');
        }
        if (strlen($arParameters['MerchantID']) > 15) {
            array_push($arErrors, 'MerchantID max langth as 15.');
        }

        if (strlen($arParameters['ReturnURL']) == 0) {
            array_push($arErrors, 'ReturnURL is required.');
        }
        if (strlen($arParameters['ClientBackURL']) > 200) {
            array_push($arErrors, 'ClientBackURL max langth as 200.');
        }
        

        if (strlen($arParameters['MerchantTradeNo']) == 0) {
            array_push($arErrors, 'MerchantTradeNo is required.');
        }
        if (strlen($arParameters['MerchantTradeNo']) > 19) {
            array_push($arErrors, 'MerchantTradeNo max langth as 19.');
        }
        if (strlen($arParameters['MerchantTradeDate']) == 0) {
            array_push($arErrors, 'MerchantTradeDate is required.');
        }
        if (strlen($arParameters['TotalAmount']) == 0) {
            array_push($arErrors, 'TotalAmount is required.');
        }
        if (strlen($arParameters['TradeDesc']) == 0) {
            array_push($arErrors, 'TradeDesc is required.');
        }
        if (strlen($arParameters['TradeDesc']) > 200) {
            array_push($arErrors, 'TradeDesc max langth as 200.');
        }
        if (strlen($arParameters['ChoosePayment']) == 0) {
            array_push($arErrors, 'ChoosePayment is required.');
        }
        if (strlen($arParameters['NeedExtraPaidInfo']) == 0) {
            array_push($arErrors, 'NeedExtraPaidInfo is required.');
        }
        if (sizeof($arParameters['Items']) == 0) {
            array_push($arErrors, 'Items is required.');
        }

        

        if (sizeof($arErrors)>0) throw new Exception(join('<br>', $arErrors));

        
        
        return $arParameters ;
    }

    //檢查延伸參數
    public function check_extend_string($arExtend = array()){
        //沒設定參數的話，就給預設參數
        foreach ($this->arPayMentExtend as $key => $value) {
            if(!isset($arExtend[$key])) $arExtend[$key] = $value;
        }
        return $arExtend ;
    }

    //檢查商品
    public function check_goods($arParameters = array()){
        // 檢查產品名稱。
        $szItemName = '';
        $arErrors   = array();
        if (sizeof($arParameters['Items']) > 0) {
            foreach ($arParameters['Items'] as $keys => $value) {
                $szItemName .= vsprintf('#%s %d %s x %u', $arParameters['Items'][$keys]);
                
            }

            if (strlen($szItemName) > 0) {
                $szItemName = mb_substr($szItemName, 1, 200);
                $arParameters['ItemName'] = $szItemName ;
            }
        } else {
            array_push($arErrors, "Goods information not found.");
        }

        if(sizeof($arErrors)>0) throw new Exception(join('<br>', $arErrors));

        unset($arParameters['Items']);
        return $arParameters ;
    }

    //過濾多餘參數
    public function filter_string($arExtend = array()){
        $arPayMentExtend = array_merge(array_keys($this->arPayMentExtend));
        foreach ($arExtend as $key => $value) {
            if (!in_array($key,$arPayMentExtend )) {
                unset($arExtend[$key]);
            }
        }

        return $arExtend ;
    }

    

    
}



/**
*  付款方式 ATM
*/
class SYSPay_ATM extends SYSPay_Verification
{
    public  $arPayMentExtend = array(
                            'ClientRedirectURL'=> '',
                        );
    
    //過濾多餘參數
    function filter_string($arExtend = array()){
        $arExtend = parent::filter_string($arExtend);
        return $arExtend ;
    }
}



/**
* 付款方式 : 信用卡
*/
class SYSPay_Credit extends SYSPay_Verification
{
    public $arPayMentExtend = array(
                                    "CreditInstallment" => '',
                                    "InstallmentAmount" => 0,
                                    "Language"          => '',
                                    "BindingCard"       => '',
                                    "MerchantMemberID"  => '',
                                    "PeriodAmount"      => '',
                                    "MonthsPerTimes"        => '',
                                    "PayTimes"         => '',
                                    "PayDate"         => '',
                                    "PeriodReturnURL"   => ''
                                );

    function filter_string($arExtend = array(),$InvoiceMark = ''){
        $arExtend = parent::filter_string($arExtend, $InvoiceMark);
        return $arExtend ;
    }
}

/**
*  付款方式：全功能
*/
class SYSPay_ALL extends SYSPay_Verification
{
    public  $arPayMentExtend = array();

    function filter_string($arExtend = array(),$InvoiceMark = ''){
        return $arExtend ;
    }
}


/**
*  檢查碼
*/
if(!class_exists('SYSPay_CheckMacValue'))
{

    class SYSPay_CheckMacValue{

        static function generate($arParameters = array(),$HashKey = '' ,$HashIV = '',$encType = 0){
            $sMacValue = '' ;
            
            if(isset($arParameters))
            {   
                unset($arParameters['CheckMacValue']);
                uksort($arParameters, array('SYSPay_CheckMacValue','merchantSort'));
                
                // 組合字串
                $sMacValue = 'HashKey=' . $HashKey ;
                foreach($arParameters as $key => $value)
                {
                    $sMacValue .= '&' . $key . '=' . $value ;
                    
                }
                
                $sMacValue .= '&HashIV=' . $HashIV ;    
                
                // URL Encode編碼     
                $sMacValue = urlencode($sMacValue); 
                
                // 轉成小寫
                $sMacValue = strtolower($sMacValue);        
                
                
                // 取代為與 dotNet 相符的字元
                $sMacValue = str_replace('%2d', '-', $sMacValue);
                $sMacValue = str_replace('%5f', '_', $sMacValue);
                $sMacValue = str_replace('%2e', '.', $sMacValue);
                $sMacValue = str_replace('%21', '!', $sMacValue);
                $sMacValue = str_replace('%2a', '*', $sMacValue);
                $sMacValue = str_replace('%28', '(', $sMacValue);
                $sMacValue = str_replace('%29', ')', $sMacValue);
                
                // 編碼
                
                $sMacValue = hash('sha256', $sMacValue);
                   

                $sMacValue = strtoupper($sMacValue);
                
            }  
            
            return $sMacValue ;
        }
        /**
        * 自訂排序使用
        */
        private static function merchantSort($a,$b)
        {
            return strcasecmp($a, $b);
        }

    }
}


?>
