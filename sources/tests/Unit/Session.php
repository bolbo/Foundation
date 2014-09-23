<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit;

use PommProject\Foundation\DatabaseConfiguration;
use Mock\PommProject\Foundation\Client\ClientInterface       as ClientInterfaceMock;
use Mock\PommProject\Foundation\Client\ClientPoolerInterface as ClientPoolerInterfaceMock;
use Atoum;

class Session extends Atoum
{
    protected function getSession(array $config = [], $ignore = false)
    {
        if ($ignore === false) {
            $config = array_merge($GLOBALS['pomm_db1'], $config);
        }

        return $this->newTestedInstance(new DatabaseConfiguration('test', $config));
    }

    protected function getClientInterfaceMock($identifier)
    {
        $client = new ClientInterfaceMock();
        $client->getMockController()->getClientType = 'test';
        $client->getMockController()->getClientIdentifier = $identifier;

        return $client;
    }

    protected function getClientPoolerInterfaceMock($type)
    {
        $client_pooler = new ClientPoolerInterfaceMock();
        $client_pooler->getMockController()->getPoolerType = $type;
        $client_pooler->getMockController()->getClient = $this->getClientInterfaceMock('ok');

        return $client_pooler;
    }

    public function testConstructor()
    {
        $this
            ->exception(function() { $this->getSession([], true); })
            ->isInstanceOf('\PommProject\Foundation\Exception\FoundationException')
            ->message->contains('is mandatory')
            ->object($this->getSession())
            ->isInstanceOf('\PommProject\Foundation\Session')
            ;
    }

    public function testGetHandler()
    {
        $session = $this->getSession();

        $this
            ->boolean(is_resource($session->getHandler()))
            ->isTrue()
            ->string(get_resource_type($session->getHandler()))
            ->isEqualTo('pgsql link')
            ;
    }

    public function testGetClient()
    {
        $session = $this->getSession();
        $client  = $this->getClientInterfaceMock('one');
        $session->registerClient($client);
        $this
            ->variable($session->getClient('test', 'two'))
            ->isNull()
            ->object($session->getClient('test', 'one'))
            ->isIdenticalTo($client)
            ->variable($session->getClient('whatever', 'two'))
            ->isNull()
            ->variable($session->getClient(null, 'two'))
            ->isNull()
            ;
    }

    public function testRegisterClient()
    {
        $session     = $this->getSession();
        $client_mock = $this->getClientInterfaceMock('one');

        $this
            ->variable($session->getClient('test', 'one'))
            ->isNull()
            ->object($session->registerClient($client_mock))
            ->isInstanceOf('\PommProject\Foundation\Session')
            ->mock($client_mock)
            ->call('getClientIdentifier')
            ->once()
            ->call('getClientType')
            ->once()
            ->call('initialize')
            ->once()
            ->object($session->getClient('test', 'one'))
            ->isIdenticalTo($client_mock)
            ;
    }

    public function testRegisterPooler()
    {
        $session            = $this->getSession();
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');

        $this
            ->boolean($session->hasPoolerForType('test'))
            ->isFalse()
            ->assert('Testing client pooler registration.')
            ->object($session->registerClientPooler($client_pooler_mock))
            ->isInstanceOf('\PommProject\Foundation\Session')
            ->boolean($session->hasPoolerForType('test'))
            ->isTrue()
            ->mock($client_pooler_mock)
            ->call('getPoolerType')
            ->atLeastOnce()
            ->call('register')
            ->once()
            ;
    }

    public function testGetPoolerForType()
    {
        $session            = $this->getSession();
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');

        $this
            ->exception(function() use ($session) { $session->getPoolerForType('test'); })
            ->isInstanceOf('\PommProject\Foundation\Exception\FoundationException')
            ->message->contains('No pooler registered for type')
            ->object($session
                ->registerClientPooler($client_pooler_mock)
                ->getPoolerForType('test')
            )
            ->isIdenticalTo($client_pooler_mock)
            ;
    }

    public function testGetClientUsingPooler()
    {
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');
        $session            = $this->getSession()->registerClientPooler($client_pooler_mock);

        $this
            ->object($session->getClientUsingPooler('test', 'ok'))
            ->isInstanceOf('\PommProject\Foundation\Client\ClientInterface')
            ->exception(function() use ($session) {$session->getClientUsingPooler('whatever', 'ok');})
            ->isInstanceOf('\PommProject\Foundation\Exception\FoundationException')
            ->message->contains('No pooler registered for type')
            ;
    }

    public function testUnderscoreCall()
    {
        $client_pooler_mock = $this->getClientPoolerInterfaceMock('test');
        $session            = $this->getSession()->registerClientPooler($client_pooler_mock);

        $this
            ->exception(function() use ($session) { $session->azerty('ok', 'what'); })
            ->isInstanceOf('\BadFunctionCallException')
            ->message->contains('Unknown method')
            ->exception(function() use ($session) { $session->getPika('ok'); })
            ->isInstanceOf('\PommProject\Foundation\Exception\FoundationException')
            ->message->contains('No pooler registered for type')
            ->object($session->getTest('ok'))
            ->isInstanceOf('\PommProject\Foundation\Client\ClientInterface')
            ->mock($client_pooler_mock)
            ->call('getClient')
            ->withArguments('ok')
            ->once()
            ;
    }

    public function testExecuteAnonymousQuery()
    {
        $session = $this->getSession();
        $this
            ->boolean(is_resource($session->executeAnonymousQuery('select true')))
            ->isTrue()
            ->string(get_resource_type($session->executeAnonymousQuery('select true')))
            ->isEqualTo('pgsql result')
            ->exception(function() use ($session) {
                    $session->executeAnonymousQuery('zesdflxcv');
                })
            ->isInstanceOf('\PommProject\Foundation\Exception\SqlException')
            ;
    }
}
