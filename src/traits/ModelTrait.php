<?php

namespace wzzwx\yii1model\traits;

class ModelTrait
{
    protected static function getDb()
    {
        return \Yii::app()->db;
    }

    protected static function createCommand()
    {
        return static::getDb()->createCommand(null, static::class);
    }

    public static function query($alias = '')
    {
        return static::createCommand()
            ->from($alias ? (static::$table . ' ' . $alias) : static::$table);
    }

    public static function find($id)
    {
        return static::query()->find($id);
    }

    public static function save(array $data)
    {
        $primaryKey = 'id';

        // Removes primary key value when it's empty to avoid SQL error
        if (array_key_exists($primaryKey, $data) && !$data[$primaryKey]) {
            unset($data[$primaryKey]);
        }

        if ($data[$primaryKey] ?? false) {
            $conditions = [$primaryKey => $data[$primaryKey]];
            unset($data[$primaryKey]);

            static::update($data, $conditions);
            $id = $conditions[$primaryKey];
        } else {
            static::createCommand()->insert(static::$table, $data);
            $id = static::getDb()->getLastInsertID();
        }

        return (int)$id;
    }

    public static function update(array $data, $condition, $params = [])
    {
        $cmd = static::createCommand();
        [$condition, $params] = $cmd->buildCondition($condition, $params);

        return $cmd->update(static::$table, $data, $condition, $params);
    }

    public static function delete($condition, $params = [])
    {
        $cmd = static::createCommand();
        [$condition, $params] = $cmd->buildCondition($condition, $params);

        return $cmd->delete(static::$table, $condition, $params);
    }

    public static function transactional(callable $fn)
    {
        $db = static::getDb();
        if ($db->getCurrentTransaction()) {
            return call_user_func($fn);
        }

        $transaction = $db->beginTransaction();
        try {
            $result = call_user_func($fn);
            $transaction->commit();

            return $result;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}