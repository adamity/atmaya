<?php

namespace App\Traits;

use App\Traits\NLPTrait;

trait TextTrait
{
    use NLPTrait;

    private function commandText($text = "Here's how our friendly bot can assist:")
    {
        // $message .= "🤖 Quick Response: /quick_response\n- Got simple medical questions on your mind? Ask away, and we'll provide quick, reliable answers.\n\n";
        $message = $this->rephraseSentence($text) . "\n\n\n";
        $message .= "👨‍💻 Customer Service: /customer_service\n- Have questions about our product or services? Feel free to ask us anything, and we'll be happy to help you out.\n\n";
        $message .= "👩‍⚕️ Preconsult: /preconsult\n- Ready to start your preconsultation? Let's get the process going.\n\n";
        $message .= "❌ Cancel: /cancel\n- Cancel any ongoing operation.\n\n\n";
        $message .= $this->rephraseSentence("Our aim is to bring you convenient healthcare solutions, anytime and anywhere.");
        return $message;
    }
}
