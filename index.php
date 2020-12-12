#!/usr/bin/env php
<?php
/**
 * Example combined event handler bot Multi.
*/
use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Tools;
use danog\MadelineProto\RPCErrorException;

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
include 'config.php';


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
        return [/*self::ADMIN*/];
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
        $res = \json_encode($update, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

        try {
            yield $this->logger($res);
        } catch (RPCErrorException $e) {
            $this->report("Surfaced: $e");
        } catch (Exception $e) {
            if (\stripos($e->getMessage(), 'invalid constructor given') === false) {
                $this->report("Surfaced: $e");
            }
        }
    }
}


function startAndLoopAsync($mp): \Generator
{
    $mp->async(true);
    yield $mp->start();
    yield $mp->setEventHandler(MyEventHandler::class);
    return yield from $mp->API->loop();
}

if(file_exists('MadelineProto.log')) unlink('MadelineProto.log');
$settings['logger']['logger_level']   = Logger::ULTRA_VERBOSE;
$settings['logger']['logger']         = Logger::FILE_LOGGER;
$userSettings['app_info']['api_id']   = $GLOBALS["API_ID"];
$userSettings['app_info']['api_hash'] = $GLOBALS["API_HASH"];
var_export(array_merge($settings, $userSettings));

echo('User1'.PHP_EOL);
$mps[0] = new API('user.madeline', array_merge($settings, $userSettings));

echo('Bot1'.PHP_EOL);
$mps[1] = new API('bot.madeline', $settings);

//while (true) {
    try {
        $mps[0]->echo('User'.PHP_EOL);
        $mps[0]->start(['async' => false]);
        $promises[0] = startAndLoopAsync($mps[0]);

        $mps[1]->echo('Bot'.PHP_EOL);
        $mps[1]->botLogin($GLOBALS["BOT_TOKEN"]);
        $mps[1]->start(['async' => false]);
        $promises[1] = startAndLoopAsync($mps[1]);

        Tools::wait(Tools::all($promises));
    } catch (\Throwable $e) {
        $mps[0]->logger((string) $e, Logger::FATAL_ERROR);
        $mps[0]->report("Surfaced: $e");
    }
//}
