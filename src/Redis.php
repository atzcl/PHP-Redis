<?php
// +----------------------------------------------------------------------
// | Author: zhichengliang <atzcl0310@gmail.com>  Blog：https://www.zcloop.com
// +----------------------------------------------------------------------
namespace Atzcl;

use Closure;
use Exception;
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
         'prefix'     => 'atzcl',
    ];

    protected static $redis;

    /**
     * 构造函数
     * @param array $config 缓存参数
     * @access public
     */
    public function __construct(array $config = [])
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
    public static function set(string $key, $value, int $time = null, string $unit = null)
    {
        if (empty($key) || empty($value)) {
            throw new Exception('请传入正确参数');
        }

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
                        throw new Exception('时间单位只能是：h/m/s/ms');
                        break;
                }

                if (strtolower($unit) === 'ms') {
                    // 毫秒秒为单位的到期值
                    static::pseTex($key, $value, (int) $time);
                }
            }

            // 秒为单位的到期值
            static::seTex($key, $value, (int) $time);
        } else {
            // 不设置过期时间
            static::$redis->set($key, $value);
        }
    }

    /**
     * 判断缓存是否存在
     * @param string $key 缓存标识
     * @return bool
     */
    public static function has(string $key)
    {
        return !is_null(static::get($key));
    }

    /**
     * 获取缓存
     * @param string $key 缓存标识
     * @return mixed
     * @throws Exception
     * */
    public static function get(string $key)
    {
        try {
            if (empty($key)) {
                throw new Exception('请传入需要获取的缓存名称');
            }

            $result = static::$redis->get(static::getPrefixKey($key));
            $decodeJson = json_decode($result, true);
            return is_null($decodeJson) ? $result : $decodeJson;
        } catch (Exception $e) {
            throw new Exception('get 方法只能获取 string 类型缓存');
        }
    }

    /**
     * 删除指定缓存
     * @param string $key 缓存标识
     * @throws \Exception
     * @return int 返回删除个数
     * */
    public static function del(string $key)
    {
        if (empty($key)) {
            throw new Exception('请传入需要删除的缓存名称');
        }

        return static::$redis->del(static::getPrefixKey($key));
    }

    /**
     * 判断缓存是否在 Redis 内
     * @param string $key 缓存标识
     * @throws \Exception
     * @return int 返回存在个数
     * */
    public static function exists(string $key)
    {
        if (empty($key)) {
            throw new Exception('请传入判断的缓存名称');
        }

        return static::$redis->exists(static::getPrefixKey($key));
    }

    /**
     * add 操作，不会覆盖已有值
     * @param string $key 缓存标识
     * @param mixed  $value 缓存的数据
     * @throws \Exception
     * */
    public static function setnx(string $key, $value)
    {
        if (empty($key) || empty($value)) {
            throw new Exception('参数不正确');
        }

        return static::$redis->setnx(static::getPrefixKey($key), static::isEncode($value));
    }

    /**
     * 设置以秒为过期时间单位的缓存
     * @param string $key 缓存标识
     * @param mixed  $value 缓存的数据
     * @param int    $time 缓存过期时间
     * */
    private static function seTex(string $key, $value, int $time)
    {
        // https://github.com/nrk/predis/issues/203
        static::$redis->setex(static::getPrefixKey($key), $time, static::isEncode($value));
    }

    /**
     * 设置以毫秒为过期时间单位的缓存
     * @param string $key 缓存标识
     * @param mixed  $value 缓存的数据
     * @param int    $time 缓存过期时间
     * */
    private static function pseTex(string $key, $value, int $time)
    {
        static::$redis->psetex(static::getPrefixKey($key), $time, static::isEncode($value));
    }

    /**
     * @param string $key 缓存标识
     * @param Closure $closure 匿名函数注入
     * @param int $time 缓存过期时间
     * @param string $unit 指定时间单位 （h/m/s/ms）
     * @return mixed
     */
    public static function remember(string $key, Closure $closure, int $time = null, string $unit = null)
    {
        // 先获取缓存
        $value = static::get($key);

        // 如果存在，那么直接返回
        if (!is_null($value)) {
            return $value;
        }

        // 不存在，那么就写入后返回
        static::set($key, static::isEncode($value = $closure()), $time, $unit);

        return $value;
    }

    /**
     * 在列表右端插入数据(插入缓存列表尾部), 当缓存不存在的时候，一个空缓存会被创建并执行 rpush 操作
     * @param string $key 缓存标识
     * @param mixed $value 缓存值
     * @return int 缓存的列表长度
     */
    public static function pushListAfter(string $key, $value)
    {
        return static::$redis->rpush(static::getPrefixKey($key), static::isEncode($value));
    }

    /**
     * 在列表左端插入数据 (插入到缓存列表头部)
     * @param string $key 缓存标识
     * @param mixed $value 缓存值
     * @return int 缓存的列表长度
     */
    public static function pushListTop(string $key, $value)
    {
        return static::$redis->lpush(static::getPrefixKey($key), static::isEncode($value));
    }

    /**
     * rpushx只对已存在的队列做添加, 当缓存不存在时，什么也不做
     * @param string $key 缓存标识
     * @param mixed $value 缓存值
     * @return int 缓存的列表长度
     */
    public static function rPushX(string $key, $value)
    {
        return static::$redis->rpushx(static::getPrefixKey($key), static::isEncode($value));
    }

    /**
     * 查看缓存列表长度
     * @param string $key 缓存标识
     * @return int 缓存列表长度
     */
    public static function getListLen(string $key)
    {
        return static::$redis->llen(static::getPrefixKey($key));
    }

    /**
     * 返回缓存的区间值
     * @param string $key 缓存标识
     * @param int $start 起始索引值
     * @param int $end 终止索引值
     * @return mixed
     */
    public static function getListRange(string $key, int $start, int $end)
    {
        return static::$redis->lrange(static::getPrefixKey($key), $start, $end);
    }

    /**
     * 获取缓存列表中指定顺序位置的元素
     * @param string $key
     * @param int $index
     * @return mixed
     */
    public static function getListIndex(string  $key, int $index)
    {
        return static::$redis->lindex(static::getPrefixKey($key), $index);
    }

    /**
     * 修改缓存列表中指定顺序位置的元素值
     * @param string $key
     * @param int $index
     * @param $value
     * @return mixed
     */
    public static function setListValue(string $key, int $index, $value)
    {
        return static::$redis->lset(static::getPrefixKey($key), $index, static::isEncode($value));
    }

    /**
     * 返回并移除缓存列表的第一个元素
     * @param string $key
     * @return mixed
     */
    public static function rmListTop(string $key)
    {
        return static::$redis->lpop(static::getPrefixKey($key));
    }

    /**
     * 返回并移除缓存列表的最后一个元素
     * @param string $key
     * @return mixed
     */
    public static function rmListAfter(string $key)
    {
        return static::$redis->rpop(static::getPrefixKey($key));
    }

    /**
     * 给缓存标识添加 prefix 前缀
     * @param string $key 缓存标识
     * @return string
     */
    protected static function getPrefixKey(string $key)
    {
        return static::$config['prefix'] . '_' . $key;
    }

    /**
     * 简单判断是否需要 json_encode
     * @param $value
     * @return mixed
     */
    protected static function isEncode($value)
    {
        // 如果传入的是数组，那么就编码下
        return is_array($value) || is_object($value)? json_encode($value) : $value;
    }
}
