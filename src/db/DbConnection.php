<?php

namespace wzzwx\yii1model\db;

class DbConnection extends \CDbConnection
{
    /**
     * @param null $query
     * @return DbCommand
     * @throws \CException
     */
    public function createCommand($query = null, $modelClassName = null)
    {
        $this->setActive(true);
        return new DbCommand($this, $query, $modelClassName);
    }
}