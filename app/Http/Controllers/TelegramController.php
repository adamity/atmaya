<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use App\Traits\CommandTrait;
use App\Traits\RequestTrait;

class TelegramController extends Controller
{
    use CommandTrait;
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
        $request = json_decode(file_get_contents('php://input'));
        $response = "Not the expected update type.";

        if (isset($request->message)) {
            $response = $this->updateMessage($request);
        } else if (isset($request->callback_query)) {
            $response = "Callback query received.";
        } else if (isset($request->my_chat_member)) {
            $response = "Chat member received.";
        }

        return $response;
    }

    public function updateMessage($request)
    {
        $action = $request->message->text;
        $teleUser = TelegramUser::where('telegram_id', $request->message->from->id)->first();

        $this->apiRequest('sendChatAction', [
            'chat_id' => $request->message->from->id,
            'action' => 'typing',
        ]);

        if ($teleUser && $teleUser->mode) {
            $response = $this->updateSession($request);
        } else switch ($action) {
            case '/start':
                $response = $this->getCommands($request, null);
                break;
            case '/help':
                $response = $this->getCommands($request, null);
                break;
            case '/customer_service':
            case '👨‍💻 Customer Service':
                $response = $this->startCustomerService($request);
                break;
            case '/preconsult':
            case '👩‍⚕️ Preconsult':
                $response = $this->startPreconsult($request);
                break;
            case '/cancel':
            case '❌ Cancel':
                $response = $this->cancelOperation($request);
                break;
            default:
                $response = $this->getCommands($request, null);
                break;
        }

        return $response;
    }
}
