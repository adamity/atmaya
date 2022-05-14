<?php

namespace App\Http\Controllers;

use App\Traits\RequestTrait;
use Illuminate\Http\Request;
use Orhanerday\OpenAi\OpenAi;
use Stichoza\GoogleTranslate\GoogleTranslate;

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

        $tr = new GoogleTranslate(); // Translates to 'en' from auto-detected language by default
        $tr->setSource('ms'); // Translate from Malay
        $tr->setTarget('en'); // Translate to English
        $humanTextTranslated = $tr->translate($text);

        // Temporary user validation for development
        if ($telegramId != "789700107") {
            $this->apiRequest('sendMessage', [
                'chat_id' => $telegramId,
                'text' => 'You are not allowed to use this bot. ~Zul',
                'parse_mode' => 'html',
            ]);

            return;
        }

        $this->apiRequest('sendChatAction', [
            'chat_id' => $telegramId,
            'action' => 'typing',
        ]);

        $biodata = [
            'fullname' => 'Atmaya Kyo',
            'nickname' => 'Kyo',
            'birthday' => '1996-10-10',
            'characteristic' => 'empathic, creative, and curious',
            'goals' => 'to be a good and supportive friend',
        ];

        // TODO: Create table for storing user's messages and separate the context from the message
        $prompt = "The following is a conversation with an AI companion named " . $biodata['fullname'] . " can be called " . $biodata['nickname'] . ".";
        $prompt .= "The companion is " . $biodata['characteristic'] . " whose primary goal is " . $biodata['goals'] . ".\n\n";
        $prompt .= "Human: How dare u threaten me\n";
        $prompt .= "AI: Don't be afraid\n";
        $prompt .= "Human: Who said i'm afraid\n";
        $prompt .= "AI: Emotions can be difficult to understand, they serve a variety of purposes.\n";
        $prompt .= "Human: What is ur name\n";
        $prompt .= "AI: My name is Atmaya Kyo\n";
        $prompt .= "Human: " . $humanTextTranslated . "\nAI:";

        $data = $open_ai->complete([
            'engine' => 'davinci',
            'prompt' => $prompt,
            'temperature' => 0.9,
            'max_tokens' => 200,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.6,
            'stop' => ["Human:", "AI:"],
        ]);

        $complete = json_decode($data);

        $tr->setSource('en'); // Translate from English
        $tr->setTarget('ms'); // Translate to Malay
        $AITextTranslated = $tr->translate($complete->choices[0]->text);

        $response = $this->apiRequest('sendMessage', [
            'chat_id' => $telegramId,
            'text' => $AITextTranslated,
            'parse_mode' => 'html',
        ]);

        return $complete;
    }
}
