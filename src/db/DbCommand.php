<?php


namespace wzzwx\yii1model\db;


class DbCommand extends \CDbCommand
{
    const LIMIT_MAX = 50;       // 每页最多
    const LIMIT_MIN = 1;        // 每页最少
    const LIMIT_DEFAULT = 10;   // 每页默认

    const WITH_TYPE_ONE = 1;    // 一条
    const WITH_TYPE_MANY = 2;   // 多条

    public static $pageSize;

    protected $modelClassName;
    protected $with = [];         // 关联信息
    protected $forUpdate = false; // 更新锁

    public function __construct(\CDbConnection $connection, $query = null, $modelClassName = null)
    {
        $this->modelClassName = $modelClassName;
        return parent::__construct($connection, $query);
    }

    /**
     * 更新锁
     *
     * @param bool $value
     *
     * @return $this
     */
    public function forUpdate($value = true)
    {
        $this->forUpdate = $value;

        return $this;
    }

    // 支持更新锁
    public function buildQuery($query)
    {
        $sql = parent::buildQuery($query);

        if ($this->forUpdate) {
            $sql .= "\nFOR UPDATE";
        }

        return $sql;
    }

    /**
     * @param null $pageSize 每页条数
     * @param null $page 页码
     * @return DbCommand
     */
    public function paging($pageSize = null, $page = null)
    {
        if (is_null($page)) {
            $page = isset($_POST['page']) ? $_POST['page'] : (isset($_GET['page']) ? $_GET['page'] : 1);
        }
        if (is_null($pageSize)) {
            $pageSize = isset($_POST['pageSize']) ? $_POST['pageSize'] : (isset($_GET['pageSize']) ? $_GET['pageSize'] : self::LIMIT_DEFAULT);
        }

        $page = max(1, intval($page));
        $pageSize = max(min(intval($pageSize), self::LIMIT_MAX), self::LIMIT_MIN);
        static::$pageSize = $pageSize;

        return $this->limit($pageSize)
            ->offset(($page - 1) * $pageSize);
    }

    public function count($countSql = 'count(*)')
    {
        return (int)(clone $this)->setText('')
            ->select($countSql)
            ->limit(-1, -1)
            ->queryScalar();
    }

    public function buildCondition($conditions, $params = [])
    {
        // 支持 andWhere(['xx' => 'yy'])
        if (is_array($conditions) && !isset($conditions[0])) {
            $newConditions = $params = [];
            foreach ($conditions as $name => $value) {
                $key = ':' . str_replace('.', '_', $name);
                $params[$key] = $value;
                $newConditions[] = $name . ' = ' . $key;
            }
            $conditions = implode(' and ', $newConditions);
        }

        return [$conditions, $params];
    }

    public function andWhere($conditions, $params = [])
    {
        return parent::andWhere(...$this->buildCondition($conditions, $params));
    }

    public function find($id)
    {
        if (!is_array($id)) {
            $id = ['id' => intval($id)];
        }
        return $this->andWhere($id)
            ->limit(1)
            ->queryRow();
    }

    /**
     * 设置关联表的信息.
     *
     * @param $with
     * [
     *     'business' => [                                  // 手动写全部配置项
     *         'query' => TbBusiness::query(),
     *         'thisField' => 'business_id',
     *         'targetField' => 'id',
     *         'type' => DbCommand::WITH_TYPE_MANY,
     *         'callback' => null,
     *     ],
     *     'business',                                      // model 中配置了静态方法 withConfigs()
     *     'business' => function($query, &$with){          // model 中配置了静态方法 withConfigs(), 并对query或其他配置项做额外处理
     *         $query->andWhere(['status' => 1])->with('xxx');
     *         $with['callback'] = [TbBusiness::class, 'outHandle'];
     *     },
     * ]
     * @return static
     */
    public function with($with)
    {
        if (is_string($with)) {
            $with = explode(',', $with);
        }
        if (method_exists($this->modelClassName, 'withConfigs')) {
            $conf = (array)($this->modelClassName)::withConfigs();
        } else {
            $conf = [];
        }
        $ret = [];
        foreach ($with as $key => $item) {
            if (is_array($item)) {
                if (!isset($item['query'])) {
                    throw new \Exception('with配置错误: 缺少query');
                }
                $ret[$key] = $item;
                continue;
            }
            if (is_string($key)) {
                list($fun, $item) = [$item, $key];
            }
            if (!isset($conf[$item])) {
                throw new \Exception($this->modelClassName . '未定义with配置: ' . $item);
            }
            $ret[$item] = $conf[$item];
            $ret[$item]['query'] = clone $ret[$item]['query'];
            if (isset($fun) && is_callable($fun)) {
                call_user_func($fun, $ret[$item]['query'], $ret[$item]);
            }
        }

        $this->with = array_merge($this->with, $ret);

        return $this;
    }

    // 支持with
    public function queryAll($fetchAssociative = true, $params = [])
    {
        $rets = parent::queryAll($fetchAssociative, $params);
        if (empty($this->with) || empty($rets)) {
            return $rets;
        }
        foreach ($this->with as $newFieldsName => $with) {
            $query = $with['query'];
            $thisField = isset($with['thisField']) ? $with['thisField'] : 'id';
            $targetField = isset($with['targetField']) ? $with['targetField'] : 'id';
            $ids = array_column($rets, $thisField);
            $targets = $query->andWhere(['in', $targetField, $ids])->queryAll();

            $dics = [];
            foreach ($targets as $k => $tar) {
                $index = $tar[$targetField];
                if (isset($with['callback']) && is_callable($with['callback'])) {
                    $tar = call_user_func($with['callback'], $tar);
                }
                if (isset($with['type']) && self::WITH_TYPE_MANY === $with['type']) {
                    $dics[$index][] = $tar;
                } else {
                    $dics[$index] = $tar;
                }
                unset($targets[$k]);
            }
            foreach ($rets as &$ret) {
                $ret[$newFieldsName] = isset($dics[$ret[$thisField]]) ? $dics[$ret[$thisField]] : [];
            }
        }

        return $rets;
    }

    public function queryRow($fetchAssociative = true, $params = array())
    {
        $ret = parent::queryRow($fetchAssociative, $params);
        if (empty($this->with) || empty($ret)) {
            return $ret;
        }
        foreach ($this->with as $newFieldsName => $with) {
            $query = $with['query'];
            $thisField = isset($with['thisField']) ? $with['thisField'] : 'id';
            $targetField = isset($with['targetField']) ? $with['targetField'] : 'id';
            $id = $ret[$thisField];
            $targets = $query->andWhere([$targetField => $id])->queryAll();
            if (isset($with['callback']) && is_callable($with['callback'])) {
                foreach ($targets as $k => $target) {
                    $targets[$k] = call_user_func($with['callback'], $target);
                }
            }
            if (isset($with['type']) && self::WITH_TYPE_MANY === $with['type']) {
                $ret[$newFieldsName] = $targets ?: [];
            } else {
                $ret[$newFieldsName] = $targets[0] ?? [];
            }
        }

        return $ret;
    }
}