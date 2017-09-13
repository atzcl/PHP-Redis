<?php
// +----------------------------------------------------------------------
// | Author: zhichengliang <atzcl0310@gmail.com>  Blog：https://www.zcloop.com
// +----------------------------------------------------------------------
namespace Atzcl\Redis;

use Predis\Client;

/**
 * Redis接口类
 * @doc http://doc.redisfans.com/
 * */
class Redis
{
    protected static $config = [
        'host'       => '127.0.0.1',
        'port'       => 6379,
        // 'password'   => '',
        // 'select'     => 0,
        // 'timeout'    => 3600,
        // 'expire'     => 0,
        // 'persistent' => false,
        // 'prefix'     => '',
    ];

    protected static $redis;

    /**
     * 构造函数
     * @param array $config 缓存参数
     * @access public
     */
    public function __construct($config = [])
    {
        if (!empty($config)) {
            // 合并重复项
            static::$config = array_merge(static::$config, $config);
        }

        static::$redis = new Client(static::$config);
    }

    /**
     * 获取 Redis 对象
     * */
    public static function init()
    {
        return static::$redis;
    }

    /**
     * 设置缓存
     * @param string $key 缓存标识
     * @param mixed  $value 缓存的数据
     * @param int    $time 缓存过期时间
     * @param string $unit 指定时间单位 （h/m/s/ms）
     * @throws \Exception
     * */
    public static function set($key, $value, $time = null, $unit = null)
    {
        if (empty($key) || empty($value)) {
            throw new \Exception('请传入正确参数');
        }

        // 如果传入的是数组，那么就编码下
        $value = is_array($value) ? json_encode($value) : $value;

        if ($time) {

            if ($unit) {

                // 设置了过期时间并使用了快捷时间单位
                // 判断时间单位
                switch (strtolower($unit)) {
                    case 'h':
                        $time *= 3600;
                        break;
                    case 'm':
                        $time *= 60;
                        break;
                    case 's':
                        break;
                    case 'ms':
                        break;
                    default:
                        throw new \Exception('时间单位只能是：h/m/s/ms');
                        break;
                }

                if (strtolower($unit) === 'ms') {

                    // 毫秒秒为单位的到期值
                    static::_psetex($key, $value, (int) $time);
                }
            }

            // 秒为单位的到期值
            static::_setex($key, $value, (int) $time);

        } else {

            // 不设置过期时间
            static::$redis->set($key, $value);
        }
    }

    /**
     * 获取缓存
     * @param string $key 缓存标识
     * @return mixed
     * @throws \Exception
     * */
    public static function get($key)
    {
        if (empty($key)) {
            throw new \Exception('请传入需要获取的缓存名称');
        }

        $result = static::$redis->get($key);
        if ($result) {

            $decodeJson = json_decode($result, true);
            return  $decodeJson !== null ? $decodeJson : $result;
        }

        return null;
    }

    /**
     * 删除指定缓存
     * @param string $key 缓存标识
     * @throws \Exception
     * @return int 返回删除个数
     * */
    public static function del($key)
    {
        if (empty($key)) {
            throw new \Exception('请传入需要删除的缓存名称');
        }

        return static::$redis->del($key);
    }

    /**
     * 判断缓存是否在 Redis 内
     * @param string $key 缓存标识
     * @throws \Exception
     * @return int 返回存在个数
     * */
    public static function exists($key)
    {
        if (empty($key)) {
            throw new \Exception('请传入判断的缓存名称');
        }

        return static::$redis->exists($key);
    }

    public static function setnx($key, $value)
    {
        if (empty($key) || empty($value)) {
            throw new \Exception('参数不正确');
        }

        return static::$redis->setnx($key,$value);
    }

    /**
     * 设置以秒为过期时间单位的缓存
     * @param string $key 缓存标识
     * @param mixed  $value 缓存的数据
     * @param int    $time 缓存过期时间
     * @throws \Exception
     * */
    private static function _setex($key, $value, $time)
    {
        // https://github.com/nrk/predis/issues/203
        static::$redis->setex($key, $time, $value);
    }

    /**
     * 设置以毫秒为过期时间单位的缓存
     * @param string $key 缓存标识
     * @param mixed  $value 缓存的数据
     * @param int    $time 缓存过期时间
     * @throws \Exception
     * */
    private static function _psetex($key, $value, $time)
    {
        static::$redis->psetex($key, $time, $value);
    }
}