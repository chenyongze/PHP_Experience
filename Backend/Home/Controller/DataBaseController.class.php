<?php
namespace Home\Controller;

use Org\Util\RedisInstance;
use Think\Controller;
use Think\Model;

class DataBaseController extends Controller
{
    public function index()
    {
        $redis = RedisInstance::MasterInstance();
        $redis->select(1);
        $redisInfo = $redis->lRange('message01',0,10);
        var_dump($redisInfo);
    }

    /**
     * MasterInstance Redis主实例，适合于存储数据
     */
    public function MasterInstance()
    {
        $redis = RedisInstance::MasterInstance();
        $redis->select(1);
        $redisInfo = $redis->lRange('message01',0,10);
        var_dump($redisInfo);
    }

    /**
     * SlaveOneInstance Redis从实例，适合于读取数据
     */
    public function SlaveOneInstance()
    {
        $redis = RedisInstance::SlaveOneInstance();
        $redis->select(1);
        $redisInfo = $redis->lRange('message01',0,15);
        var_dump($redisInfo);
    }

    /**
     * SlaveTwoInstance Redis从实例，适合于读取数据
     */
    public function SlaveTwoInstance()
    {
        $redis = RedisInstance::SlaveTwoInstance();
        $redis->select(1);
        $redisInfo = $redis->lRange('message01',0,6);
        var_dump($redisInfo);
    }

    /**
     * 连接本地的Redis实例
     */
    public function localhostRedis(){
        $redis = RedisInstance::Instance();
        var_dump($redis);

        $redis->connect('127.0.0.1');
        $keys = $redis->keys('*');
        var_dump($keys);
    }

    /**
     * 是否是同一个对象的比较
     * 比较结果：
     * 【redis1和redis2：是同一个实例--redis1和redis3：是同一个实例--redis2和redis3：是同一个实例--】
     * 对象$redis1,$redis2,$redis3实际上都是使用同一个对象实例，访问的都是同一块内存区域
     */
    public function ObjectCompare()
    {

        $redis1 = RedisInstance::MasterInstance();
        $redis2 = RedisInstance::SlaveOneInstance();
        $redis3 = RedisInstance::SlaveTwoInstance();
        if($redis1 === $redis2){
            echo 'redis1和redis2：是同一个实例--';
        }else{
            echo '不是同一个实例';
        }

        if($redis1 === $redis3){
            echo 'redis1和redis3：是同一个实例--';
        }else{
            echo '不是同一个实例';
        }

        if($redis3 === $redis2){
            echo 'redis2和redis3：是同一个实例--';
        }else{
            echo '不是同一个实例';
        }

    }

    /**
     * 使用队列生成reids测试数据
     * 成功：执行 RPUSH操作后，返回列表的长度：8
     */
    public function createRedis()
    {
        $redis = RedisInstance::getInstance();
        $redis->select(1);
        $message = [
            'type' => 'say',
            'userId' => $redis->incr('user_id'),
            'userName' => 'Tinywan'.mt_rand(100,9999), //是否正在录像
            'userImage' => '/res/pub/user-default-w.png', //是否正在录像
            'openId' => 'openId'.mt_rand(100000,9999999999999999),
            'roomId' => 'openId'.mt_rand(30,50),
            'createTime' => date('Y-m-d H:i:s', time()),
            'content' => $redis->incr('content') //当前是否正在打流状态
        ];
        $rPushResul = $redis->rPush('message01', json_encode($message)); //执行成功后返回当前列表的长度 9
        return $rPushResul;
    }

    public function executeFunction()
    {
        for ($x=0; $x<=10000; $x++)
        {
            $this->createRedis();
        }
    }



    /**
     * 获取Redis数据
     * 如果是14 的话 大于10条，满足条件的话，则截取列表长度是多少
     */
    public function getRedisData()
    {
        $redis = RedisInstance::getInstance();
        $redis->select(1);
        $redisInfo = $redis->lRange('message01',0,-1);
        var_dump($redisInfo);
        die;
        "<hr/>";
        $dataLength = $redis->lLen('message01');
        // 10 14 19 20 21
        if($dataLength > 20){
            $redis->lTrim('message01',10,-1);
            var_dump($dataLength);
        }else{
            echo '不可以删除了,只剩下:'.$dataLength.'条了';
            var_dump($redisInfo);
        }
        foreach($redisInfo as $value){
            $newArr[] = json_decode($value,true);
        }
        var_dump($newArr);
        die;
    }

    /**
     * 获取Redis数据批量保存到Mysql数据库
     */
    public function RedisSaveToMysql($dataList = 'Message01')
    {
        $sql= "insert into twenty_million (value) values";
        for($i=0;$i<10;$i++){
            $sql.="('50'),";
        };
        $sql = substr($sql,0,strlen($sql)-1);
        var_dump($sql);
        die;
        if(empty($dataList)) {
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
        $redis = RedisInstance::getInstance();
        $redis->select(1);
        $redisInfo = $redis->lRange('message01',0,9);
        $dataLength = $redis->lLen('message01');
//        var_dump($redisInfo);
        $model = new  Model();

//        $sql = "INSERT INTO tour_user (username,description) VALUES ('tingywan','123123')";
//        $result = $model->query($sql);
//        var_dump($result);
//        die;
        $redis->set('dataLength_front',$dataLength);
        try {
            $model->startTrans();
            foreach ($redisInfo as $action) {
                $sql = "INSERT INTO tour_user (username,description) VALUES (
                    json_decode($action,true)['userName'],
                    json_decode($action,true)['content'],
                    )";
                     $result = $model->query($sql);
            }
            $redis->set('message_insert_success', '00000');
//                $redis->lTrim('message01', 10, -1);
//                $redisInfo = $redis->lRange('message01',0,9);
//                $dataLength = $redis->lLen('message01');
//                $redis->set('dataLength_backenk', $dataLength);
            $model->commit();
        } catch (\Exception $e) {
            $redis->set('message_catch', json_encode($e));
            $model->rollback();
        }

        var_dump($result);
        die;
    }

    /*
     * TP 自带批量插入数据的方法
     */
    public function addAll($dataList,$options=array(),$replace=false)
    {
        if(empty($dataList)) {
            $this->error = L('_DATA_TYPE_INVALID_');
            return false;
        }
        // 数据处理
        foreach ($dataList as $key=>$data){
            $dataList[$key] = $this->_facade($data);
        }
        // 分析表达式
        $options =  $this->_parseOptions($options);
        // 写入数据到数据库
        $result = $this->db->insertAll($dataList,$options,$replace);
        if(false !== $result ) {
            $insertId   =   $this->getLastInsID();
            if($insertId) {
                return $insertId;
            }
        }
        return $result;
    }

}