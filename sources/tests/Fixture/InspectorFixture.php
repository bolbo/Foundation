<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Fixture;

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Session;

Class InspectorFixture extends Client
{
    protected function executeAnonymousQuery($sql)
    {
        return $this
            ->getSession()
            ->getConnection()
            ->executeAnonymousQuery($sql);
    }

    public function getClientType()
    {
        return 'fixture';
    }

    public function getClientIdentifier()
    {
        return 'inspector';
    }

    public function createSchema()
    {
        $this->dropSchema();
        $sql = [];
        $sql[] = "create schema inspector_test";
        $sql[] = "create table inspector_test.no_pk (a_boolean bool, varchar_array character varying[])";
        $sql[] = "create table inspector_test.with_simple_pk (with_simple_pk_id int4 primary key, a_char char, some_timestamps timestamptz[])";
        $sql[] = "create table inspector_test.with_complex_pk (with_complex_pk_id int4, another_id int4, created_at timestamp not null default now(), primary key (with_complex_pk_id, another_id))";
        $this->executeAnonymousQuery(join('; ', $sql));
    }

    public function dropSchema()
    {
        $sql = "drop schema if exists inspector_test cascade";
        $this->executeAnonymousQuery($sql);
    }
}
