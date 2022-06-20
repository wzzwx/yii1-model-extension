# yii1 框架model 查询扩展


### db配置
```$xslt
// main.php

return [
    ...
    'components' => [
        ...
        'db' => [
            'class' => \wzzwx\yii1model\db\DbConnection::class, // 此处使用
            'connectionString' => ...
            ...
        ],
        ...
    ],
    ...
];
```

### model 定义
```$xslt
use wzzwx\yii1model\traits\ModelTrait;

class UserModel
{
    use ModelTrait;

    public static $table = 'user';

    public static function withConfigs()
    {
        return [
            'order' => [    // 用户的订单
                'query' => OrderModel::query(),
                'thisField' => 'id',        // user 表中对应的字段
                'targetField' => 'user_id', // order表总对应的字段
                'type' => DbCommand::WITH_TYPE_MANY,    // 表示一对多
            ],
        ];
    }
}

class OrderModel
{
    use ModelTrait;

    public static $table = 'order';

    public static function withConfigs()
    {
        return [
            'items' => [    // 子订单
                'query' => OrderItemsModel::query(),
                'thisField' => 'id',    // 值为"id"的 此行可省略
                'targetField' => 'order_id',
                'type' => DbCommand::WITH_TYPE_MANY,    // 订单对子订单为 一对多
            ],
            'user' => [
                'query' => UserModel::query()->select('id,name'),
                'thisField' => 'user_id',
                // 'targetField' => 'id',  // id字段可不写
            ],
        ];
    }
}
```

### 查询
```$xslt
$ret = UserModel::find(1);  // 根据id 查询

$ret = UserModel::find([
    'name' => '张三',
    'status' => 1,
]);  // 根据自定义字段查询

$ret = UserModel::query()
    ->andWhere(['name' => 'zhangsan'])
    ->andWhere(['in', 'id', [1,2,3]])
    ->find();

```

### 增加or修改
```$xslt
    // 新增
    $user_id = UserModel::save([
        'name' => '张三',
        'age' => 22,
    ]);

    // 根据id修改
    $user = UserModel::find(1);
    UserModel::save([
        'id' => $user['id'],
        'namg' => '张四',
    ]);
    // 其他条件
    UserModel::update(['name' => '张四'], ['name' => '张三']);

```

### with 用法
#### 可配合model中的withConfigs方法, 也可将配置参数直接传入with方法
```$xslt
    // 查询用户的订单
    $ret = UserModel::query()
        ->with('order')     // 在UserModel中配置的withConfigs
        ->queryAll();

    // 查询某个订单
    $ret = OrderModel::query()
        ->with('items,user')
        ->andWhere(['order_no' => 'JD123d1awaasdwa221'])
        ->queryRow();
    // 或者
    $ret = OrderModel::query()
       ->with([
            'user',
            'items' => function($query){
                $query->select('xx,xx')
                    ->andWhere(['status' => xxx])     // 增加条件筛选
                    ->with('goods');    // 查出产品相关信息, 需要在OrderItems中配置好withConfigs
            }
        ])
       ->andWhere(['order_no' => 'JD123d1awaasdwa221'])
       ->queryRow();
    // 也可将 model 中 withConfigs()方法的返回值直接传入with()方法

```

### 分页查询
```$xslt
    $query = UserModel::query()
        ->with('order')
        ->andWhere(['user_id' => 123]);

    $list = $query()
        ->pageing(10, 1)
        // ->pageing()          // 默认取 page 和 pageSize 参数的值
        ->queryAll();           // 分页数据

    $count = $query->count();   // 总数量

```


如有扩展性问题, 建议建子类重写, 后续有时间会完善