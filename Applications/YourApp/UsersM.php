<?php
/**
 *         ▂▃╬▄▄▃▂▁▁
 *  ●●●█〓██████████████▇▇▇▅▅▅▅▅▅▅▅▅▇▅▅          BUG
 *  ▄▅████☆RED█WOLF☆███▄▄▃▂
 *  ███████████████████████████
 *  ◥⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙▲⊙◤
 *
 * demo 示例
 * @author 路漫漫
 * @link ahmerry@qq.com
 * @version
 * v2017/6/26 初版
 */

namespace Applications\YourApp;

class UsersM {
    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $db = null;

    public function __construct($db) {
        self::$db = $db;
    }

    public function login($name,$pwd) {
        $sql = "SELECT * FROM wl_users WHERE `name` = '$name'";
        $result = self::$db->query($sql)[0];
        if ($result) {
            if (md5($pwd . $result['salt']) === $result['password']) {
                unset($result['password'],$result['salt']);
                return $result;
            }else{
                return false;
            }
        } else {
            return false;
        }
    }
}