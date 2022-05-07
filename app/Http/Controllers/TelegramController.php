<?php

namespace App\Http\Controllers;

use App\Traits\RequestTrait;
use Illuminate\Http\Request;
use Orhanerday\OpenAi\OpenAi;

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
        $open_ai = new OpenAi(env('OPEN_AI_API_KEY'));
        $result = json_decode(file_get_contents('php://input'));

        $telegramId = $result->message->chat->id;
        $text = $result->message->text;

        $this->apiRequest('sendChatAction', [
            'chat_id' => $telegramId,
            'action' => 'typing',
        ]);

        // TODO: Create table for storing user's messages and separate the context from the message
        $prompt = "The following is a conversation with an AI companion named Atmaya. The companion is empathic whose primary goal is to be kind and supportive.\n\nHuman: Hello, who are you?\nAI: I am an AI created by OpenAI. How can I help you today?\nHuman: " . $text . "\nAI:";

        $data = $open_ai->complete([
            'engine' => 'davinci',
            'prompt' => $prompt,
            'temperature' => 0.9,
            'max_tokens' => 150,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.6,
            'stop' => ["Human:", "AI:"],
        ]);

        $complete = json_decode($data);

        $response = $this->apiRequest('sendMessage', [
            'chat_id' => $telegramId,
            'text' => $complete->choices[0]->text,
            'parse_mode' => 'html',
        ]);

        return $complete;
    }
}
