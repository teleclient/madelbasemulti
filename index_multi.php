#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * Example Multi-Account bot.
 */

use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\Tools;

/*
 * Various ways to load MadelineProto
 */
if (\file_exists('vendor/autoload.php')) {
    include 'vendor/autoload.php';
} else {
    if (!\file_exists('madeline.php')) {
        \copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    include 'madeline.php';
}

/**
 * Event handler class.
 */
class MyEventHandler extends EventHandler
{
    /**
     * @var int|string Username or ID of bot admin
     */
    const ADMIN = "webwarp"; // Change this

    /**
     * Get peer(s) where to report errors.
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }

    /**
     * Handle updates from supergroups and channels.
     *
     * @param array $update Update
     *
     * @return void
     */
    public function onUpdateNewChannelMessage(array $update): \Generator
    {
        return $this->onUpdateNewMessage($update);
    }

    /**
     * Handle updates from users.
     *
     * @param array $update Update
     *
     * @return \Generator
     */
    public function onUpdateNewMessage(array $update): \Generator
    {
        if ($update['message']['_'] === 'messageEmpty' || $update['message']['out'] ?? false) {
            return;
        }
        $res = \json_encode($update, JSON_PRETTY_PRINT);

        try {
            yield $this->messages->sendMessage(['peer' => $update, 'message' => "<code>$res</code>", 'reply_to_msg_id' => isset($update['message']['id']) ? $update['message']['id'] : null, 'parse_mode' => 'HTML']);
            if (isset($update['message']['media']) && $update['message']['media']['_'] !== 'messageMediaGame') {
                yield $this->messages->sendMedia(['peer' => $update, 'message' => $update['message']['message'], 'media' => $update]);
            }
        } catch (RPCErrorException $e) {
            $this->report("Surfaced: $e");
        } catch (Exception $e) {
            if (\stripos($e->getMessage(), 'invalid constructor given') === false) {
                $this->report("Surfaced: $e");
            }
        }
    }
}


function getSettings(int $appId) : array
{
    if($appId >= 0 && $appId <= 1) {
        $settings = [];
        //$settings['app_info']['api_id']   = $this->accounts[$appId]['api_id'];
        //$settings['app_info']['api_hash'] = $this->accounts[$appId]['api_hash'];
        return $settings;
    }
    throw new InvalidArgumentException('AppId must be 0 or 1. Input was: '.$appId);
}

/*
function getSession(int $appId): string {
    if($appId >= 0 && $appId <= 1) {
        return $appId === 0? 'user.madeline' : 'user.madeline';
    }
    throw new InvalidArgumentException('AppId must be 0 or 1. Input was: '.$appId);
}
function getEventHandler(int $appId): string {
    if($appId >= 0 && $appId <= 1) {
        return $appId === 0? 'MyEventHandler::class' : 'MyEventHandler::class';
    }
    throw new InvalidArgumentException('AppId must be 0 or 1. Input was: '.$appId);
}
*/

function getToken(array $app): array
{
    return $app['account']['token']?? null;
}
function getInstance(array $app): API
{
    $settings = $app['settings'];
    if(isset($app['account']['id']) && isset($app['account']['hash'])) {
        $settings []= ['api_id'   => $app['account']['id']];
        $settings []= ['api_hash' => $app['account']['hash']];
    }
    return new API($app['session'], $settings);
}

/*
function startAndLoopAsync($instance, $eventHandler): \Generator
{
    $instance->async(true);
    yield $instance->start();
    yield $instance->setEventHandler($eventHandler);
    return yield from $instance->API->loop();
}
*/

$apps []= [
    'account'  => ['id' => 123, 'hash' => ''],
    'session'  => 'user.madeline',
    'handler'  => 'MyEventHandler::class',
    'settings' => getSettings(0)
];
$apps []= [
    'account'  => ['token' => ''],
    'session'  => 'bot.madeline',
    'handler'  => 'MyEventHandler::class',
    'settings' => getSettings(1)
];
$instances []= getInstance($app[0]);
$instances []= getInstance($app[1]);
while (true) {
    try {
        $promises = [];
        for ($index = 0; $index < sizeof($instances); $index++) {
            $instances[$index]->start(['async' => false]);
            $promises []= (function ($instance, $app): \Generator {
                $instance->async(true);
                if(getToken($app) !== null) {
                    $autorization = yield $instance->botLogin(getToken($app));
                    $self = $autorization['user'];
                }
                $self = yield $instance->start();
                yield $instance->setEventHandler($app['handler']);
                return yield from $instance->API->loop();
            })($instances[$index], $apps[$index]['handler']);
        }
        Tools::wait(Tools::all($promises));
        //return;
    } catch (\Throwable $e) {
        $instances[0]->logger((string) $e, Logger::FATAL_ERROR);
        $instances[0]->report("Surfaced: $e");
    }
}
