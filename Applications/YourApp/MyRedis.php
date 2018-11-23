<?php

/**
 *         ▂▃╬▄▄▃▂▁▁
 *  ●●●█〓███████████▇▇▇▅▅▅▅▅▅▅▅▅▅▅▅▅▇▅▅          BUG
 *  ▄▅██████☆☆☆██████▄▄▃▂
 *  ████████████████████████
 *  ◥⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙◤
 *
 * redis操作类
 * 说明，任何为false的串，存在redis中都是空串。
 * 只有在key不存在时，才会返回false。
 * 这点可用于防止缓存穿透
 * @author 路漫漫
 * @link ahmerry@qq.com
 * @version
 * v2017/7/11 初版
 */
namespace Applications\YourApp;
class MyRedis {
    protected static $redis=null;
    protected static $prefix;

    /**
     * @param string $config['host'] Redis域名
     * @param int $config['port'] Redis端口,默认为6379
     * @param string $config['prefix'] Redis key prefix
     * @param string $config['auth'] Redis 密码
     */
    public static function init($config) {
        if (self::$redis === null){
//            $config = Config('redis');
            self::$redis = new \Redis();
            self::$redis->pconnect($config['host'], $config['port']);
            self::$prefix = isset($config['prefix']) ? $config['prefix'] : '';

            if($config['auth']){
                self::$redis->auth($config['auth']);
            }
        }
    }
    /**
     * 将value 的值赋值给key,生存时间为expire秒
     */
    public static function setex($key, $value, $expire = 300){
        return self::$redis->setex(self::formatKey($key), $expire, self::formatValue($value));
    }
    /**
     * 设置永久
     */
    public static function set($key, $value){
        return self::$redis->set(self::formatKey($key), self::formatValue($value));
    }

    public static function get($key) {
        $value = self::$redis->get(self::formatKey($key));
        return $value !== FALSE ? self::unformatValue($value) : NULL;
    }
    public static function ttl($key) {
        return self::$redis->ttl(self::formatKey($key));
    }

    public static function del($key) {
        return self::$redis->del(self::formatKey($key));
    }

    /**
     * 检测是否存在key,若不存在则赋值value
     */
    public static function setnx($key, $value){
        return self::$redis->setnx(self::formatKey($key), self::formatValue($value));
    }

    public static function lPush($key, $value) {
        return self::$redis->lPush(self::formatKey($key), self::formatValue($value));
    }

    public static function rPush($key, $value) {
        return self::$redis->rPush(self::formatKey($key), self::formatValue($value));
    }

    public static function lPop($key) {
        $value = self::$redis->lPop(self::formatKey($key));
        return $value !== FALSE ? self::unformatValue($value) : NULL;
    }

    public static function rPop($key) {
        $value = self::$redis->rPop(self::formatKey($key));
        return $value !== FALSE ? self::unformatValue($value) : NULL;
    }

    public static function lIndex($key,$index) {
        $value = self::$redis->lIndex(self::formatKey($key),$index);
        return $value !== FALSE ? self::unformatValue($value) : NULL;
    }

    public static function lRange($key,$start=0,$end=-1) {
        $value = self::$redis->lRange(self::formatKey($key),$start,$end);
        return $value !== FALSE ? $value : NULL;
    }

    public static function lSet($key,$index,$value) {
        return self::$redis->lSet(self::formatKey($key),$index,$value);
    }

    public static function lRem($key,$value) {
        return self::$redis->lRem(self::formatKey($key),self::formatValue($value),0);
    }



    protected static function formatKey($key) {
        return self::$prefix . $key;
    }

    protected static function formatValue($value) {
        if(is_string($value)) return $value;
        return json_encode($value,JSON_UNESCAPED_UNICODE);
    }

    protected static function unformatValue($value) {
        if(!is_string($value)) return $value;
        $result = json_decode($value,true);
        if($result===null) return $value;
        return $result;
    }

    //往集合key中增加元素
    public static function sadd($key,$value) {
        return self::$redis->sadd(self::formatKey($key), $value);
    }

    //value是否在key集合中
    public static function sismember($key,$value) {
        return self::$redis->sismember(self::formatKey($key), $value);
    }

    //移除$source集合中的$destination加到$member集合
    public static function smove($source,$destination,$member) {
        return self::$redis->smove(self::formatKey($source), self::formatKey($destination), $member);
    }

    //是否存在
    public static function exists($key) {
        return self::$redis->exists(self::formatKey($key));
    }


}