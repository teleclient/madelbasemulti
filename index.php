#!/usr/bin/env php
<?php
/**
 * Example combined event handler bot Multi.
*/
use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Exception;
use danog\MadelineProto\Logger;
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
        //if ($update['message']['_'] === 'messageEmpty' || $update['message']['out'] ?? false) {
        //    return;
        //}
        $res = \json_encode($update, JSON_PRETTY_PRINT);

        try {
            yield $this->messages->sendMessage([
                'peer' => $update,
                'message'         => "<code>$res</code>",
                'reply_to_msg_id' => $update['message']['id'] ?? null,
                'parse_mode'      => 'HTML']);
            if (isset($update['message']['media']) &&
                $update['message']['media']['_'] !== 'messageMediaGame')
            {
                yield $this->messages->sendMedia([
                    'peer'    => $update,
                    'message' => $update['message']['message'],
                    'media'   => $update
                ]);
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

$MadelineProtos = [];
foreach ([
     'bot.madeline'  => 'Bot Login',
    'user.madeline'  => 'Userbot login',
    'user2.madeline' => 'Userbot login (2)'
] as $session => $message) {
    Logger::log($message, Logger::WARNING);
    $MadelineProtos []= new API($session);
}

API::startAndLoopMulti($MadelineProtos, MyEventHandler::class);