<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
use \GatewayWorker\Lib\DbConnection;
use \Applications\YourApp\MyRedis;
use \Workerman\Lib\Timer;
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    static private $ids;
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * 
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id){
    }

    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $db = null;
    public static $redis = null;

    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart() {
//        self::$db = new DbConnection('127.0.0.1', 3306, 'root', 'root', 'wl', 'utf8');
        MyRedis::init(['host' => '127.0.0.1','port' => 6379,'auth' => '']);
    }

    /**
     * 通知对应控制器处理
     *
     * request:
     * respond:
     *
     *
     */
    static private function notify($msg=[],$id){
        $info = json_decode($msg,true);
        switch($info["type"]){
            case 'ht':
                return;
                break;
            case "login":
                $users = MyRedis::lRange('gameUsers');
                if ($users && in_array($info["name"],$users)) {
//                    $info['content'] = ['code'=>-1,'msg'=>'昵称被占用啦，换一个吧'];
//                    self::send( $info, $id );
//                    return false;
                }else{
                    MyRedis::rPush( 'gameUsers', $info['name'] );
                }
                self::$ids[$id]['name'] = $info["name"];
                MyRedis::set( $info["name"].':socketId',$id);
                /*$save = MyRedis::get( $info["name"] . ':save' );
                if ( $save ) {
                    $info['content'] = $save;
                    $info['type'] = 'getSave';
                } else {
                    $result = ['id'=>0,'jobId'=> 1,'level'=>1,'mapDir'=> 3,'mapId'=>21,'mapX'=> 10,'mapY'=>12,'name'=> $info["name"]];
                    $info['content'] = $result;
                }*/
                self::send( ['type' => 'talk', 'content' => ['msg' => "玩家【$info[name]】进入游戏"]] );
//                self::send( $info, $id );
                return false;
                break;
            case "talk":
                if($info['content']['target'] === "all"){
                    $info['content']['msg'] = $info['name'].'：'.$info['content']['msg'];
                    return $info;
                }
                if($info['content']['target'] == null) return false;
                self::send($info['content']['msg'],$info['content']['target']);
                return false;
                break;
            case "addUser":
                $users = MyRedis::lRange($info['content']['stageId']);
                if(!$users){
                    MyRedis::rPush($info['content']['stageId'],$info['name']);
                    MyRedis::set($info['name'],$info);
                }else{
                    if(!in_array($info['name'],$users)){
                        MyRedis::rPush($info['content']['stageId'],$info['name']);
                        MyRedis::set($info['name'],$info);
                    }
                    //把已经在线的玩家发送给当前登录玩家
                    foreach ($users as $v){
                        $userInfo = MyRedis::get($v);
                        if ($userInfo['name']==$info['name']) continue;
                        $userInfo['type'] = 'addUser';
                        self::send($userInfo,$id);
                    }
                }
                //把登录玩家推送给所有玩家
                self::send($info);
                return false;
                break;
            case "move":
                MyRedis::set($info['name'],$info);
                break;
            case "inBattle":
                break;
            case "setSave":
                return;
                $result = MyRedis::set($info['name'].':save',$info['content']);
                $info['content'] = $result;
                self::send($info,$id);
                return false;
                break;
            case "getSave":
                return;
                $save = MyRedis::get($info['name'].':save');
                $info['content'] = $save;
                self::send($info,$id);
                return false;
                break;
            case "removeUser":
                MyRedis::lRem($info['content']['stageId'],$info['name']);
                break;
            case "netTeam":
                return;
                MyRedis::set($info['name'].':teamData',$info);
                break;
            case "support":
                return;
                //支援谁
                $result = MyRedis::get($info['content']['target'].':teamData');
                //谁支援
                $result2 = MyRedis::get($info['name'].':teamData');
                if($result){
                    $result['type'] = $info['type'];
                    self::send($result,$id);
                    $socketId = MyRedis::get($info['content']['target'].':socketId');
                    $result2['type'] = $info['type'];
                    self::send($result2,$socketId);
                }
                return false;
                break;
            default:
                break;
        }
        return $info;
    }
    
   /**
    * 当客户端发来消息时触发
    * @param int $client_id 连接id
    * @param mixed $message 具体消息
    */
   public static function onMessage($client_id, $message)
   {
//       self::MyLog($message,"$client_id request");
       $result = self::notify($message,$client_id);
       if(!$result) return;
       self::send($result);
   }

    /**
     * 绑定跑步机与ksid
     * @param $data {array}  具体消息
     * @return null
     */
    public static function bindKsid($data)
    {

//        $cids = Gateway::getAllClientSessions();
            // 定时调用带命名空间的类的静态方法
//            Timer::add(15, ['Events', 'clearData'], [$sid], false);
    }

    /**
     * 统一发送出口
     * @param $result mixed  日志内容
     * @param $id string  客户端id
     * @return null
     */
    public static function send($result=false,$id=false) {
//        self::MyLog($result,"send $id");
        if($id){
            // 定向发送
            Gateway::sendToClient($id,json_encode($result,JSON_UNESCAPED_UNICODE));
        }else{
            // 向所有人发送
            Gateway::sendToAll(json_encode($result,JSON_UNESCAPED_UNICODE));
        }
    }


    /**
     * 写入日志到文件
     * @param $log mixed 日志内容
     * @param $name string 日志标识
     */
    public static function myLog($log,$name='') {
        if (is_array($log) || is_object($log)) $log = json_encode($log,JSON_UNESCAPED_UNICODE);
        $content = "\n\n$name Time : ".date('Y-m-d H:i:s')."\n".$log;
        file_put_contents('./logs.log',$content,FILE_APPEND);
//        echo $content;
    }

   
   /**
    * 当用户断开连接时触发
    * @param int $client_id 连接id
    */
   public static function onClose($client_id)
   {
       if (isset(self::$ids[$client_id]['name'])){
           $name = self::$ids[$client_id]['name'];
           $userInfo = MyRedis::get($name);
           self::send(['type'=>'talk','content'=>['msg'=>"玩家【 $name 】离开游戏"]]);
           self::send(['type'=>'removeUser','name'=>$name,'content'=>['stageId'=>$userInfo['content']['stageId']]]);
           MyRedis::lRem('gameUsers',$name);
           MyRedis::lRem($userInfo['content']['stageId'],$name);
           MyRedis::del($name);
           MyRedis::del($name.'*');
       }
       unset(self::$ids[$client_id]);
   }

    /**
     * 清空数据
     * @param int $client_id 连接id
     */
    public static function clearData($id)
    {
        $ksid = MyRedis::get('tm:id:'.self::$ids[$id]['ksKey'].':ksid');
        $r = MyRedis::del('tm:ksid:'.$ksid);
        self::MyLog($r,"del tm:ksid$ksid");
        MyRedis::del('tm:id:'.self::$ids[$id]['ksKey'].':ksid');
        MyRedis::del('tm:id:'.self::$ids[$id]['ksKey'].':tmpKsid');
        MyRedis::del('tm:id:'.self::$ids[$id]['ksKey'].':startTime');
        MyRedis::del('tm:id:'.self::$ids[$id]['ksKey'].':unlockTime');
        MyRedis::del('tm:id:'.self::$ids[$id]['ksKey'].':linkId');
        Timer::delAll();
    }

    /**
     * 结算
     * @param int $client_id 连接id
     * @param int $ksid
     */
   private static function endSettle($id,$ksid){
       /*if ($users && in_array($info["name"],$users)) {
           $userInfo = MyRedis::get( $info["name"] );
           MyRedis::lRem($userInfo['content']['stageId'],$info["name"]);
           MyRedis::del($info["name"]);
       }else{
           MyRedis::rPush( 'gameUsers', $info['name'] );
       }*/

   }

    /**
     * wifi模块数据转hex数组
     */
    static private function asciiToHexArray($str) {
        return str_split(bin2hex($str), 2);
    }

    static private function hexTime($time){
        $hexTime = strval(dechex($time));
        $zeroLen = 10-strlen($hexTime);
        if ($zeroLen){
            for ($i=0;$i<$zeroLen;$i++){
                $hexTime = '0'.$hexTime;
            }
        }
        return str_split($hexTime,2);
    }
}
