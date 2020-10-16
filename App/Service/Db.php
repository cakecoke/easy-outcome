<?php


namespace App\Service;


use Medoo\Medoo;

class Db
{
    // todo dt format const

    public static function getDb()
    {
        return new Medoo([
            'database_type' => 'sqlite',
            'database_file' => 'database/database.db',
        ]);
    }
}