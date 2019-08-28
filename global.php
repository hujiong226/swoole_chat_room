<?php

class Config
{
    private static $conf = [];

    public static function set($key, $val)
    {
        return self::$conf[$key] = $val;
    }
    
    public static function get($key)
    {
        return isset(self::$conf[$key]) ? self::$conf[$key] : null;
    }
}

$nickname_tmp = explode('|', '静葔椛开ζ|欢迎勾引|半世琉璃╮|亡魂孤心|﹏薄荷少年゛|川浮华黯淡|°素锦流年つ|彩Sē泡泡|夏雨﹏初晴|半梦半醒|凉城以北|国妓总奸▲|╭⌒堇色安年ㄨ|ジ柠檬心酸つ|［嘸葾嘸誨］|嘘！小声点！|嘟嘴卖萌╯ε╰|爱已离线|孤守一城|ε嘴嘴欠吻|姓坚名强|╰破茧╭成蝶メ|哎呀媽呀！|我不姓胡|ζ落雪‵成殇つ|步步奸情ゝ|▓▓▓▓刮开看看|心死身残▲|àīωǒЬīézǒu|萌面怪廋罒▽罒|こ清羽ζ墨安|Mé、易睡品|痘肤西施|ン孤身一人′|转身〃遗忘|﹏夏婲幵|众里寻她|对心开火|尐不正经～|七堇年華|半人半妖°|不念则忘|胸有大痣～|国产逗逼|哆啦A萌￢ε￢|痛而不言|柠萌小姐ζ|此號已封|莪、琲賣榀|雾以泪聚|豆蔻年华*|ー眼萭哖﹌|［顾你安稳］|「十年九夏」|葑蕊琐僾|视ní如命|残花△落败|痴心易碎|惜你如命|［栀璃鸢年］|烟花巷陌ヾ|离人怎扰|镜子不哭|〆尛果冻ル|趁早放手');
//var_dump($nickname_tmp);die();
$user_list_tmp = [];
foreach ($nickname_tmp as $key=>$val)
{
    $user_list_tmp[1000+$key] = ['id'=>1000+$key, 'nickname'=>$val];
}
Config::set('user_list', $user_list_tmp);

function std_decode($obj){
    if( is_string($obj) )return $obj;
    if( !$obj )return [];
    return json_decode(json_encode($obj),1);
}

class Console
{
    public static function log($msg)
    {
        echo "[" . date('Y-m-d H:i:s') . "] [log] " . $msg . "\n\n";
    }
}

class MyRedis
{
    private static $redis = null;
    
    public static function instance()
    {
        if(self::$redis == null)
        {
            self::$redis = new Redis();
//            self::$redis->connect('10.0.3.3', 6379);
            self::$redis->connect('127.0.0.1', 6379);
        }
        return self::$redis;
    }
}

/**
 * 接收命令行参数
 */
$param = getopt('', [
    'help::',
    'host::',
    'port::',
    'chost::',
    'cport::'
]);
//var_dump($param);
if(!empty($param))
{
    if(isset($param["help"]))
    {
        echo "\n"
        . "     --help                   参数说明；\n"
        . "\n"
        . "     --host=0.0.0.0           本服务监听的地址；\n"
        . "\n"
        . "     --port=0000              本服务监听的端口；\n"
        . "\n"
        . "     --chost=0.0.0.0          中心服务器地址；\n"
        . "\n"
        . "     --cport=0000             中心服务器端口；\n"
        . "\n";
        exit();
    }
    
    foreach ($param as $key => $val)
    {
        Config::set($key, $val);
    }
}

