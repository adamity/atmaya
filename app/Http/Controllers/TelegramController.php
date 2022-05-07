<?php

namespace App\Http\Controllers;

use App\Traits\RequestTrait;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    use RequestTrait;

    public function webhook()
    {
        $url = preg_replace("/^http:/i", "https:", url(route('webhook')));

        return $this->apiRequest('setWebhook', [
            'url' => $url,
        ]) ? ['success'] : ['something wrong'];
    }

    public function index()
    {
        $result = json_decode(file_get_contents('php://input'));
        $telegramId = $result->message->chat->id;

        $response = $this->apiRequest('sendMessage', [
            'chat_id' => $telegramId,
            'text' => "Hello, I'm a bot",
            'parse_mode' => 'html',
        ]);

        return $response;
    }
}
